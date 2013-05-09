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
     * HTTP Client
     *
     * @var \Buzz\Browser
     */
    protected $browser = null;

    /**
     * Project
     *
     * @var string
     */
    protected $project = null;

    protected function configure() {
        $this->setName('message-queue:consumer')
             ->setDescription('Generic task for message queue consumer')
             // @todo create a command to list all available projects
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

    protected function setProject($project) {
        $this->project = $project;
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->setProject($input->getOption('project'));
        $this->config = Yaml::parse(CONFIG_FILE);

        $config = $this->config['MySQL'];
        if (array_key_exists($this->getProject(), $this->config['Projects']) === false) {
            throw new \Exception('Configuration for project "' . $this->getProject() . '" does not exist', 1368101351);
        }
        $projectConfig = $this->config['Projects'][$this->getProject()];
        $this->database = new Database($config['Host'], $config['Port'], $config['Username'], $config['Password'], $projectConfig['MySQL']['Database']);

        $curlClient = new \Buzz\Client\Curl();
        $this->browser = new \Buzz\Browser($curlClient);

        $config = $this->config['RabbitMQ'];
        $this->messageQueue = new MessageQueue($config['Host'], $config['Port'], $config['Username'], $config['Password'], $config['VHost']);
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $consumer = $input->getArgument('consumer');
        $consumerToGet = '\\TYPO3Analysis\\Consumer\\' . $consumer;

        if (class_exists($consumerToGet) === false) {
            // @todo add a command to find all consumer and list them
            throw new \Exception('A consumer like "' . $consumer . '" does not exist', 1368100583);
        }

        $consumer = new $consumerToGet();
        /* @var \TYPO3Analysis\Consumer\ConsumerAbstract $consumer  */
        $consumer->setConfig($this->config);
        $consumer->setDatabase($this->database);
        $consumer->setHttpClient($this->browser);
        $consumer->setMessageQueue($this->messageQueue);
        $consumer->initialize();

        $projectConfig = $this->config['Projects'][$this->getProject()];
        $consumer->setExchange($projectConfig['RabbitMQ']['Exchange']);

        $callback = array($consumer, 'process');
        $this->messageQueue->basicConsume($consumer->getExchange(), $consumer->getQueue(), $consumer->getRouting(), $consumer->getConsumerTag(), $callback);

        return true;
    }
}