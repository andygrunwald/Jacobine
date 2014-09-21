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
 * Class GitwebCommand
 *
 * Command to send the first message to the message broker to crawl a
 * Gitweb Server (https://git.wiki.kernel.org/index.php/Gitweb).
 *
 * Only a message to crawl such a server will be created and sent to a message broker.
 * With this message a chain of crawling messages is triggered:
 *
 * GitwebCommand
 *      |-> Consumer: Crawler\\Gitweb
 *              |-> Consumer: Download\\Git
 *
 * Usage:
 *  php console crawler:gitweb [--project=ProjectName]
 *
 * e.g. to start crawling of the TYPO3 Gitweb server
 *  php console crawler:gitweb --project=TYPO3
 *
 * @package Jacobine\Command
 * @author Andy Grunwald <andygrunwald@gmail.com>
 */
class GitwebCommand extends Command implements ContainerAwareInterface
{

    use ContainerAwareTrait;

    /**
     * Message Queue routing
     *
     * @var string
     */
    const ROUTING = 'crawler.gitweb';

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
        $this->setName('crawler:gitweb')
             ->setDescription('Adds a Gitweb page to message queue to crawl this.')
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
     * Sets up the project, project service and message queue
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
     * Initialize and starts a single consumer.
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
            DataSource::TYPE_GITWEB_SERVER
        ];

        $project = $this->getProject();
        if ($project) {
            $projects = $this->projectService->getProjectByNameWithDatasources($project, $dataSource);
        } else {
            $projects = $this->projectService->getAllProjectsWithDatasources($dataSource);
        }

        foreach ($projects as $project) {
            $message = [
                'project' => $project['projectId'],
                'url' => $project['datasourceContent']
            ];

            $this->messageQueue->sendSimpleMessage($message, $exchange, self::ROUTING);
        }

        return null;
    }
}
