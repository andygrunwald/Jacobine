<?php
/**
 * This file is part of the Jacobine package.
 *
 * (c) Andreas Grunwald <andygrunwald@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Jacobine\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;
use Jacobine\Helper\AMQPFactory;
use Jacobine\Helper\MessageQueue;

/**
 * Class GerritCommand
 *
 * Command to send the first message to the message broker to crawl a
 * Gerrit Code Review Server (https://code.google.com/p/gerrit/).
 *
 * Only a message to crawl such a server will be created and sent to a message broker.
 * With this message a chain of crawling messages is triggered:
 *
 * GerritCommand
 *      |-> Consumer: Crawler\\Gerrit
 *              |-> Consumer: Crawler\\GerritProject
 *
 * Usage:
 *  php console crawler:gerrit [--project=ProjectName]
 *
 * e.g. to start crawling of the TYPO3 Gerrit server
 *  php console crawler:gerrit --project=TYPO3
 *
 * @package Jacobine\Command
 * @author Andy Grunwald <andygrunwald@gmail.com>
 */
class GerritCommand extends Command
{

    /**
     * Routing key for the message queue
     *
     * @var string
     */
    const ROUTING = 'crawler.gerrit';

    /**
     * Config
     *
     * @var array
     */
    protected $config = [];

    /**
     * MessageQueue connection
     *
     * @var \TYPO3Analysis\Helper\MessageQueue
     */
    protected $messageQueue;

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
        $this->setName('crawler:gerrit')
             ->setDescription('Adds a Gerrit review system to message queue to crawl it.')
             ->addOption(
                 'project',
                 null,
                 InputOption::VALUE_OPTIONAL,
                 'Choose the project (for configuration, etc.).',
                 'TYPO3'
             );
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

        $this->messageQueue = $this->initializeMessageQueue($this->config);
    }

    /**
     * Initialize the message queue object
     *
     * @param array $parsedConfig
     * @return MessageQueue
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
     * Executes the current command.
     *
     * Checks the config and adds a Gerrit review system to message queue.
     *
     * @param InputInterface $input An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     * @return null|integer null or 0 if everything went fine, or an error code
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $projectConfig = $this->config['Projects'][$this->getProject()];

        $configFile = rtrim(dirname(CONFIG_FILE), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $configFile .= $projectConfig['Gerrit']['ConfigFile'];

        if (file_exists($configFile) === false) {
            $msg = 'Gerrit config file "%s" does not exist.';
            $msg = sprintf($msg, $configFile);
            throw new \Exception($msg, 1369437144);
        }

        $message = array(
            'project' => $this->getProject(),
            'configFile' => $configFile
        );

        $this->messageQueue->sendSimpleMessage($message, $projectConfig['RabbitMQ']['Exchange'], self::ROUTING);
        return null;
    }
}
