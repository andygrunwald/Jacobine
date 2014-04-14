<?php
/**
 * This file is part of the TYPO3-Analytics package.
 *
 * (c) Andreas Grunwald <andygrunwald@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TYPO3Analysis\Consumer\Analysis;

use TYPO3Analysis\Consumer\ConsumerAbstract;
use TYPO3Analysis\Helper\ProcessFactory;
use Symfony\Component\Process\ProcessUtils;

/**
 * Class CVSAnaly
 *
 * A consumer to execute CVSAnaly (https://github.com/MetricsGrimoire/CVSAnalY).
 * CVSAnaly is a tool to analyze the history of a version control system (e.g. subversion or git).
 *
 * CVSAnaly is written in Python.
 * We have to execute CVSAnaly via a external command, because we can`t speak from PHP to Python libs directly.
 * Currently we can only execute this consumer once, because CVSAnaly is not ready to run concurrent.
 *
 * TODO: Idea -> Port this consumer from PHP to Python. With this we can get rid of the system command.
 *
 * Message format (json encoded):
 *  [
 *      project: Project key from config. E.g. TYPO3
 *      checkoutDir: Absolute path to folder which will be analyzed. E.g. /var/www/my/checkout
 *  ]
 *
 * Usage:
 *  php console analysis:consumer Analysis\\CVSAnaly
 *
 * @package TYPO3Analysis\Consumer\Analysis
 * @author Andy Grunwald <andygrunwald@gmail.com>
 */
class CVSAnaly extends ConsumerAbstract
{

    /**
     * Gets a description of the consumer
     *
     * @return string
     */
    public function getDescription()
    {
        return 'Executes the CVSAnaly analysis on a given folder and stores the results in database.';
    }

    /**
     * Initialize the consumer.
     * Sets the queue and routing key
     *
     * @return void
     */
    public function initialize()
    {
        parent::initialize();

        $this->setQueueOption('name', 'analysis.cvsanaly');
        $this->enableDeadLettering();

        $this->setRouting('analysis.cvsanaly');
    }

    /**
     * The logic of the consumer
     *
     * @param \stdClass $message
     * @return void
     */
    public function process($message)
    {
        $this->setMessage($message);
        $messageData = json_decode($message->body);

        $this->getLogger()->info('Receiving message', (array) $messageData);

        // If there is no directory to analyse, exit here
        if (is_dir($messageData->checkoutDir) !== true) {
            $this->getLogger()->critical('Directory does not exist', array('directory' => $messageData->checkoutDir));
            $this->rejectMessage($message);
            return;
        }

        /** @var \Symfony\Component\Process\Process $process */
        list($process, $exception) = $this->executeCVSAnaly($messageData->checkoutDir, $messageData->project);
        if ($exception !== null || $process->isSuccessful() === false) {
            $context = $this->getContextOfCommand($process, $exception);
            $this->getLogger()->critical('CVSAnaly command failed', $context);
            $this->rejectMessage($this->getMessage());
            return;
        }

        $this->acknowledgeMessage($message);

        $this->getLogger()->info('Finish processing message', (array)$messageData);
    }

    /**
     * Builds the CVSAnaly command
     *
     * @param array $config
     * @param string $project
     * @param string $directory
     * @param string $extensions
     * @return string
     */
    private function buildCVSAnalyCommand($config, $project, $directory, $extensions)
    {
        $projectConfig = $config['Projects'][$project];

        $configFile = rtrim(dirname(CONFIG_FILE), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $configFile .= $projectConfig['CVSAnaly']['ConfigFile'];

        $command = escapeshellcmd($config['Application']['CVSAnaly']['Binary']);
        $command .= ' --config-file ' . ProcessUtils::escapeArgument($configFile);
        $command .= ' --db-driver ' . ProcessUtils::escapeArgument('mysql');
        $command .= ' --db-hostname ' . ProcessUtils::escapeArgument($config['MySQL']['Host']);
        $command .= ' --db-user ' . ProcessUtils::escapeArgument($config['MySQL']['Username']);
        $command .= ' --db-password ' . ProcessUtils::escapeArgument($config['MySQL']['Password']);
        $command .= ' --db-database ' . ProcessUtils::escapeArgument($projectConfig['MySQL']['Database']);
        $command .= ' --extensions ' . ProcessUtils::escapeArgument($extensions);
        $command .= ' --metrics-all';
        $command .= ' ' . ProcessUtils::escapeArgument($directory);

        return $command;
    }

    /**
     * Returns all active and usable extensions of CVSAnaly
     *
     * @return string
     */
    private function getCVSAnalyExtensions()
    {
        // TODO Take care of this ... configure extensions or make this work!
        // Hardcoded extensions, because some extensions may not work correct
        // With this way we can enable / disable various extensions
        // and know that all works fine :)
        // Later on we try to fix all extensions in CVSAnaly to work with all repositories
        //
        // $command = escapeshellcmd($config['Application']['CVSAnaly']['Binary']);
        // $command .= ' --list-extensions';
        //
        // $extensions = $this->executeCommand($command);
        // $extensions = implode('', $extensions);
        $extensions = 'Months, Weeks';

        if ($extensions) {
            $extensions = str_replace(' ', '', $extensions);
        }

        return $extensions;
    }

    /**
     * Starts a analysis of a given $checkoutDir with CVSAnaly
     *
     * @param string $checkoutDir Directory which should be analyzed by github linguist
     * @param string $project Project which will be analyzed. Needed to catch the right configuration file
     * @return array [
     *                  0 => Symfony Process object,
     *                  1 => Exception if one was thrown otherwise null
     *               ]
     */
    private function executeCVSAnaly($checkoutDir, $project)
    {
        $this->getLogger()->info('Start analyzing directory with CVSAnaly', ['directory' => $checkoutDir]);

        $extensions = $this->getCVSAnalyExtensions();
        $command = $this->buildCVSAnalyCommand($this->getConfig(), $project, $checkoutDir, $extensions);

        $processFactory = new ProcessFactory();
        $process = $processFactory->createProcess($command, null);
        $exception = null;
        try {
            $process->run();
        } catch (\Exception $exception) {
            // This catch section is empty, because we got an error handling in the caller area
            // We check not only the exception. We use the result command of the process as well
        }

        return [$process, $exception];
    }
}
