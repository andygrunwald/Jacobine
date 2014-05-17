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
 * Class GerritProject
 *
 * TODO merge both Gerrit consumer to one. Adjust README as well!
 *
 * A consumer to execute Gerrie (https://github.com/andygrunwald/Gerrie).
 * Gerrie is a project written in PHP to crawl data from a Gerrit Code Review server.
 * The crawled data will be saved in a (configured) database.
 *
 * This consumer is part of "message chain".
 * This consumer is responsible to receive all changesets + dependencies of a single project from a Gerrit server
 * and store them into a database which will be configured in Gerries config file.
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
 *      serverId: Server id of Gerrit server stored in Gerries database. Returned by Gerrie::proceedServer()
 *      projectId: Project id of Gerrit server stored in Gerries database. Returned by Gerrie::importProject()
 *  ]
 *
 * Usage:
 *  php console analysis:consumer Crawler\\GerritProject
 *
 * @package Jacobine\Consumer\Crawler
 * @author Andy Grunwald <andygrunwald@gmail.com>
 */
class GerritProject extends ConsumerAbstract
{

    /**
     * Gets a description of the consumer
     *
     * @return string
     */
    public function getDescription()
    {
        return 'Imports a single project of a Gerrit review system';
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

        $this->setQueueOption('name', 'crawler.gerritproject');
        $this->enableDeadLettering();

        $this->setRouting('crawler.gerritproject');
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
            $context = array('file' => $message->configFile);
            $this->getLogger()->critical('Gerrit config file does not exist', $context);
            throw new \Exception('Gerrit config file does not exist', 1398886991);
        }

        $project = $message->project;

        // Bootstrap Gerrie
        $gerrieConfig = $this->initialGerrieConfig($message->configFile);
        $databaseConfig = $gerrieConfig->getConfigurationValue('Database');
        $projectConfig = $gerrieConfig->getConfigurationValue('Gerrit.' . $project);

        // TODO port Gerrie classes to use DIC
        $gerrieDatabase = new \Gerrie\Helper\Database($databaseConfig);
        $gerrieDataService = \Gerrie\Helper\Factory::getDataService($gerrieConfig, $project);

        $gerrie = new \Gerrie\Gerrie($gerrieDatabase, $gerrieDataService, $projectConfig);
        $gerrie->setOutput($this->getLogger());

        $gerritHost = $gerrieDataService->getHost();
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
