<?php
/**
 * This file is part of the TYPO3-Analytics package.
 *
 * (c) Andreas Grunwald <andygrunwald@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TYPO3Analysis\Command;

use Gerrie\Gerrie;
use Gerrie\Helper\Configuration;
use Gerrie\Helper\Factory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;
use TYPO3Analysis\Helper\AMQPFactory;
use TYPO3Analysis\Helper\Database;
use TYPO3Analysis\Helper\DatabaseFactory;
use TYPO3Analysis\Helper\MessageQueue;

class ReviewTYPO3OrgCommand extends Command
{

    /**
     * JSON File with all information we need
     *
     * @var string
     */
    const CONFIG_FILE = 'gerrit-review.typo3.org.yml';

    /**
     * Message Queue Queue
     *
     * @var string
     */
    const QUEUE = 'import.gerritproject';

    /**
     * Message Queue routing
     *
     * @var string
     */
    const ROUTING = 'import.gerritproject';

    /**
     * Config
     *
     * @var array
     */
    protected $config = array();

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

    /**
     * Configures the current command.
     *
     * @return void
     */
    protected function configure()
    {
        $this->setName('typo3:review.typo3.org')
            ->setDescription('Queues tasks to import projects of review.typo3.org');
    }

    /**
     * Initializes the command just after the input has been validated.
     *
     * Sets up the config, database and message queue
     *
     * @param InputInterface $input An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     * @return void
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->config = Yaml::parse(CONFIG_FILE);

        $config = $this->config['MySQL'];
        $projectConfig = $this->config['Projects']['TYPO3'];

        $databaseFactory = new DatabaseFactory();
        // TODO Refactor this to use a config entity or an array
        $this->database = new Database($databaseFactory, $config['Host'], $config['Port'], $config['Username'], $config['Password'], $projectConfig['MySQL']['Database']);

        $config = $this->config['RabbitMQ'];

        $amqpFactory = new AMQPFactory();
        $amqpConnection = $amqpFactory->createConnection($config['Host'], $config['Port'], $config['Username'], $config['Password'], $config['VHost']);
        $this->messageQueue = new MessageQueue($amqpConnection, $amqpFactory);
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
        // TODO Take care of this! Does this class might sense? Or is this just old code?
        // Maybe it was replaces by GerritCommand? Step in deeper!
        $message = __CLASS__ . '->' . __METHOD__ . ':';
        $message .= 'Deactivated! This class has to be refactored and make it working again.';
        $message .= 'Sorry for this. Please come back later.'.
        $message = '<error>' . $message . '</error>';
        $output->writeln($message);
        die(99);

        $project = 'TYPO3';

        // Bootstrap Gerrie
        $gerrieConfig = $this->initialGerrieConfig(dirname(CONFIG_FILE));
        $databaseConfig = $gerrieConfig->getConfigurationValue('Database');
        $projectConfig = $gerrieConfig->getConfigurationValue('Gerrit.' . $project);

        $gerrieDatabase = new \Gerrie\Helper\Database($databaseConfig);
        $gerrieDataService = Factory::getDataService($gerrieConfig, $project);

        $gerrie = new Gerrie($gerrieDatabase, $gerrieDataService, $projectConfig);
        $gerrie->proceedServer($project, $gerrieDataService->getHost());

        $projects = $gerrieDataService->getProjects();

        if ($projects === null) {
            return;
        }

        $parentMapping = array();
        foreach ($projects as $name => $info) {
            //$projectId = $gerrie->importProject($name, $info, &$parentMapping);
            //var_dump($projects);
            //die();

            // Import / update single project via Gerrie
            // Return the insert id
            // Add a new RabbitMQ message to import single project
        }

        $gerrie->proceedProjectParentChildRelations($parentMapping);

        return null;
    }

    protected function initialGerrieConfig($configDir)
    {
        $configFile = rtrim($configDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $configFile .= static::CONFIG_FILE;

        $gerrieConfig = new Configuration($configFile);
        return $gerrieConfig;
    }
}
