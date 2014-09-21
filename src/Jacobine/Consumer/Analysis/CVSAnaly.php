<?php
/**
 * This file is part of the Jacobine package.
 *
 * (c) Andreas Grunwald <andygrunwald@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Jacobine\Consumer\Analysis;

use Jacobine\Consumer\ConsumerAbstract;
use Jacobine\Component\Process\ProcessFactory;
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
 * Message format (json encoded):
 *  [
 *      project: Project to be analyzed. Id of jacobine_project table
 *      checkoutDir: Absolute path to folder which will be analyzed. E.g. /var/www/my/checkout
 *  ]
 *
 * Usage:
 *  php console jacobine:consumer Analysis\\CVSAnaly
 *
 * @package Jacobine\Consumer\Analysis
 * @author Andy Grunwald <andygrunwald@gmail.com>
 */
class CVSAnaly extends ConsumerAbstract
{

    /**
     * @var \Jacobine\Component\Process\ProcessFactory
     */
    protected $processFactory;

    /**
     * Constructor to set dependencies
     *
     * @param ProcessFactory $processFactory
     */
    public function __construct(ProcessFactory $processFactory)
    {
        $this->processFactory = $processFactory;
    }

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
     * @throws \Exception
     * @return void
     */
    protected function process($message)
    {
        // If there is no directory to analyse, exit here
        if (is_dir($message->checkoutDir) !== true) {
            $this->getLogger()->critical('Directory does not exist', ['directory' => $message->checkoutDir]);
            throw new \Exception('Directory does not exist', 1398885152);
        }

        /** @var \Symfony\Component\Process\Process $process */
        list($process, $exception) = $this->executeCVSAnaly($message->checkoutDir);
        if ($exception !== null || $process->isSuccessful() === false) {
            $context = $this->getContextOfCommand($process, $exception);
            $this->getLogger()->critical('CVSAnaly command failed', $context);
            throw new \Exception('CVSAnaly command failed', 1398885269);
        }
    }

    /**
     * Builds the CVSAnaly command
     *
     * @param string $directory
     * @param string $extensions
     * @return string
     */
    private function buildCVSAnalyCommand($directory, $extensions)
    {
        $cvsAnalyBinary = $this->container->getParameter('application.cvsanaly.binary');
        $configFile = $this->container->getParameter('application.cvsanaly.configFile');
        $databaseName = $this->container->getParameter('database.database');

        $command = escapeshellcmd($cvsAnalyBinary);
        $command .= ' --config-file ' . ProcessUtils::escapeArgument($configFile);
        $command .= ' --db-database ' . ProcessUtils::escapeArgument($databaseName);
        $command .= ' --extensions ' . ProcessUtils::escapeArgument($extensions);
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
        // Hardcoded extensions in config, because some extensions may not work correct
        // With this way we can enable / disable various extensions and know that all works fine :)
        // Later on we try to fix all extensions in CVSAnaly to work with all repositories
        // For this we have to execute CVSAnaly with --list-extensions parameter
        $extensions = $this->container->getParameter('application.cvsanaly.extensions');

        if ($extensions) {
            $extensions = str_replace(' ', '', $extensions);
        }

        return $extensions;
    }

    /**
     * Starts a analysis of a given $checkoutDir with CVSAnaly
     *
     * @param string $checkoutDir Directory which should be analyzed by CVSAnalY
     * @return array [
     *                  0 => Symfony Process object,
     *                  1 => Exception if one was thrown otherwise null
     *               ]
     */
    private function executeCVSAnaly($checkoutDir)
    {
        $this->getLogger()->info('Start analyzing directory with CVSAnaly', ['directory' => $checkoutDir]);

        $extensions = $this->getCVSAnalyExtensions();
        $command = $this->buildCVSAnalyCommand($checkoutDir, $extensions);

        $process = $this->processFactory->createProcess($command, null);
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
