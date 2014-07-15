<?php
/**
 * This file is part of the Jacobine package.
 *
 * (c) Andreas Grunwald <andygrunwald@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Jacobine\Consumer\Crawler;

use Jacobine\Consumer\ConsumerAbstract;
use Jacobine\Component\AMQP\MessageQueue;
use Jacobine\Component\Database\Database;

/**
 * Class NNTP
 *
 * This consumer got two different tasks:
 *  * Crawl a NNTP Server (http://en.wikipedia.org/wiki/Network_News_Transfer_Protocol)
 *    This consumer is responsible to receive all groups from a single NNTP server
 *    store the group in the database and create a seperate message for each NNTP group
 *  * Crawl a single group of a NNTP Server (http://en.wikipedia.org/wiki/Network_News_Transfer_Protocol)
 *    This consumer is responsible to receive all messages from a single group of a NNTP server
 *    and store them in the database
 *
 * Some of the logic (communication with NNTP server and transformation to utf8) of this class
 * is based on the work of dkd (https://www.dkd.de/) and Ingo Renner (@irnnr)
 * Thx to dkd, specially Olivier Dobberkau (@T3RevNeverEnd) for code publishing to github and Ingo for coding <3
 *
 * Message format (json encoded):
 *  [
 *      config: Config array which contains the Host of the NNTP-Server
 *      project: Project to be analyzed. Must be a configured project in "configFile"
 *      host: Host of the NNTP-Server
 *      groupId: ID of NNTP group stored in the database
 *  ]
 *
 * Usage:
 *  php console analysis:consumer Crawler\\NNTP
 *
 * @package Jacobine\Consumer\Crawler
 * @author Andy Grunwald <andygrunwald@gmail.com>
 * @link https://github.com/dkd/solrnntp/blob/master/scheduler/class.tx_solrnntp_scheduler_indextask.php
 */
class NNTP extends ConsumerAbstract
{

    /**
     * @var \PEAR
     */
    protected $pear;

    /**
     * @var \Net_NNTP_Client
     */
    protected $nntpClient;

    /**
     * Constructor to set dependencies
     *
     * @param MessageQueue $messageQueue
     * @param Database $database
     * @param \PEAR $pear
     * @param \Net_NNTP_Client $nntpClient
     */
    public function __construct(
        MessageQueue $messageQueue,
        Database $database,
        \PEAR $pear,
        \Net_NNTP_Client $nntpClient
    ) {
        $this->setDatabase($database);
        $this->setMessageQueue($messageQueue);
        $this->pear = $pear;
        $this->nntpClient = $nntpClient;
    }

    /**
     * Gets a description of the consumer
     *
     * @return string
     */
    public function getDescription()
    {
        return 'Crawls a NNTP server for groups and add further messages for every single group';
    }

    /**
     * Initialize the consumer.
     * Sets the queue and routing key
     *
     * @return void
     */
    public function initialize()
    {
        parent::initialize();

        $this->setQueueOption('name', 'crawler.nntp');
        $this->enableDeadLettering();

        $this->setRouting('crawler.nntp');
    }

    /**
     * The main logic of the consumer
     *
     * @param \stdClass $message
     * @throws \Exception
     * @return void
     */
    protected function process($message)
    {
        $type = strtolower($message->type);
        switch ($type) {
            case 'server':
                $this->processNNTPServer($message);
                break;

            case 'group':
                $this->processNNTPGroup($message);
                break;

            default:
                throw new \Exception('Type "' . $type . '" not supported', 1400367495);
        }
    }

