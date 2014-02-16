<?php
/**
 * @todo adds a description (license text, description of this class / file, etc)
 */
namespace TYPO3Analysis\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;
use TYPO3Analysis\Helper\MessageQueue;

class NNTPCommand extends Command
{

    /**
     * Message Queue Queue
     *
     * @var string
     */
    const QUEUE = 'crawler.nntp';

    /**
     * Message Queue routing
     *
     * @var string
     */
    const ROUTING = 'crawler.nntp';

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
        $this->setName('crawler:nntp')
            ->setDescription('Adds a NNTP server to message queue to crawl this.')
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
        // TODO Refactor this to use a config entity or an array
        $messageQueue = new MessageQueue($config['Host'], $config['Port'], $config['Username'], $config['Password'], $config['VHost']);

        return $messageQueue;
    }

    /**
     * Executes the current command.
     *
     * Checks the config and adds a NNTP server to message queue.
     *
     * @param InputInterface $input An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     * @return null|integer null or 0 if everything went fine, or an error code
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $projectConfig = $this->config['Projects'][$this->getProject()];

        $nntpConfig = $projectConfig['NNTP'];

        if (is_array($nntpConfig) === false || array_key_exists('Host', $nntpConfig) === false) {
            $msg = 'NNTP configuration for project "%s" does not exist or is incomplete.';
            $msg = sprintf($msg, $this->getProject());
            throw new \Exception($msg, 1380906175);
        }

        $message = array(
            'project' => $this->getProject(),
            'config' => $nntpConfig
        );

        $this->messageQueue->sendMessage($message, $projectConfig['RabbitMQ']['Exchange'], self::QUEUE, self::ROUTING);
        return null;
    }
}
