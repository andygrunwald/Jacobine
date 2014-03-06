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

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;
use TYPO3Analysis\Helper\AMQPFactory;
use TYPO3Analysis\Helper\MessageQueue;

class GitwebCommand extends Command
{

    /**
     * Message Queue Queue
     *
     * @var string
     */
    const QUEUE = 'crawler.gitweb';

    /**
     * Message Queue routing
     *
     * @var string
     */
    const ROUTING = 'crawler.gitweb';

    /**
     * Config
     *
     * @var array
     */
    protected $config = array();

    /**
     * MessageQueue connection
     *
     * @var \TYPO3Analysis\Helper\MessageQueue
     */
    protected $messageQueue = null;

    /**
     * Project
     *
     * @var string
     */
    protected $project = null;

    /**
     * Configures the current command.
     *
     * @return void
     */
    protected function configure()
    {
        $this->setName('crawler:gitweb')
            ->setDescription('Adds a Gitweb page to message queue to crawl this.')
            ->addOption(
                'project',
                null,
                InputOption::VALUE_OPTIONAL,
                'Chose the project (for configuration, etc.).',
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
     * Initialize and starts a single consumer.
     *
     * @param InputInterface $input An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     * @return null|integer null or 0 if everything went fine, or an error code
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $projectConfig = $this->config['Projects'][$this->getProject()];

        // If we do not get a gitweb, exit here
        if (isset($projectConfig['Gitweb']) === false) {
            return null;
        }

        $gitwebUrl = $projectConfig['Gitweb'];
        if (filter_var($gitwebUrl, FILTER_VALIDATE_URL) === false) {
            throw new \Exception('"' . $gitwebUrl . '" seems to be not a valid url', 1369417543);
        }

        $message = array(
            'project' => $this->getProject(),
            'url' => $gitwebUrl
        );

        $this->messageQueue->sendMessage($message, $projectConfig['RabbitMQ']['Exchange'], self::QUEUE, self::ROUTING);
        return null;
    }
}
