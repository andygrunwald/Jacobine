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
use Monolog\Logger;
use Monolog\Processor\ProcessIdProcessor;
use Monolog\Processor\MemoryUsageProcessor;
use Monolog\Processor\MemoryPeakUsageProcessor;
use TYPO3Analysis\Helper\Database;
use TYPO3Analysis\Helper\MessageQueue;
use TYPO3Analysis\Monolog\Handler\SymfonyConsoleHandler;

class ConsumerCommand extends Command {

    /**
     * MessageQueue connection
     *
     * @var \TYPO3Analysis\Helper\MessageQueue
     */
    protected $messageQueue = null;

    /**
     * Database connection
     *
     * @var \TYPO3Analysis\Helper\Database
     */
    protected $database = null;

    /**
     * Config
     *
     * @var array
     */
    protected $config = array();

    /**
     * Project
     *
     * @var string
     */
    protected $project = null;

    protected function configure() {
        $this->setName('analysis:consumer')
             ->setDescription('Generic task for message queue consumer')
             ->addOption('project', null, InputOption::VALUE_OPTIONAL, 'Chose the project (for configuration, etc.).', 'TYPO3')
             ->addArgument('consumer', InputArgument::REQUIRED, 'Part namespace of consumer');
    }

    /**
     * Returns the current project
     *
     * @return string
     */
    protected function getProject() {
        return $this->project;
    }

    /**
     * Sets the current project
     *
     * @param $project
     * @return void
     */
    protected function setProject($project) {
        $this->project = $project;
    }

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
    private function initializeDatabase($parsedConfig) {
        if (array_key_exists($this->getProject(), $parsedConfig['Projects']) === false) {
            throw new \Exception('Configuration for project "' . $this->getProject() . '" does not exist', 1368101351);
        }

        $config = $parsedConfig['MySQL'];

        $projectConfig = $parsedConfig['Projects'][$this->getProject()];
        $database = new Database($config['Host'], $config['Port'], $config['Username'], $config['Password'], $projectConfig['MySQL']['Database']);

        return $database;
    }

    /**
     * Initialize the message queue object
     *
     * @param array $parsedConfig
     * @return \TYPO3Analysis\Helper\MessageQueue
     */
    private function initializeMessageQueue($parsedConfig) {
        $config = $parsedConfig['RabbitMQ'];
        $messageQueue = new MessageQueue($config['Host'], $config['Port'], $config['Username'], $config['Password'], $config['VHost']);

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
    private function initializeLogger($parsedConfig, $consumer, OutputInterface $output) {
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

        $logger->pushProcessor(new ProcessIdProcessor());
        $logger->pushProcessor(new MemoryUsageProcessor());
        $logger->pushProcessor(new MemoryPeakUsageProcessor());

        foreach ($loggerConfig['Logger'] as $loggerClass) {
            $loggerInstance = $this->getLoggerInstance($loggerClass, $loggerConfig, $channelName, $output);
            $logger->pushHandler($loggerInstance);
        }

        return $logger;
    }

    /**
     * Creates a logger from config
     *
     * @param $loggerClass
     * @param $loggerConfig
     * @param $consumer
     * @param OutputInterface $output
     * @return \Monolog\Handler\StreamHandler|SymfonyConsoleHandler
     * @throws \Exception
     */
    private function getLoggerInstance($loggerClass, $loggerConfig, $consumer, OutputInterface $output) {
        switch ($loggerClass) {

            // Monolog StreamHandler
            case 'StreamHandler':
                $stream = rtrim($loggerConfig['LogPath'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
                $stream .= $consumer . '.log';
                $instance = new \Monolog\Handler\StreamHandler($stream);
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

    protected function execute(InputInterface $input, OutputInterface $output) {
        $consumerIdent = $input->getArgument('consumer');
        $consumerToGet = '\\TYPO3Analysis\\Consumer\\' . $consumerIdent;

        if (class_exists($consumerToGet) === false) {
            throw new \Exception('A consumer like "' . $consumerIdent . '" does not exist', 1368100583);
        }

        $logger = $this->initializeLogger($this->config, $consumerIdent, $output);

        $consumer = new $consumerToGet();
        /* @var \TYPO3Analysis\Consumer\ConsumerAbstract $consumer  */
        $consumer->setConfig($this->config);
        $consumer->setDatabase($this->database);
        $consumer->setMessageQueue($this->messageQueue);
        $consumer->setLogger($logger);
        $consumer->initialize();

        $projectConfig = $this->config['Projects'][$this->getProject()];
        $consumer->setExchange($projectConfig['RabbitMQ']['Exchange']);

        $consumerIdent = str_replace('\\', '\\\\', $consumerIdent);
        $logger->info('Consumer starts', array('consumer' => $consumerIdent));

        $callback = array($consumer, 'process');
        $this->messageQueue->basicConsume($consumer->getExchange(), $consumer->getQueue(), $consumer->getRouting(), $consumer->getConsumerTag(), $callback);

        return true;
    }
}