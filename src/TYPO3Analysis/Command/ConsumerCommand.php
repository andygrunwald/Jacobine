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

use Monolog\Logger;
use Monolog\Processor\MemoryPeakUsageProcessor;
use Monolog\Processor\MemoryUsageProcessor;
use Monolog\Processor\ProcessIdProcessor;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;
use TYPO3Analysis\Helper\AMQPFactory;
use TYPO3Analysis\Helper\Database;
use TYPO3Analysis\Helper\DatabaseFactory;
use TYPO3Analysis\Helper\MessageQueue;
use TYPO3Analysis\Monolog\Handler\SymfonyConsoleHandler;

/**
 * Class ConsumerCommand
 *
 * This command is to start a single consumer to receive messages from a message queue broker.
 * The message queue broker must support the AMQP standard.
 *
 * Every consumer can be started via this ConsumerCommand.
 * This class reflects the single entry point for every consumer.
 *
 * Usage:
 *  php console analysis:consumer ConsumerName [--project=ProjectName]
 *
 * e.g. to start the Download HTTP consumer
 *  php console analysis:consumer Download\\HTTP --project=TYPO3
 *
 * @package TYPO3Analysis\Command
 * @author Andy Grunwald <andygrunwald@gmail.com>
 */
class ConsumerCommand extends Command
{

    /**
     * MessageQueue connection
     *
     * @var \TYPO3Analysis\Helper\MessageQueue
     */
    protected $messageQueue;

    /**
     * Database connection
     *
     * @var \TYPO3Analysis\Helper\Database
     */
    protected $database;

    /**
     * Config
     *
     * @var array
     */
    protected $config = [];

    /**
     * Project
     *
     * @var string
     */
    protected $project;

    /**
     * Configures the current command.
     *
     * @return void
     */
    protected function configure()
    {
        $this->setName('analysis:consumer')
             ->setDescription('Generic task for message queue consumer')
             ->addOption(
                 'project',
                 null,
                 InputOption::VALUE_OPTIONAL,
                 'Choose the project (for configuration, etc.).',
                 'TYPO3'
             )
             ->addArgument('consumer', InputArgument::REQUIRED, 'Part namespace of consumer');
    }

    /**
     * Returns the current project
     *
     * @return string
     */
    protected function getProject()
    {
        return $this->project;
    }

    /**
     * Sets the current project
     *
     * @param string $project
     * @return void
     */
    protected function setProject($project)
    {
        $this->project = $project;
    }

    /**
     * Initializes the command just after the input has been validated.
     *
     * Sets up the project, config, database and message queue
     *
     * @param InputInterface $input An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     * @return void
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->setProject($input->getOption('project'));
        $this->config = Yaml::parse(CONFIG_FILE);

        $this->database = $this->initializeDatabase($this->config);
        $this->messageQueue = $this->initializeMessageQueue($this->config);
    }

    /**
     * Initialize the database connection
     *
     * @param array $parsedConfig
     * @return \TYPO3Analysis\Helper\Database
     * @throws \Exception
     */
    private function initializeDatabase($parsedConfig)
    {
        if (array_key_exists($this->getProject(), $parsedConfig['Projects']) === false) {
            throw new \Exception('Configuration for project "' . $this->getProject() . '" does not exist', 1368101351);
        }

        $config = $parsedConfig['MySQL'];
        $projectConfig = $parsedConfig['Projects'][$this->getProject()];

        $databaseFactory = new DatabaseFactory();
        // TODO Refactor this to use a config entity or an array
        $database = new Database($databaseFactory, $config['Host'], $config['Port'], $config['Username'], $config['Password'], $projectConfig['MySQL']['Database']);

        return $database;
    }

    /**
     * Initialize the message queue object
     *
     * @param array $parsedConfig
     * @return \TYPO3Analysis\Helper\MessageQueue
     */
    private function initializeMessageQueue($parsedConfig)
    {
        $config = $parsedConfig['RabbitMQ'];

        $amqpFactory = new AMQPFactory();
        $amqpConnection = $amqpFactory->createConnection($config['Host'], $config['Port'], $config['Username'], $config['Password'], $config['VHost']);
        $messageQueue = new MessageQueue($amqpConnection, $amqpFactory);

        return $messageQueue;
    }

