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
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Class MailinglistCommand
 *
 * Command to send the first message to the message broker to crawl a
 * single mailinglist or a complete mailinglist server (e.g. mailman).
 *
 * Only a message to crawl such a server will be created and send to a message broker.
 * With this message a chain of crawling messages is triggered:
 *
 * MailinglistCommand
 *      |-> Consumer: Crawler\\Mailinglist (type: server)
 *          |-> Consumer: Crawler\\Mailinglist (type: list)
 *
 * The second message to consumer "Crawler\\Mailinglist" will only be created if MailinglistCommand sends a URL of a
 * complete mailmain server. If MailinglistCommand sends only a single / list of mailinglists the second message
 * won`t be crated.
 *
 * Usage:
 *  php console crawler:mailinglist [--project=ProjectName]
 *
 * e.g. to start crawling of the TYPO3 mailman server
 *  php console crawler:mailinglist --project=TYPO3
 *
 * @package Jacobine\Command
 * @author Andy Grunwald <andygrunwald@gmail.com>
 */
class MailinglistCommand extends Command implements ContainerAwareInterface
{

    use ContainerAwareTrait;

    /**
     * Message Queue routing
     *
     * @var string
     */
    const ROUTING = 'crawler.mailinglist';

    /**
     * Project identifier
     *
     * @var string
     */
    const PROJECT = 'TYPO3';

    /**
     * Config
     *
     * @var array
     */
    protected $config = [];

    /**
     * MessageQueue connection
     *
     * @var \Jacobine\Component\AMQP\MessageQueue
     */
    protected $messageQueue;

    /**
     * Project
     *
     * @var string
     */
    protected $project;

    /**
     * Message Queue Exchange
     *
     * @var string
     */
    protected $exchange;

    /**
     * Configures the current command.
     *
     * @return void
     */
    protected function configure()
    {
        $this->setName('crawler:mailinglist')
             ->setDescription('Adds a single mailinglist or a mailinglist host to message queue to crawl it.')
             ->addOption(
                'project',
                null,
                InputOption::VALUE_OPTIONAL,
                'Choose the project (for configuration, etc.).',
                self::PROJECT
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
     * Sets up the config, HTTP client, database and message queue
     *
     * @param InputInterface $input An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     * @return void
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->setProject($input->getOption('project'));
        $this->config = Yaml::parse(CONFIG_FILE);

        $projectConfig = $this->config['Projects'][$this->getProject()];
        $this->exchange = $projectConfig['RabbitMQ']['Exchange'];

        $this->messageQueue = $this->container->get('component.amqp.messageQueue');
    }

    /**
     * Executes the current command.
     *
     * Reads the mailinglists from config and sends it to the message broker..
     *
     * @param InputInterface $input An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     * @return null|integer null or 0 if everything went fine, or an error code
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // TODO This is not dynamic enough. Currently we can only read mailinglists from config
        // A better way would be to read this from a database or an alternative data storage
        $projectConfig = $this->config['Projects'][$this->getProject()];
        $message = [
            'project' => $this->getProject(),
            'type' => 'server',
            'host' => $projectConfig['Mailinglist']['Host'],
        ];

        $this->messageQueue->sendSimpleMessage($message, $this->exchange, self::ROUTING);
    }
}
