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

    protected function configure() {
        $this->setName('message-queue:consumer')
             ->setDescription('Generic task for message queue consumer')
             ->addArgument('consumer', InputArgument::REQUIRED, 'Part namespace of consumer');
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->config = Yaml::parse(CONFIG_FILE);

        $config = $this->config['MySQL'];
        $this->database = new Database($config['Host'], $config['Port'], $config['Username'], $config['Password'], $config['Databases']['typo3']);

        $curlClient = new \Buzz\Client\Curl();
        $this->browser = new \Buzz\Browser($curlClient);

        $config = $this->config['RabbitMQ'];
        $this->messageQueue = new MessageQueue($config['Host'], $config['Port'], $config['Username'], $config['Password'], $config['VHost']);
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $consumer = $input->getArgument('consumer');
        $consumerToGet = '\\TYPO3Analysis\\Consumer\\' . $consumer;

        $consumer = new $consumerToGet();
        /* @var \TYPO3Analysis\Consumer\ConsumerAbstract $consumer  */
        $consumer->setConfig($this->config);
        $consumer->setDatabase($this->database);
        $consumer->setHttpClient($this->browser);
        $consumer->setMessageQueue($this->messageQueue);
        $consumer->initialize();
        // @todo make the exchange configurable
        $consumer->setExchange('TYPO3');

        $callback = array($consumer, 'process');
        $this->messageQueue->basicConsume($consumer->getExchange(), $consumer->getQueue(), $consumer->getRouting(), $consumer->getConsumerTag(), $callback);

        return true;
    }
}