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

use Jacobine\Entity\DataSource;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;

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
 *      |-> Consumer: Crawler\\Gerrit (type: server)
 *              |-> Consumer: Crawler\\Gerrit (type: project)
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
class GerritCommand extends Command implements ContainerAwareInterface
{

    use ContainerAwareTrait;

    /**
     * Routing key for the message queue
     *
     * @var string
     */
    const ROUTING = 'crawler.gerrit';

    /**
     * MessageQueue connection
     *
     * @var \Jacobine\Component\AMQP\MessageQueue
     */
    protected $messageQueue;

    /**
     * Project service
     *
     * @var \Jacobine\Service\Project
     */
    protected $projectService;

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
     * Sets up the project and message queue
     *
     * @param InputInterface $input An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     * @return void
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->setProject($input->getOption('project'));

        $this->messageQueue = $this->container->get('component.amqp.messageQueue');
        $this->projectService = $this->container->get('service.project');
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
        $exchange = $this->container->getParameter('messagequeue.exchange');
        $dataSource = [
            DataSource::TYPE_GERRIT_PROJECT,
            DataSource::TYPE_GERRIT_SERVER
        ];

        $configFile = $this->container->getParameter('application.gerrie.configFile');

        if (file_exists($configFile) === false) {
            $msg = 'Gerrit config file "%s" does not exist.';
            $msg = sprintf($msg, $configFile);
            throw new \Exception($msg, 1369437144);
        }

        $project = $this->getProject();
        if ($project) {
            $projects = $this->projectService->getProjectByNameWithDatasources($project, $dataSource);
        } else {
            $projects = $this->projectService->getAllProjectsWithDatasources($dataSource);
        }

        foreach ($projects as $project) {
            foreach ($project['dataSources'] as $dataSources) {
                foreach ($dataSources as $singleSource) {

                    switch ($singleSource['type']) {
                        case DataSource::TYPE_GERRIT_PROJECT:
                            $type = 'project';
                            break;
                        case DataSource::TYPE_GERRIT_SERVER:
                            $type = 'server';
                            break;
                        default:
                            $exceptionMessage = 'Datasource type ' . $singleSource['type'] . ' not supported';
                            throw new \Exception($exceptionMessage, 1411320967);
                    }

                    $message = [
                        'project' => $project['id'],
                        'configFile' => $configFile,
                        'type' => $type
                    ];

                    $this->messageQueue->sendSimpleMessage($message, $exchange, self::ROUTING);
                }
            }
        }

        return null;
    }
}
