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
        return 'Prepares the message queues for a single gerrit review system';
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
     * @throws \Exception
     */
    public function process($message) {
        $messageData = json_decode($message->body);

        if (file_exists($messageData->configFile) === false) {
            $context = array('file' => $messageData->configFile);
            $this->getLogger()->critical('Gerrit config file does not exist', $context);

            $msg = 'Gerrit config file "%s" does not exist.';
            $msg = sprintf($msg, $messageData->configFile);
            throw new \Exception($msg, 1369437363);
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
        $gerrie->proceedServer($project, $gerritHost);

        $this->getLogger()->info('Requesting projects', array('host' => $gerritHost));

        $projects = $gerrieDataService->getProjects();

        if ($projects === null) {
            $this->getLogger()->info('No projects available');
            return;
        }

        $parentMapping = array();
        foreach($projects as $name => $info) {
            $projectId = $gerrie->importProject($name, $info, &$parentMapping);
            var_dump($projects);
            die();

            // Import / update single project via Gerrie
            // Return the insert id
            // Add a new RabbitMQ message to import single project
        }

        $gerrie->proceedProjectParentChildRelations($parentMapping);

        $this->acknowledgeMessage($message);

        return null;
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