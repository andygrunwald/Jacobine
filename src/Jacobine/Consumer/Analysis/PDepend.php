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
 * Class PDepend
 *
 * A consumer to execute pDepend (https://github.com/pdepend/pdepend).
 * pDepend is a PHP port of javas design quality and metrics tool JDepend (http://clarkware.com/software/JDepend.html).
 *
 * We use this to generate the overview pyramide and generate and save various metrics per class.
 *
 * Message format (json encoded):
 *  [
 *      directory: Absolute path to folder which will be analyzed. E.g. /var/www/my/sourcecode
 *      versionId: Version ID to get the regarding version record from version database table
 *  ]
 *
 * Usage:
 *  php console analysis:consumer Analysis\\PDepend
 *
 * @package Jacobine\Consumer\Analysis
 * @author Andy Grunwald <andygrunwald@gmail.com>
 */
class PDepend extends ConsumerAbstract
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
        return 'Executes the PDepend analysis on a given folder.';
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

        $this->setQueueOption('name', 'analysis.pdepend');
        $this->enableDeadLettering();

        $this->setRouting('analysis.pdepend');
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
        switch ($message->type) {
            case 'analyze':
                $this->processXDependExecution($message);
                break;

            case 'import-jDepend-xml':
                $this->processImportJDependXml($message);
                break;

            case 'import-summary-xml':
                $this->processImportSummaryXml($message);
                break;
        }
    }

    /**
     * Imports a result of a xDepend analysis
     * This method imports a standard jDepend output file.
     *
     * @link http://clarkware.com/software/JDepend.html
     *
     * @param \stdClass $message
     * @throws \RuntimeException
     * @return void
     */
    private function processImportJDependXml($message)
    {
        // TODO implement
        // $message->content
        // jdepend-xml-typo3_src-6.2.0beta7.xml
        throw new \RuntimeException('Not implemented', 1400418354);
    }

    /**
     * Imports a result of a xDepend analysis
     * This methods import a summary xml output file.
     * E.g. pDepend can report such a summary file
     *
     * @param \stdClass $message
     * @throws \RuntimeException
     * @return void
     */
    private function processImportSummaryXml($message)
    {
        // TODO implement
        // $message->content
        // summary-xml-typo3_src-6.2.0beta7.xml
        throw new \RuntimeException('Not implemented', 1400418339);
    }

    /**
     * Executes the xDepend (currently only pDepend) tool
     *
     * @param \stdClass $message
     * @throws \Exception
     * @return void
     */
    private function processXDependExecution($message)
    {
        // If there is no directory to analyse, exit here
        if (is_dir($message->directory) !== true) {
            $this->getLogger()->critical('Directory does not exist', ['directory' => $message->directory]);
            throw new \Exception('Directory does not exist', 1398886309);
        }

        $dirToAnalyze = rtrim($message->directory, DIRECTORY_SEPARATOR);
        $analysisFiles = $this->generateAnalysisFilenames($dirToAnalyze);

        // If there was already a pDepend run, all files must be exist. If yes, exit here
        if ($this->doesAnalysisFilesAlreadyExists($analysisFiles) === true) {
            $context = array(
                'versionId' => $message->versionId,
                'directory' => $message->directory
            );
            $this->getLogger()->info('Directory already analyzed with pDepend', $context);
            return;
        }

        /** @var \Symfony\Component\Process\Process $process */
        list($process, $exception) = $this->executePDepend($dirToAnalyze, $analysisFiles, $message->project);
        if ($exception !== null || $process->isSuccessful() === false) {
            $context = $this->getContextOfCommand($process, $exception);
            $this->getLogger()->critical('pDepend command failed', $context);
            throw new \Exception('pDepend command failed', 1398886366);
        }

        if ($this->doesMinimumOneAnalysisFileNotExists($analysisFiles) === true) {
            $context = $analysisFiles;
            $this->getLogger()->critical('pDepend analysis result files does not exist!', $context);
            throw new \Exception('pDepend analysis result files does not exist!', 1398886405);
        }

        $this->addFurtherMessageToQueue($message->project, $message->versionId, $analysisFiles);
    }

    /**
     * Adds further messages to the message broker
     *
     * @param string $project
     * @param integer $versionId
     * @param array $analysisFiles
     * @return void
     */
    private function addFurtherMessageToQueue($project, $versionId, array $analysisFiles)
    {
        $config = $this->getConfig();
        $projectConfig = $config['Projects'][$project];
        $exchange = $projectConfig['RabbitMQ']['Exchange'];

        $jDependXmlMessage = [
            'project' => $project,
            'versionId' => $versionId,
            'type' => 'import-jDepend-xml',
            'content' => file_get_contents($analysisFiles['jDependXmlFile'])
        ];

        $summaryXmlMessage = $jDependXmlMessage;
        $summaryXmlMessage['type'] = 'import-summary-xml';
        $summaryXmlMessage['content'] = file_get_contents($analysisFiles['summaryXmlFile']);

        $messageQueue = $this->getMessageQueue();
        $messageQueue->sendSimpleMessage($jDependXmlMessage, $exchange, 'analysis.pdepend');
        $messageQueue->sendSimpleMessage($summaryXmlMessage, $exchange, 'analysis.pdepend');
    }

    /**
     * Generates file names of result files for pDepend like the jDepend chart or xml file.
     *
     * @param string $dirToAnalyze Directory which should be analyzed by pDepend
     * @return array
     */
    private function generateAnalysisFilenames($dirToAnalyze)
    {
        $pathParts = explode(DIRECTORY_SEPARATOR, $dirToAnalyze);
        $dirName = array_pop($pathParts);
        $basePath = implode(DIRECTORY_SEPARATOR, $pathParts) . DIRECTORY_SEPARATOR;

        $files = [
            'jDependChartFile' => $basePath . 'jdepend-chart-' . $dirName . '.svg',
            'jDependXmlFile' => $basePath . 'jdepend-xml-' . $dirName . '.xml',
            'overviewPyramidFile' => $basePath . 'overview-pyramid-' . $dirName . '.svg',
            'summaryXmlFile' => $basePath . 'summary-xml-' . $dirName . '.xml'
        ];

        return $files;
    }

    /**
     * Checks if all result analysis files already exists.
     * Returns true if the files already exists. False otherwise.
     *
     * @param array $files
     * @return bool
     */
    private function doesAnalysisFilesAlreadyExists(array $files)
    {
        $result = (
            file_exists($files['jDependChartFile']) === true
            && file_exists($files['jDependXmlFile']) === true
            && file_exists($files['overviewPyramidFile']) === true
            && file_exists($files['summaryXmlFile']) === true
        );

        return $result;
    }

    /**
     * Checks if minimum one result analysis file does not exists.
     * Returns true if minimum one file is missing. False otherwise.
     *
     * @param array $files
     * @return bool
     */
    private function doesMinimumOneAnalysisFileNotExists(array $files)
    {
        $result = (
            file_exists($files['jDependChartFile']) !== true
            || file_exists($files['jDependXmlFile']) !== true
            || file_exists($files['overviewPyramidFile']) !== true
            || file_exists($files['summaryXmlFile']) !== true
        );

        return $result;
    }

    /**
     * Starts a analysis of a given $dirToAnalyze with pDepend
     *
     * @param string $dirToAnalyze Directory which should be analyzed by pDepend
     * @param array $analysisFiles
     * @param string $project
     * @return array [
     *                  0 => Symfony Process object,
     *                  1 => Exception if one was thrown otherwise null
     *               ]
     */
    private function executePDepend($dirToAnalyze, array $analysisFiles, $project)
    {
        $config = $this->getConfig();
        $projectConfig = $config['Projects'][$project];
        $configFile  = rtrim(dirname(CONFIG_FILE), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $configFile .= $projectConfig['PDepend']['ConfigFile'];

        $filePattern = $config['Application']['PDepend']['FilePattern'];
        $filePattern = ProcessUtils::escapeArgument($filePattern);

        $analysisFiles['jDependChartFile'] = ProcessUtils::escapeArgument($analysisFiles['jDependChartFile']);
        $analysisFiles['jDependXmlFile'] = ProcessUtils::escapeArgument($analysisFiles['jDependXmlFile']);
        $analysisFiles['overviewPyramidFile'] = ProcessUtils::escapeArgument($analysisFiles['overviewPyramidFile']);
        $analysisFiles['summaryXmlFile'] = ProcessUtils::escapeArgument($analysisFiles['summaryXmlFile']);
        $pDependConfigFile = ProcessUtils::escapeArgument($configFile);

        $dirToAnalyze = ProcessUtils::escapeArgument($dirToAnalyze . DIRECTORY_SEPARATOR);

        // Execute pDepend
        $command = $config['Application']['PDepend']['Binary'];
        $command .= ' --configuration=' . $pDependConfigFile;
        $command .= ' --jdepend-chart=' . $analysisFiles['jDependChartFile'];
        $command .= ' --jdepend-xml=' . $analysisFiles['jDependXmlFile'];
        $command .= ' --overview-pyramid=' . $analysisFiles['overviewPyramidFile'];
        $command .= ' --summary-xml=' . $analysisFiles['summaryXmlFile'];
        $command .= ' --suffix=' . $filePattern;
        $command .= ' --coderank-mode=inheritance,property,method ' . $dirToAnalyze;

        $context = [
            'directory' => $dirToAnalyze,
            'command' => $command
        ];
        $this->getLogger()->info('Start analyzing with pDepend', $context);

        // Disable process timeout, because pDepend should take a while
        $processTimeout = null;
        $process = $this->processFactory->createProcess($command, $processTimeout);

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
