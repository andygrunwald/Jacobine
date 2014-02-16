<?php
/**
 * @todo adds a description (license text, description of this class / file, etc)
 *
 * Some of the logic (communication with NNTP server and transformation to utf8) of this class
 * is based on the work of dkd (Ingo Renner / @irnnr)
 * Thx to dkd (Olivier Dobberkau / @T3RevNeverEnd) to publish the code!
 *
 * @link https://github.com/dkd/solrnntp/blob/master/scheduler/class.tx_solrnntp_scheduler_indextask.php
 */
namespace TYPO3Analysis\Consumer\Crawler;

use TYPO3Analysis\Consumer\ConsumerAbstract;

class NNTPGroup extends ConsumerAbstract
{

    /**
     * Gets a description of the consumer
     *
     * @return string
     */
    public function getDescription()
    {
        return 'Imports a single NNTP group of a NNTP server';
    }

    /**
     * Initialize the consumer.
     * Sets the queue and routing key
     *
     * @return void
     */
    public function initialize()
    {
        $this->setQueue('crawler.nntpgroup');
        $this->setRouting('crawler.nntpgroup');
    }

    /**
     * The logic of the consumer
     *
     * @param \stdClass $message
     * @return null|void
     */
    public function process($message)
    {
        $this->setMessage($message);
        $messageData = json_decode($message->body);
        $groupId = (int)$messageData->groupId;
        $nntpHost = $messageData->host;

        $this->getLogger()->info('Receiving message', (array)$messageData);

        $record = $this->getNNTPGroupFromDatabase($groupId);

        // If the record does not exists in the database exit here
        if ($record === false) {
            $context = array('groupId' => $groupId);
            $this->getLogger()->info('Record does not exist in nntp_group table', $context);
            $this->acknowledgeMessage($message);
            return;
        }

        $groupName = $record['name'];

        // Bootstrap NNTP client
        $nntpClient = new \Net_NNTP_Client();
        $nntpClient->connect($nntpHost);

        $this->getLogger()->info('Select NNTP group', array('group' => $groupName));
        $nntpClient->selectGroup($groupName);

        // Select last indexed article
        $articleNumber = $lastIndexedArticle = (int)$record['last_indexed'];
        if ($articleNumber <= 0) {
            // first time indexing
            $articleNumber = (int)$nntpClient->first();
        }

        $this->getLogger()->info('Select NNTP article', array('article' => $articleNumber));
        $dummyArticle = $nntpClient->selectArticle($articleNumber);

        // Check if the last article is still the last article
        $lastArticleNumber = (int)$nntpClient->last();
        if ($articleNumber === $lastArticleNumber) {
            $this->getLogger()->info('Group got no new articles', array('group' => $groupName));
            $this->acknowledgeMessage($message);
            return;

        } else {
            $articleNumber = (($articleNumber === 1) ? $articleNumber : $nntpClient->selectNextArticle());
        }

        $pearObj = new \PEAR();
        if ($pearObj->isError($dummyArticle)) {
            $this->getLogger()->info('Article can not be selected', array('article' => $articleNumber));
            $this->acknowledgeMessage($message);
            return;
        }

        // Loop over aaaaaaall articles
        do {
            // Fetch overview for currently selected article
            $article = $nntpClient->getArticle();

            if ($pearObj->isError($article)) {
                break;
            }

            $context = array(
                'article' => $articleNumber,
                'groupId' => $groupId
            );

            if ($this->articleExists($groupId, $articleNumber) === false) {
                $this->getLogger()->info('Index article', $context);

                // Which charset?
                $articleHeader = $nntpClient->getHeader();
                $articleBody = $nntpClient->getBody(null, true);

                $contentType = $nntpClient->getHeaderField('Content-Type');
                $charset = $this->getArticleCharset($contentType);
                // $charset = $this->getArticleCharset($articleHeader);

                $articleHeader = $this->convertArticleHeaderToUTF8($articleHeader, $charset);

                $articleBody = $this->convertTextToUTF8($articleBody, $charset);
                $articleBody = quoted_printable_decode($articleBody);

                $this->indexNNTPGroupArticle(
                    $nntpClient,
                    $groupId,
                    $articleNumber,
                    $articleHeader,
                    $articleBody
                );
            } else {
                $this->getLogger()->info('Article already exists', $context);
            }

            unset($articleHeader, $articleBody);

            $articleNumber = $nntpClient->selectNextArticle();
            switch (true) {
                case is_int($articleNumber):
                    $lastIndexedArticle = $articleNumber;
                    break;
                case $pearObj->isError($articleNumber):
                    $articleNumber = false;
                    break;
            }
        } while ($articleNumber !== false);

        $this->updateLastIndexedArticle($groupId, $lastIndexedArticle);

        $nntpClient->disconnect();
        unset($nntpClient);

        $this->acknowledgeMessage($message);

        $this->getLogger()->info('Finish processing message', (array)$messageData);
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
        $rows = $this->getDatabase()->getRecords($fields, 'nntp_group', array('id' => $id), '', '', 1);

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
        $this->getDatabase()->updateRecord('nntp_group', $data, $where);
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
        $insertedId = $this->getDatabase()->insertRecord('nntp_article', $data);
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
            $this->getDatabase()->insertRecord('nntp_article_header', $data);
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
        $rows = $this->getDatabase()->getRecords(array('id'), 'nntp_article', $where);

        $result = false;
        if (count($rows)) {
            $result = true;
            unset($rows);
        }

        return $result;
    }
}