    /**
     * Initialize the configured logger for consumer
     *
     * @param array $parsedConfig
     * @param string $consumer
     * @param OutputInterface $output
     * @return \Monolog\Logger
     */
    private function initializeLogger($parsedConfig, $consumer, OutputInterface $output)
    {
        $loggerConfig = null;
        if (array_key_exists('Logger', $parsedConfig['Logging']['Consumer']) === true) {
            $loggerConfig = $parsedConfig['Logging']['Consumer'];
        }

        // Create a channel name ...
        // e.g. Download\\HTTP to download.http
        $channelName = str_replace('\\', '.', $consumer);
        $channelName = strtolower($channelName);
        $logger = new Logger($channelName);

        // If there are no configured logger, add a NullHandler and exit
        if ($loggerConfig === null || is_array($loggerConfig['Logger']) === false) {
            $logger->pushHandler(new \Monolog\Handler\NullHandler());
            return $logger;
        }

        // Add global logProcessors
        $logger->pushProcessor(new ProcessIdProcessor());
        $logger->pushProcessor(new MemoryUsageProcessor());
        $logger->pushProcessor(new MemoryPeakUsageProcessor());

        foreach ($loggerConfig['Logger'] as $loggerName => $singleLoggerConfig) {
            $logFileName = $channelName . '-' . strtolower($loggerName);
            $loggerInstance = $this->getLoggerInstance(
                $singleLoggerConfig['Class'],
                $singleLoggerConfig,
                $logFileName,
                $output
            );
            $logger->pushHandler($loggerInstance);
        }

        return $logger;
    }

    /**
     * Creates a logger from config
     *
     * @param string $loggerClass
     * @param array $loggerConfig
     * @param string $logFileName
     * @param OutputInterface $output
     * @return \Monolog\Handler\StreamHandler|SymfonyConsoleHandler
     * @throws \Exception
     */
    private function getLoggerInstance($loggerClass, $loggerConfig, $logFileName, OutputInterface $output)
    {
        switch ($loggerClass) {

            // Monolog StreamHandler
            case 'StreamHandler':
                $stream = rtrim($loggerConfig['Path'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
                $stream .= $logFileName . '.log';

                // Determine LogLevel
                $minLogLevel = Logger::DEBUG;
                $configuredLogLevel = (array_key_exists(
                    'MinLogLevel',
                    $loggerConfig
                )) ? $loggerConfig['MinLogLevel'] : null;
                $configuredLogLevel = strtoupper($configuredLogLevel);
                if ($configuredLogLevel && constant('Monolog\Logger::' . $configuredLogLevel)) {
                    $minLogLevel = constant('Monolog\Logger::' . $configuredLogLevel);
                }

                $instance = new \Monolog\Handler\StreamHandler($stream, $minLogLevel);
                break;

            // Custom SymfonyConsoleHandler
            case 'SymfonyConsoleHandler':
                $instance = new \TYPO3Analysis\Monolog\Handler\SymfonyConsoleHandler($output);
                break;

            // If there is another handler, skip it :(
            default:
                throw new \Exception('Configured logger "' . $loggerClass . '" not supported yet', 1368216223);
        }

        return $instance;
    }

    /**
     * Executes the current command.
     *
     * Initialize and starts a single consumer.
     *
     * @param InputInterface $input An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     * @return null|integer null or 0 if everything went fine, or an error code
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $consumerIdent = $input->getArgument('consumer');
        $consumerToGet = '\\TYPO3Analysis\\Consumer\\' . $consumerIdent;

        // If the consumer does not exists exit here
        if (class_exists($consumerToGet) === false) {
            throw new \Exception('A consumer like "' . $consumerIdent . '" does not exist', 1368100583);
        }

        $logger = $this->initializeLogger($this->config, $consumerIdent, $output);

        // Create, initialize and start consumer
        $consumer = new $consumerToGet();
        /* @var \TYPO3Analysis\Consumer\ConsumerAbstract $consumer */
        $consumer->setConfig($this->config);
        $consumer->setDatabase($this->database);
        $consumer->setMessageQueue($this->messageQueue);
        $consumer->setLogger($logger);
        $consumer->initialize();

        $projectConfig = $this->config['Projects'][$this->getProject()];
        $consumer->setExchangeOption('name', $projectConfig['RabbitMQ']['Exchange']);

        $consumerIdent = str_replace('\\', '\\\\', $consumerIdent);
        $logger->info('Consumer starts', array('consumer' => $consumerIdent));

        // Register consumer at message queue
        $callback = array($consumer, 'process');
        $this->messageQueue->basicConsume(
            $consumer->getExchangeOptions(),
            $consumer->getQueueOptions(),
            $consumer->isDeadLetteringEnabled(),
            $consumer->getRouting(),
            $consumer->getConsumerTag(),
            $callback
        );

        return null;
    }
}