    /**
     * Crawls a single NNTP server and sends messages for every group tp crawl this group.
     *
     * @param \stdClass $message
     * @throws \Exception
     * @return void
     */
    private function processNNTPServer($message)
    {
        $nntpConfig = $message->config;

        if (is_object($nntpConfig) === false || property_exists($nntpConfig, 'Host') === false) {
            $context = array('config' => $nntpConfig);
            $this->getLogger()->critical('NNTP configuration does not exist or is incomplete', $context);
            throw new \Exception('NNTP configuration does not exist or is incomplete', 1398887703);
        }

        $this->nntpClient->connect($nntpConfig->Host);

        $this->getLogger()->info('Requesting groups', array('host' => $nntpConfig->Host));
        $groups = $this->nntpClient->getGroups();

        $this->getLogger()->info('Requesting group descriptions', array('host' => $nntpConfig->Host));
        $descriptions = $this->nntpClient->getDescriptions();

        // Looping over the groups and get the shit done!
        foreach ($groups as $group) {
            $groupSummary = $this->nntpClient->selectGroup($group['group']);
            $groupRecord = $this->getGroupFromDatabase($group['group']);

            if ($groupRecord === false) {
                $description = ((array_key_exists(
                        $group['group'],
                        $descriptions
                    ) === true) ? $descriptions[$group['group']] : '');
                $id = $this->insertGroupRecord(
                    $group['group'],
                    $description,
                    $group['first'],
                    $group['last'],
                    $groupSummary['count'],
                    $group['posting']
                );

            } else {
                $id = $groupRecord['id'];
                $this->getLogger()->info('NNTP group record already exists', ['group' => $group['group']]);
            }

            $context = [
                'project' => $message->project,
                'groupId' => $id
            ];
            $this->getLogger()->info('Add nntp group to message queue "crawler.nntpgroup"', $context);
            $this->addFurtherMessageForNntpGroup($message->project, $nntpConfig->Host, $id);
        }
    }

    /**
     * Crawls a single NNTP group
     *
     * @param \stdClass $message
     * @throws \Exception
     * @return null|void
     */
    private function processNNTPGroup($message)
    {
        $groupId = (int) $message->groupId;
        $nntpHost = $message->host;

        $record = $this->getNNTPGroupFromDatabase($groupId);

        // If the record does not exists in the database exit here
        if ($record === false) {
            $context = array('groupId' => $groupId);
            $this->getLogger()->critical('Record does not exist in nntp_group table', $context);
            throw new \Exception('Record does not exist in nntp_group table', 1398887817);
        }

        $groupName = $record['name'];

        $this->nntpClient->connect($nntpHost);

        $this->getLogger()->info('Select NNTP group', array('group' => $groupName));
        $this->nntpClient->selectGroup($groupName);

        // Select last indexed article
        $articleNumber = $lastIndexedArticle = (int)$record['last_indexed'];
        if ($articleNumber <= 0) {
            // first time indexing
            $articleNumber = (int) $this->nntpClient->first();
        }

        $this->getLogger()->info('Select NNTP article', ['article' => $articleNumber]);
        $dummyArticle = $this->nntpClient->selectArticle($articleNumber);

        // Check if the last article is still the last article
        $lastArticleNumber = (int) $this->nntpClient->last();
        if ($articleNumber === $lastArticleNumber) {
            $this->getLogger()->info('Group got no new articles', ['group' => $groupName]);
            return;

        } else {
            $articleNumber = (($articleNumber === 1) ? $articleNumber: $this->nntpClient->selectNextArticle());
        }

        if ($this->pear->isError($dummyArticle)) {
            $this->getLogger()->critical('Article can not be selected', ['article' => $articleNumber]);
            throw new \Exception('Article can not be selected', 1398887859);
        }

        // Loop over aaaaaaall articles
        do {
            // Fetch overview for currently selected article
            $article = $this->nntpClient->getArticle();

            if ($this->pear->isError($article)) {
                break;
            }

            $context = array(
                'article' => $articleNumber,
                'groupId' => $groupId
            );

            if ($this->articleExists($groupId, $articleNumber) === false) {
                $this->getLogger()->info('Index article', $context);

                // Which charset?
                $articleHeader = $this->nntpClient->getHeader();
                $articleBody = $this->nntpClient->getBody(null, true);

                $contentType = $this->nntpClient->getHeaderField('Content-Type');
                $charset = $this->getArticleCharset($contentType);
                // $charset = $this->getArticleCharset($articleHeader);

                $articleHeader = $this->convertArticleHeaderToUTF8($articleHeader, $charset);

                $articleBody = $this->convertTextToUTF8($articleBody, $charset);
                $articleBody = quoted_printable_decode($articleBody);

                $this->indexNNTPGroupArticle(
                    $this->nntpClient,
                    $groupId,
                    $articleNumber,
                    $articleHeader,
                    $articleBody
                );
            } else {
                $this->getLogger()->info('Article already exists', $context);
            }

            unset($articleHeader, $articleBody);

            $articleNumber = $this->nntpClient->selectNextArticle();
            switch (true) {
                case is_int($articleNumber):
                    $lastIndexedArticle = $articleNumber;
                    break;
                case $this->pear->isError($articleNumber):
                    $articleNumber = false;
                    break;
            }
        } while ($articleNumber !== false);

        $this->updateLastIndexedArticle($groupId, $lastIndexedArticle);

        $this->nntpClient->disconnect();
        unset($nntpClient);
    }

