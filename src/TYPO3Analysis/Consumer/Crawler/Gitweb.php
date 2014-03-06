<?php
/**
 * This file is part of the TYPO3-Analytics package.
 *
 * (c) Andreas Grunwald <andygrunwald@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TYPO3Analysis\Consumer\Crawler;

use Symfony\Component\DomCrawler\Crawler;
use TYPO3Analysis\Consumer\ConsumerAbstract;

class Gitweb extends ConsumerAbstract
{

    /**
     * HTTP Client
     *
     * @var \Buzz\Browser
     */
    protected $browser = null;

    /**
     * Gets a description of the consumer
     *
     * @return string
     */
    public function getDescription()
    {
        return 'Crawls a Gitweb-Index page for Git-repositories';
    }

    /**
     * Initialize the consumer.
     * Sets the queue and routing key
     *
     * @return void
     */
    public function initialize()
    {
        $this->setQueue('crawler.gitweb');
        $this->setRouting('crawler.gitweb');

        $config = $this->getConfig();

        $curlClient = new \Buzz\Client\Curl();
        $curlClient->setVerifyPeer(false);
        $curlClient->setIgnoreErrors(true);
        $curlClient->setTimeout(intval($config['Various']['Requests']['Timeout']));
        $this->browser = new \Buzz\Browser($curlClient);
    }

    /**
     * The logic of the consumer
     *
     * @param \stdClass $message
     * @return void
     */
    public function process($message)
    {
        $this->setMessage($message);
        $messageData = json_decode($message->body);

        $this->getLogger()->info('Receiving message', (array)$messageData);

        try {
            $content = $this->getContent($this->browser, $messageData->url);
        } catch (\Exception $e) {
            $context = array(
                'url' => $messageData->url,
                'message' => $e->getMessage()
            );
            $this->getLogger()->error('Reading gitweb frontend failed', $context);
            $this->acknowledgeMessage($message);
            return;
        }

        $crawler = new Crawler($content);
        /* @var $crawler \Symfony\Component\DomCrawler\Crawler */

        $projectLinks = $crawler->filterXPath('//table[@class="project_list"]/tr/td[1]/a[@class="list"]');
        foreach ($projectLinks as $node) {
            $name = $node->nodeValue;

            $href = $node->getAttribute('href');
            $detailUrl = rtrim($messageData->url, '/') . $href;
            try {
                $content = $this->getContent($this->browser, $detailUrl);
            } catch (\Exception $e) {
                continue;
            }

            $detailCrawler = new Crawler($content);
            /* @var $detailCrawler \Symfony\Component\DomCrawler\Crawler */
            $gitUrl = $detailCrawler->filterXPath(
                '//table[@class="projects_list"]/tr[@class="metadata_url"]/td[2]'
            )->text();

            $gitwebRecord = $this->getGitwebFromDatabase($gitUrl);
            if ($gitwebRecord === false) {
                $id = $this->insertGitwebRecord($name, $gitUrl);
            } else {
                $id = $gitwebRecord['id'];
                $this->getLogger()->info('Gitweb record already exists', array('git' => $gitUrl));
            }

            $this->addFurtherMessageToQueue($messageData->project, $id);
        }

        $this->acknowledgeMessage($message);

        $this->getLogger()->info('Finish processing message', (array)$messageData);
    }

    /**
     * Returns the content of a web page
     *
     * @param \Buzz\Browser $browser
     * @param string $url
     * @return mixed
     * @throws \Exception
     */
    private function getContent($browser, $url)
    {
        $this->getLogger()->info('Requesting url', array('url' => $url));
        $response = $browser->get($url);
        /* @var $response \Buzz\Message\Response */

        if ($response->getStatusCode() !== 200) {
            $context = array(
                'url' => $url,
                'statusCode' => $response->getStatusCode()
            );
            $this->getLogger()->error('URL is not crawlable', $context);
            $exceptionMessage = sprintf('URL "%s" is not crawlable', $url);
            throw new \Exception($exceptionMessage, 1369417933);
        }

        return $response->getContent();
    }

    /**
     * Adds new messages to queue system to download the git repository
     *
     * @param string $project
     * @param integer $id
     * @return void
     */
    private function addFurtherMessageToQueue($project, $id)
    {
        $message = array(
            'project' => $project,
            'id' => $id
        );

        $this->getMessageQueue()->sendMessage($message, 'TYPO3', 'download.git', 'download.git');
    }

    /**
     * Receives a single gitweb record of the database
     *
     * @param string $repository
     * @return bool|array
     */
    private function getGitwebFromDatabase($repository)
    {
        $fields = array('id');
        $rows = $this->getDatabase()->getRecords($fields, 'gitweb', array('git' => $repository), '', '', 1);

        $row = false;
        if (count($rows) === 1) {
            $row = array_shift($rows);
            unset($rows);
        }

        return $row;
    }

    /**
     * Inserts a new gitweb record to database
     *
     * @param string $name
     * @param string $repository
     * @return string
     */
    private function insertGitwebRecord($name, $repository)
    {
        $data = array(
            'name' => $name,
            'git' => $repository
        );

        $this->getLogger()->info('Inserted new gitweb record', $data);
        return $this->getDatabase()->insertRecord('gitweb', $data);
    }
}
