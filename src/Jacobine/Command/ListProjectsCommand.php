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
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;

/**
 * Class ListProjectsCommand
 *
 * Command to list all projects which are stored in the database.
 * This command does not execute something. It will only output a list of stored projects with datasources.
 *
 * Usage:
 *  php console jacobine:list-projects
 *
 * @package Jacobine\Command
 * @author Andy Grunwald <andygrunwald@gmail.com>
 */
class ListProjectsCommand extends Command implements ContainerAwareInterface
{

    use ContainerAwareTrait;

    /**
     * Project service
     *
     * @var \Jacobine\Service\Project
     */
    protected $projectService;

    /**
     * Configures the current command.
     *
     * @return void
     */
    protected function configure()
    {
        $this->setName('jacobine:list-projects')
             ->setDescription('Lists all available and configured projects');
    }

    /**
     * Initializes the command just after the input has been validated.
     *
     * Sets up the config
     *
     * @param InputInterface $input An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     * @return void
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->projectService = $this->container->get('service.project');
    }

    /**
     * Executes the current command.
     *
     * Lists all stored projects.
     *
     * @param InputInterface $input An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     * @return null|integer null or 0 if everything went fine, or an error code
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $projects = $this->projectService->getAllProjectsWithDatasources();

        if (count($projects) <= 0) {
            $message = '<error>Oh no! Sadly there a no projects in our storage backend :(</error>';
            $output->writeln($message);

            $message = '<error>But hey, it is not to late! There is a change to add new projects.</error>';
            $output->writeln($message);

            $message = '<error>Just execute "./console jacobine:create-project" to add some :)</error>';
            $output->writeln($message);

            $message = '<error>Have fun and see you next time!</error>';
            $output->writeln($message);

            return true;
        }

        foreach ($projects as $project) {

            $message = '<comment>' . $project['name'] . ' (' . $project['website'] . ')</comment>';
            $output->writeln($message);

            foreach ($project['dataSources'] as $type => $sourcesPerType) {

                $message = '<comment>- ' . DataSource::getTextForType($type) . '</comment>';
                $output->writeln($message);

                foreach ($sourcesPerType as $singleSource) {
                    $message = '<comment>  * ' . $singleSource['content'] . '</comment>';
                    $output->writeln($message);
                }
            }

            $output->writeln('');
        }

        return null;
    }
}