    /**
     * Adds new messages to queue system to import a single nntp group
     *
     * @param string $project
     * @param string $host
     * @param integer $groupId
     * @return void
     */
    private function addFurtherMessageForNntpGroup($project, $host, $groupId)
    {
        $config = $this->getConfig();
        $projectConfig = $config['Projects'][$project];

        $message = [
            'project' => $project,
            'type' => 'group',
            'host' => $host,
            'groupId' => $groupId,
        ];

        $exchange = $projectConfig['RabbitMQ']['Exchange'];
        $this->getMessageQueue()->sendSimpleMessage($message, $exchange, 'crawler.nntp');
    }

    /**
     * Receives a single nntp_group record of the database
     *
     * @param string $group
     * @return bool|array
     */
    private function getGroupFromDatabase($group)
    {
        $fields = array('id');
        $rows = $this->getDatabase()->getRecords($fields, 'jacobine_nntp_group', array('name' => $group), '', '', 1);

        $row = false;
        if (count($rows) === 1) {
            $row = array_shift($rows);
            unset($rows);
        }

        return $row;
    }

    /**
     * Inserts a new nntp_group record to database
     *
     * @param string $name
     * @param string $description
     * @param integer $first
     * @param integer $last
     * @param integer $cnt
     * @param string $posting
     * @return string
     */
    private function insertGroupRecord($name, $description, $first, $last, $cnt, $posting)
    {
        $data = array(
            'name' => $name,
            'description' => $description,
            'first' => $first,
            'last' => $last,
            'cnt' => $cnt,
            'posting' => $posting
        );

        $this->getLogger()->info('Inserted new nntp_group record', $data);
        return $this->getDatabase()->insertRecord('jacobine_nntp_group', $data);
    }

    /**
     * Receives a single nntp_group record of the database
     *
     * @param integer $id
     * @return bool|array
     */
    private function getNNTPGroupFromDatabase($id)
    {
        $fields = array('id', 'name', 'last_indexed');
        $rows = $this->getDatabase()->getRecords($fields, 'jacobine_nntp_group', array('id' => $id), '', '', 1);

        $row = false;
        if (count($rows) === 1) {
            $row = array_shift($rows);
            unset($rows);
        }

        return $row;
    }

    /**
     * Updates the last indexed article of a single nntp group
     *
     * @param integer $groupId
     * @param integer $lastIndexedArticle
     * @return void
     */
    private function updateLastIndexedArticle($groupId, $lastIndexedArticle)
    {
        $data = array('last_indexed' => $lastIndexedArticle);
        $where = array('id' => $groupId);
        $this->getDatabase()->updateRecord('jacobine_nntp_group', $data, $where);
    }

    /**
     * Index a single newsgroup article
     *
     * @param \Net_NNTP_Client $nntpClient
     * @param integer $groupId
     * @param integer $articleNumber
     * @param array $header
     * @param string $body
     * @return void
     */
    private function indexNNTPGroupArticle($nntpClient, $groupId, $articleNumber, $header, $body)
    {
        $articleId = $this->indexArticle($groupId, $articleNumber, $body);
        $this->indexArticleHeader($nntpClient, $articleId, $header);
    }

    /**
     * Insert a new nntp article to database
     *
     * @param integer $groupId
     * @param integer $articleNumber
     * @param string $body
     * @return integer
     */
    private function indexArticle($groupId, $articleNumber, $body)
    {
        $data = array(
            'group_id' => $groupId,
            'article_no' => $articleNumber,
            'message' => $body
        );
        $insertedId = $this->getDatabase()->insertRecord('jacobine_nntp_article', $data);
        return $insertedId;
    }

    /***
     * Indexs the header of an article
     *
     * @param \Net_NNTP_Client $nntpClient
     * @param integer $articleId
     * @param array $header
     * @return void
     */
    private function indexArticleHeader($nntpClient, $articleId, $header)
    {
        foreach ($header as $singleHeaderLine) {
            $headerName = $this->getHeaderName($singleHeaderLine);
            if ($headerName === false) {
                continue;
            }

            $header = $nntpClient->getHeaderField($headerName);
            $header = quoted_printable_decode($header);

            $data = array(
                'article_id' => $articleId,
                'header' => $headerName,
                'content' => $header
            );
            $this->getDatabase()->insertRecord('jacobine_nntp_article_header', $data);
        }
    }

