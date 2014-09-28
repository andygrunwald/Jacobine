<?php
/**
 * This file is part of the Jacobine package.
 *
 * (c) Andreas Grunwald <andygrunwald@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Jacobine\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;

/**
 * Class GetTYPO3OrgCommand
 *
 * Command to get the JSON stream from http://get.typo3.org/.
 * This stream contains information about releases of the TYPO3 CMS
 * e.g. Name of release, version number, status (development, beta, stable), download url, ...
 *
 * This commands parses the JSON information, adds the various versions to the database
 * and sends one message per release to the message broker to download it :)
 *
 * TODO: Build this a little bit more flexible
 *       Currently this is only for TYPO3-Releases, but it would make sense to do this with other
 *       Software as well. Drupal, Wikimedia, etc.
 *
 * Usage:
 *  php console typo3:get.typo3.org
 *
 * @package Jacobine\Command
 * @author Andy Grunwald <andygrunwald@gmail.com>
 */
class GetTYPO3OrgCommand extends Command implements ContainerAwareInterface
{

    use ContainerAwareTrait;

    /**
     * JSON File with all information we need
     *
     * @var string
     */
    const JSON_FILE_URL = 'http://get.typo3.org/json';

    /**
     * Message Queue routing
     *
     * @var string
     */
    const ROUTING = 'download.http';

    /**
     * Project identifier
     *
     * @var string
     */
    const PROJECT = 'TYPO3';

    /**
     * HTTP Client
     *
     * @var \Buzz\Browser
     */
    protected $remoteService;

    /**
     * Database connection
     *
     * @var \Jacobine\Component\Database\Database
     */
    protected $database;

    /**
     * MessageQueue connection
     *
     * @var \Jacobine\Component\AMQP\MessageQueue
     */
    protected $messageQueue;

    /**
     * Project service
     *
     * @var \Jacobine\Service\Project
     */
    protected $projectService;

    /**
     * Configures the current command.
     *
     * @return void
     */
    protected function configure()
    {
        $this->setName('typo3:get.typo3.org')
             ->setDescription('Queues tasks for TYPO3 CMS releases');
    }

    /**
     * Initializes the command just after the input has been validated.
     *
     * Sets up the HTTP client, database and message queue
     *
     * @param InputInterface $input An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     * @return void
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->remoteService = $this->container->get('component.remoteService.httpRemoteService');

        $this->database = $this->container->get('component.database.database');
        $this->messageQueue = $this->container->get('component.amqp.messageQueue');
        $this->projectService = $this->container->get('service.project');
    }

    /**
     * Executes the current command.
     *
     * Reads all versions from get.typo3.org/json, store them into a database
     * and add new messages to message queue to download this versions.
     *
     * @param InputInterface $input An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     * @return null|integer null or 0 if everything went fine, or an error code
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $exchange = $this->container->getParameter('messagequeue.exchange');
        $projectRecord = $this->projectService->getProjectByName(self::PROJECT);

        // If there is no TYPO3 project configured, exit here
        if (count($projectRecord) == 0) {
            $this->outputNoProjectMessage($output);
            return null;
        }

        $versions = $this->getReleaseInformation();
        foreach ($versions as $branch => $data) {
            // $data got two keys: releases + latest
            // Sometimes, the releases key does not exists.
            // This is the case where $branch contains one of this values:
            // "latest_stable", "latest_old_stable", "latest_lts", "latest_old_lts" or "latest_deprecated"
            // and $data reflects the version number e.g. 6.2.2 or 4.7.18
            // Some examples:
            //  $branch: latest_stable - $data: 6.2.2
            //  $branch: latest_old_stable - $data: 6.1.8
            //  ...
            // We do not need this information (currently), so we can skip this here
            if (is_array($data) === false
                || array_key_exists('releases', $data) === false
                || is_array($data['releases']) === false
            ) {
                continue;
            }

            foreach ($data['releases'] as $releaseVersion => $releaseData) {

                // Temp. fix for http://forge.typo3.org/issues/49337
                if (strpos($releaseData['url']['tar'], 'snapshot')) {
                    continue;
                }

                // Try to get the current version from the database
                $versionRecord = $this->getVersionFromDatabase($releaseVersion);

                // If the current version is not in database already, create it
                if ($versionRecord === false) {
                    $versionRecord = $this->insertVersionIntoDatabase($branch, $releaseData);
                }

                // If the current version is not downloaded yet, queue it
                if (!$versionRecord['downloaded']) {
                    $message = [
                        'project' => $projectRecord['projectId'],
                        'versionId' => $versionRecord['id'],
                        'filenamePrefix' => 'typo3_',
                        'filenamePostfix' => '.tar.gz',
                    ];

                    $this->messageQueue->sendSimpleMessage($message, $exchange, self::ROUTING);
                }
            }
        }

        return null;
    }

    /**
     * Outputs a help message if there is no configured TYPO3 project.
     *
     * @param OutputInterface $output
     * @return void
     */
    private function outputNoProjectMessage(OutputInterface $output) {
        $messages = [
            'Hey, cool! You want to download all TYPO3 releases.',
            'And of course Jacobine will offer you this service :)',
            '',
            'But we had seen that you do not created a project named "TYPO3".',
            'Please create this project first. Just execute:',
            "\t$ ./console jacobine:create-project",
            '',
            'If you had done this, start all consumers and execute',
            "\t$ ./console jacobine:create-project",
            '',
            'I hope this will work now for you.',
            'Otherwise please get in contact with us.',
            'Have fun and happy downloading!',
        ];

        foreach ($messages as $message) {
            $messageToWrite = sprintf('<%s>%s</%s>', 'comment', $message, 'comment');
            $output->writeln($messageToWrite);
        }
    }

    /**
     * Stores a single version of TYPO3 into the database table 'versions'
     *
     * @param string $branch Branch version like 4.7, 6.0, 6.1, ...
     * @param array $versionData Data about the current version provided by the json file
     * @return array
     */
    private function insertVersionIntoDatabase($branch, $versionData)
    {
        $data = [
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
        ];
        $data['id'] = $this->database->insertRecord('jacobine_versions', $data);
        return $data;
    }

    /**
     * Receives a single version from the database table 'versions' (if exists).
     *
     * @param string $version A version like 4.5.7, 6.0.4, ...
     * @return bool|array
     */
    private function getVersionFromDatabase($version)
    {
        $rows = $this->database->getRecords(
            ['id', 'downloaded'],
            'jacobine_versions',
            ['version' => $version],
            '',
            '',
            1
        );

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
    private function getReleaseInformation()
    {
        $response = $this->remoteService->get(static::JSON_FILE_URL);
        /** @var \Buzz\Message\Response $response */
        if ($response->isOk() !== true) {
            return false;
        }

        $jsonContent = $response->getContent();
        return json_decode($jsonContent, true);
    }
}
