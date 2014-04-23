<?php
/**
 * This file is part of the Jacobine package.
 *
 * (c) Andreas Grunwald <andygrunwald@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TYPO3Analysis\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Class ListProjectsCommand
 *
 * Command to list all projects which are valid configured.
 * This command does not execute something. It will only output a list of configured projects.
 *
 * A project must be a fulfill some requirements.
 * This requirements will be checked in isProjectConfigValid().
 * E.g.
 *      Database must be configured
 *      RabbitMQ-Exchange must be configured
 *
 * To check if your new project is configured correct, just configure it and execute this command.
 *
 * Usage:
 *  php console analysis:list-projects
 *
 * @package TYPO3Analysis\Command
 * @author Andy Grunwald <andygrunwald@gmail.com>
 */
class ListProjectsCommand extends Command
{

    /**
     * Configuration
     *
     * @var array
     */
    protected $config = [];

    /**
     * Configures the current command.
     *
     * @return void
     */
    protected function configure()
    {
        $this->setName('analysis:list-projects')
             ->setDescription('Lists all available and configured projects');
    }

    /**
     * Sets the configuration
     *
     * @param array $config
     * @return void
     */
    public function setConfig($config)
    {
        $this->config = $config;
    }

    /**
     * Gets the configuration
     *
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
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
        $this->setConfig(Yaml::parse(CONFIG_FILE));
    }

    /**
     * Executes the current command.
     *
     * Lists all configured projects.
     * See Config/config.yml
     *
     * @param InputInterface $input An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     * @return null|integer null or 0 if everything went fine, or an error code
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $config = $this->getConfig();

        if (is_array($config['Projects']) === false) {
            $message = '<error>There are no configured projects available! Please configure one!</error>';
            $output->writeln($message);
            return true;
        }

        foreach ($config['Projects'] as $projectName => $projectConfig) {
            if ($this->isProjectConfigValid($projectConfig) === false) {
                continue;
            }

            $message = '<comment>' . $projectName . '</comment>';
            $output->writeln($message);
        }

        return null;
    }

    /**
     * Checks the configuration and necessary config parts
     *
     * @param mixed $config
     * @return bool
     */
    private function isProjectConfigValid($config)
    {
        if (is_array($config) === false) {
            return false;
        }

        // Database settings
        if (array_key_exists('MySQL', $config) === false
            || array_key_exists('Database', $config['MySQL']) === false
        ) {
            return false;
        }

        // RabbitMQ settings
        if (array_key_exists('RabbitMQ', $config) === false
            || array_key_exists('Exchange', $config['RabbitMQ']) === false
        ) {
            return false;
        }

        // Various settings
        if (array_key_exists('ReleasesPath', $config) === false) {
            return false;
        }

        return true;
    }
}
