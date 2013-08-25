<?php
/**
 * @todo adds a description (license text, description of this class / file, etc)
 */
namespace TYPO3Analysis\Consumer\Crawler;

use TYPO3Analysis\Consumer\ConsumerAbstract;

class Gerrit extends ConsumerAbstract {

    /**
     * Gets a description of the consumer
     *
     * @return string
     */
    public function getDescription() {
        return 'Prepares the message queues for a single Gerrit review system';
    }

    /**
     * Initialize the consumer.
     * Sets the queue and routing key
     *
     * @return void
     */
    public function initialize() {
        $this->setQueue('crawler.gerrit');
        $this->setRouting('crawler.gerrit');
    }

    /**
     * The logic of the consumer
     *
     * @param \stdClass $message
     * @return null|void
     */
    public function process($message) {
        $this->setMessage($message);
        $messageData = json_decode($message->body);

        if (file_exists($messageData->configFile) === false) {
            $context = array('file' => $messageData->configFile);
            $this->getLogger()->critical('Gerrit config file does not exist', $context);
            $this->acknowledgeMessage($message);
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
            return;
        }

        $parentMapping = array();
        foreach($projects as $name => $info) {
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

        return null;
    }

    /**
     * Adds new messages to queue system to import a single gerrit project
     *
     * @param string    $project
     * @param integer   $serverId
     * @param integer   $projectId
     * @param string    $configFile
     * @return void
     */
    private function addFurtherMessageToQueue($project, $serverId, $projectId, $configFile) {
        $message = array(
            'project' => $project,
            'projectId' => $projectId,
            'serverId' => $serverId,
            'configFile' => $configFile
        );

        $this->getMessageQueue()->sendMessage($message, 'TYPO3', 'crawler.gerritproject', 'crawler.gerritproject');
    }

    /**
     * Initialize the Gerrit configuration
     *
     * @param string    $configFile
     * @return \Gerrie\Helper\Configuration
     */
    protected function initialGerrieConfig($configFile) {
        $gerrieConfig = new \Gerrie\Helper\Configuration($configFile);
        return $gerrieConfig;
    }
}