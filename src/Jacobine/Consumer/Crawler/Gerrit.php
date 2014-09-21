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

/**
 * Class Gerrit
 *
 * A consumer to execute Gerrie (https://github.com/andygrunwald/Gerrie).
 * Gerrie is a project written in PHP to crawl data from a Gerrit Code Review server.
 * The crawled data will be saved in a (configured) database.
 *
 * This consumer is part of "message chain".
 * This consumer got two tasks:
 *  * Receive all projects from a Gerrit server and create a seperate message for each project
 *  * Receive all changesets + dependencies of a single project from a Gerrit server
 *    and store them into a database which will be configured in Gerries config file
 * The chain is:
 *
 * GerritCommand
 *      |-> Consumer: Crawler\\Gerrit (type: server)
 *           |-> Consumer: Crawler\\Gerrit (type: project)
 *
 * Message format (json encoded):
 *  [
 *      configFile: Absolute path to a Gerrie config file which will be used. E.g. /var/www/my/Gerrie/config
 *      project: Project to be analyzed. Must be a configured project in "configFile"
 *      serverId: Server id of Gerrit server stored in Gerries database. Returned by Gerrie::proceedServer()
 *      projectId: Project id of Gerrit server stored in Gerries database. Returned by Gerrie::importProject()
 *      type: "server" to crawl a server or "project" to crawl a project
 *  ]
 *
 * Usage:
 *  php console jacobine:consumer Crawler\\Gerrit
 *
 * @package Jacobine\Consumer\Crawler
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
     * @throws \Exception
     * @return null|void
     */
    protected function process($message)
    {
        if (file_exists($message->configFile) === false) {
            $context = ['file' => $message->configFile];
            $this->getLogger()->critical('Gerrit config file does not exist', $context);
            throw new \Exception('Gerrit config file does not exist', 1398886834);
        }

        // Bootstrap Gerrie
        $gerrieConfig = $this->initialGerrieConfig($message->configFile);
        $databaseConfig = $gerrieConfig->getConfigurationValue('Database');
        $projectConfig = $gerrieConfig->getConfigurationValue('Gerrit.' . $message->project);

        // TODO get Gerrie classes via DIC
        $gerrieDatabase = new \Gerrie\Helper\Database($databaseConfig);
        $gerrieDataService = \Gerrie\Helper\Factory::getDataService($gerrieConfig, $message->project);

        $gerrie = new \Gerrie\Gerrie($gerrieDatabase, $gerrieDataService, $projectConfig);
        $gerrie->setOutput($this->getLogger());

        $gerritHost = $gerrieDataService->getHost();

        switch ($message->type) {
            case 'server':
                $this->processServer($message, $gerrie, $gerritHost, $gerrieDataService);
                break;
            case 'project':
                $this->processProject($message, $gerrie, $gerritHost);
                break;
        }

    }

    /**
     * Imports all changesets + dependencies of a single Gerrit project
     *
     * @param \stdClas  $message
     * @param \Gerrie\Gerrie $gerrie
     * @param string $gerritHost
     * @throws \Exception
     * @return void
     */
    private function processProject($message, \Gerrie\Gerrie $gerrie, $gerritHost)
    {
        $gerritProject = $gerrie->getGerritProjectById($message->serverId, $message->projectId);

        $context = [
            'serverId' => $message->serverId,
            'projectId' => $message->projectId
        ];
        if ($gerritProject === false) {
            $this->getLogger()->critical('Gerrit project does not exists in database', $context);
            throw new \Exception('Gerrit project does not exists in database', 1398887300);
        }

        $this->getLogger()->info('Start importing of changesets for Gerrit project', $context);
        $gerrie->proceedChangesetsOfProject($gerritHost, $gerritProject);
        $this->getLogger()->info('Import of changesets for Gerrit project successful', $context);
    }

    /**
     * Imports all projects of a single Gerrit review server and creates new messages to crawl those projects
     *
     * @param \stdClass $message
     * @param \Gerrie\Gerrie $gerrie
     * @param string $gerritHost
     * @param $gerrieDataService
     */
    private function processServer($message, \Gerrie\Gerrie $gerrie, $gerritHost, $gerrieDataService)
    {
        $project = $message->project;
        $gerritServerId = $gerrie->proceedServer($project, $gerritHost);

        $this->getLogger()->info('Requesting projects', ['host' => $gerritHost]);

        $projects = $gerrieDataService->getProjects();

        if ($projects === null) {
            $this->getLogger()->info('No projects available');
            return;
        }

        $parentMapping = [];
        foreach ($projects as $name => $info) {
            $projectId = $gerrie->importProject($name, $info, $parentMapping);

            $context = [
                'projectName' => $name,
                'projectId' => $projectId
            ];
            $this->getLogger()->info('Add project to message queue "crawler"', $context);
            $this->addFurtherMessageToQueue($project, $gerritServerId, $projectId, $message->configFile);
        }

        $this->getLogger()->info('Set correct project parent child relation');
        $gerrie->proceedProjectParentChildRelations($parentMapping);
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
        $config = $this->getConfig();
        $projectConfig = $config['Projects'][$project];

        $message = [
            'project' => $project,
            'projectId' => $projectId,
            'serverId' => $serverId,
            'configFile' => $configFile,
            'type' => 'project'
        ];

        $exchange = $projectConfig['RabbitMQ']['Exchange'];
        $this->getMessageQueue()->sendSimpleMessage($message, $exchange, 'crawler.gerrit');
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