    /**
     * Get the header name of a header line
     * Example header line
     *  Content-Type: text/plain; charset=ISO-8859-1
     *
     * @param string $headerLine
     * @return bool|string
     */
    private function getHeaderName($headerLine)
    {
        // Split it by ':'
        $colon = strpos($headerLine, ':');
        if ($colon === false) {
            return false;
        }

        $headerName = substr($headerLine, 0, $colon);

        // Check if the header name contains a space or a tab
        // Some headers are multiline headers
        if (strstr($headerName, ' ') !== false || strstr($headerName, chr(9)) !== false) {
            return false;
        }

        return $headerName;
    }

    /**
     * Returns the charset of the article
     *
     * We have to parse the charset from the email header (Content-Type).
     * We got a lot of different stuff here. Some examples
     *
     *  text/html; charset="iso-8859-1"
     *  text/html; charset=iso-8859-1
     *  text/html; charset=ISO-8859-15
     *  text/plain;    charset="iso-8859-1"
     *  text/plain;    charset="us-ascii"
     *  text/plain;    charset="utf-8"
     *  text/plain;    charset="windows-1255"
     *  text/plain;    charset=ISO-8859-1;    format="flowed"
     *  text/plain; charset=ISO-8859-1; delsp=yes; format=flowed
     *  text/plain; delsp=yes; charset=ISO-8859-1; format=flowed
     *  text/plain; format=flowed; charset="iso-8859-1";    reply-type=original
     *  text/plain; format=flowed; delsp=yes; charset=iso-8859-15
     *  ...
     *
     * @param string $header
     * @return string
     */
    private function getArticleCharset($header)
    {
        $charset = 'utf-8'; // assuming the best case

        $matches = array();
        preg_match('/charset="?([^";]*)/i', $header, $matches);

        if (count($matches) > 0) {
            $charset = strtolower($matches[1]);
        }

        return $charset;
    }

    /**
     * Converts a text to UTF-8
     *
     * What is the best way to do this job?
     * iconv with //IGNORE or //TRANSLIT ?
     * mb_convert_encoding ?
     * I do not have real clue about the whole charset thingy.
     * What are the (dis)-advantages of the different ways?
     * Which languages are supported / not supported?
     * If anyone knows the answer please inform me and do not let my die so stupid.
     *
     * @todo sometimes we got a fucked up charset. Idea: Decouple TYPO3 charset library to use it standalone?
     *
     * @todo When charset is windows-1250 / US-ASCII a PHP Warning will be thrown:
     *      mb_convert_encoding(): Illegal character encoding specified in ..
     *
     * @param string $text
     * @param string $currentCharset
     * @return string
     */
    private function convertTextToUTF8($text, $currentCharset)
    {
        $currentCharset = strtoupper($currentCharset);

        if ($currentCharset == 'UTF-8' || !$currentCharset) {
            return $text;
        }

        if ($currentCharset == 'ISO-8859-1') {
            $text = utf8_encode($text);

        } else {
            // $text = iconv(strtoupper($currentCharset), 'UTF-8//TRANSLIT', $text);
            $text = mb_convert_encoding($text, 'UTF-8', strtoupper($currentCharset));
        }


        return $text;
    }

    /**
     * Converts the header of a message to UTF-8
     *
     * @param array $header
     * @param string $currentCharset
     * @return array
     */
    private function convertArticleHeaderToUTF8(array $header, $currentCharset)
    {
        if ($currentCharset == 'utf-8') {
            return $header;
        }

        $convertedHeader = array();
        foreach ($header as $key => $singleHeaderLine) {
            $convertedHeader[$key] = $this->convertTextToUTF8($singleHeaderLine, $currentCharset);
        }

        return $convertedHeader;
    }

    /**
     * Checks if the article already exists
     *
     * @param integer $groupId
     * @param integer $articleNumber
     * @return bool
     */
    private function articleExists($groupId, $articleNumber)
    {
        $where = array(
            'group_id' => $groupId,
            'article_no' => $articleNumber,
        );
        $rows = $this->getDatabase()->getRecords(array('id'), 'jacobine_nntp_article', $where);

        $result = false;
        if (count($rows)) {
            $result = true;
            unset($rows);
        }

        return $result;
    }
}
