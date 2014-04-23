<?php
/**
 * This file is part of the Jacobine package.
 *
 * (c) Andreas Grunwald <andygrunwald@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TYPO3Analysis\Consumer\Crawler;

use TYPO3Analysis\Consumer\ConsumerAbstract;

/**
 * Class Gerrit
 *
 * A consumer to execute Gerrie (https://github.com/andygrunwald/Gerrie).
 * Gerrie is a project written in PHP to crawl data from a Gerrit Code Review server.
 * The crawled data will be saved in a (configured) database.
 *
 * This consumer is part of "message chain".
 * This consumer is responsible to receive all projects from a Gerrit server and create
 * a seperate message for each project.
 * The chain is:
 *
 * GerritCommand
 *      |-> Consumer: Crawler\\Gerrit
 *              |-> Consumer: Crawler\\GerritProject
 *
 * Message format (json encoded):
 *  [
 *      configFile: Absolute path to a Gerrie config file which will be used. E.g. /var/www/my/Gerrie/config
 *      project: Project to be analyzed. Must be a configured project in "configFile"
 *  ]
 *
 * Usage:
 *  php console analysis:consumer Crawler\\Gerrit
 *
 * @package TYPO3Analysis\Consumer\Crawler
 * @author Andy Grunwald <andygrunwald@gmail.com>
 */
class Gerrit extends ConsumerAbstract
{

    /**
     * Gets a description of the consumer
     *
     * @return string
     */
    public function getDescription()
    {
        return 'Prepares the message queues for a single Gerrit review system';
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

        $this->setQueueOption('name', 'crawler.gerrit');
        $this->enableDeadLettering();

        $this->setRouting('crawler.gerrit');
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

        $this->getLogger()->info('Receiving message', (array) $messageData);

        if (file_exists($messageData->configFile) === false) {
            $context = array('file' => $messageData->configFile);
            $this->getLogger()->critical('Gerrit config file does not exist', $context);
            $this->rejectMessage($message);
            return;
        }

        $project = $messageData->project;

        // Bootstrap Gerrie
        $gerrieConfig = $this->initialGerrieConfig($messageData->configFile);
        $databaseConfig = $gerrieConfig->getConfigurationValue('Database');
        $projectConfig = $gerrieConfig->getConfigurationValue('Gerrit.' . $project);

        $gerrieDatabase = new \Gerrie\Helper\Database($databaseConfig);
        $gerrieDataService = \Gerrie\Helper\Factory::getDataService($gerrieConfig, $project);

        $gerrie = new \Gerrie\Gerrie($gerrieDatabase, $gerrieDataService, $projectConfig);
        $gerritHost = $gerrieDataService->getHost();
        $gerritServerId = $gerrie->proceedServer($project, $gerritHost);

        $this->getLogger()->info('Requesting projects', array('host' => $gerritHost));

        $projects = $gerrieDataService->getProjects();

        if ($projects === null) {
            $this->getLogger()->info('No projects available');
            $this->acknowledgeMessage($message);
            return;
        }

        $parentMapping = array();
        foreach ($projects as $name => $info) {
            $projectId = $gerrie->importProject($name, $info, $parentMapping);

            $context = array(
                'projectName' => $name,
                'projectId' => $projectId
            );
            $this->getLogger()->info('Add project to message queue "crawler"', $context);
            $this->addFurtherMessageToQueue($project, $gerritServerId, $projectId, $messageData->configFile);
        }

        $this->getLogger()->info('Set correct project parent child relation');

        $gerrie->proceedProjectParentChildRelations($parentMapping);

        $this->acknowledgeMessage($message);

        $this->getLogger()->info('Finish processing message', (array)$messageData);
    }

    /**
     * Adds new messages to queue system to import a single gerrit project
     *
     * @param string $project
     * @param integer $serverId
     * @param integer $projectId
     * @param string $configFile
     * @return void
     */
    private function addFurtherMessageToQueue($project, $serverId, $projectId, $configFile)
    {
        $message = array(
            'project' => $project,
            'projectId' => $projectId,
            'serverId' => $serverId,
            'configFile' => $configFile
        );

        $this->getMessageQueue()->sendSimpleMessage($message, 'TYPO3', 'crawler.gerritproject');
    }

    /**
     * Initialize the Gerrit configuration
     *
     * @param string $configFile
     * @return \Gerrie\Helper\Configuration
     */
    protected function initialGerrieConfig($configFile)
    {
        $gerrieConfig = new \Gerrie\Helper\Configuration($configFile);
        return $gerrieConfig;
    }
}
