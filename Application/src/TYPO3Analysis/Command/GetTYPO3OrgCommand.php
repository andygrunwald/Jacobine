<?php
/**
 * @todo adds a description (license text, description of this class / file, etc)
 */
namespace TYPO3Analysis\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;
use TYPO3Analysis\Helper\Database;
use TYPO3Analysis\Helper\MessageQueue;

class GetTYPO3OrgCommand extends Command {

    /**
     * JSON File with all information we need
     *
     * @string
     */
    const JSON_FILE_URL = 'http://get.typo3.org/json';

    /**
     * Message Queue Queue
     *
     * @string
     */
    const QUEUE = 'download.http';

    /**
     * Message Queue routing
     *
     * @string
     */
    const ROUTING = 'download.http';

    /**
     * Config
     *
     * @var array
     */
    protected $config = array();

    /**
     * HTTP Client
     *
     * @var \Buzz\Browser
     */
    protected $browser = null;

    /**
     * Database connection
     *
     * @var \TYPO3Analysis\Helper\Database
     */
    protected $database = null;

    /**
     * MessageQueue connection
     *
     * @var \TYPO3Analysis\Helper\MessageQueue
     */
    protected $messageQueue = null;

    protected function configure() {
        $this->setName('typo3:get.typo3.org')
             ->setDescription('Queues tasks for TYPO3 CMS releases');
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->config = Yaml::parse(CONFIG_FILE);

        $curlClient = new \Buzz\Client\Curl();
        $this->browser = new \Buzz\Browser($curlClient);

        $config = $this->config['MySQL'];
        $this->database = new Database($config['Host'], $config['Port'], $config['Username'], $config['Password'], $config['Databases']['typo3']);

        $config = $this->config['RabbitMQ'];
        $this->messageQueue = new MessageQueue($config['Host'], $config['Port'], $config['Username'], $config['Password'], $config['VHost']);
    }

    protected function execute(InputInterface $input, OutputInterface $output) {

        $versions = $this->getReleaseInformation();
        foreach ($versions as $branch => $data) {
            // $data got two keys: releases + latest

            // Sometimes, the releases key does not exists.
            // This is the case where $branch is latest_stable, latest_lts or latest_deprecated
            // We can skip this here
            // @todo update database with this information!
            if (is_array($data['releases']) === false) {
                continue;
            }

            foreach ($data['releases'] as $releaseVersion => $releaseData) {
                // If the current version already in database, skip it
                $versionRecord = $this->getVersionFromDatabase($releaseVersion);

                // If the current version is not in database already, create it
                if ($versionRecord === false) {
                    $versionRecord = $this->insertVersionIntoDatabase($branch, $releaseData);
                }

                // If the current version is not downloaded yet, queue it
                if (!$versionRecord['downloaded']) {
                    $message = $versionRecord['id'];
                    $this->messageQueue->sendMessage($message, 'TYPO3', self::QUEUE, self::ROUTING);
                }
            }
        }

        return true;
    }

    /**
     * Stores a single version of TYPO3 into the database table 'versions'
     *
     * @param string    $branch         Branch version like 4.7, 6.0, 6.1, ...
     * @param array     $versionData    Data about the current version provided by the json file
     * @return array
     */
    private function insertVersionIntoDatabase($branch, $versionData) {
        $data = array(
            'branch' => $branch,
            'version' => $versionData['version'],
            'date' => $versionData['date'],
            'type' => $versionData['type'],
            'checksum_tar_md5' => $versionData['checksums']['tar']['md5'],
            'checksum_tar_sha1' => $versionData['checksums']['tar']['sha1'],
            'checksum_zip_md5' => $versionData['checksums']['zip']['md5'],
            'checksum_zip_sha1' => $versionData['checksums']['zip']['sha1'],
            'url_tar' => $versionData['url']['tar'],
            'url_zip' => $versionData['url']['zip'],
            'downloaded' => 0
        );
        $data['id'] = $this->database->insertRecord('versions', $data);
        return $data;
    }

    /**
     * Receives a single version from the database table 'versions' (if exists).
     *
     * @param string    $version    A version like 4.5.7, 6.0.4, ...
     * @return bool|array
     */
    private function getVersionFromDatabase($version) {
        $rows = $this->database->getRecords(array('id', 'downloaded'), 'versions', array('version' => $version), '', '', 1);

        $row = false;
        if (count($rows) === 1) {
            $row = array_shift($rows);
            unset($rows);
        }

        return $row;
    }

    /**
     * Downloads the json file about the versions of TYPO3
     *
     * @return array
     */
    private function getReleaseInformation() {
        $response = $this->browser->get(static::JSON_FILE_URL);
        if ($response->isOk() !== true) {
            return false;
        }

        $jsonContent = $response->getContent();
        return json_decode($jsonContent, true);
    }
}