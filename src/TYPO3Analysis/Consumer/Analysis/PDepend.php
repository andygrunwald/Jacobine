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
 * @package TYPO3Analysis\Consumer\Analysis
 * @author Andy Grunwald <andygrunwald@gmail.com>
 */
class PDepend extends ConsumerAbstract
{

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
     * @return void
     */
    public function process($message)
    {
        $this->setMessage($message);
        $messageData = json_decode($message->body);

        $this->getLogger()->info('Receiving message', (array) $messageData);

        // If there is no directory to analyse, exit here
        if (is_dir($messageData->directory) !== true) {
            $this->getLogger()->critical('Directory does not exist', array('directory' => $messageData->directory));
            $this->rejectMessage($message);
            return;
        }

        $dirToAnalyze = rtrim($messageData->directory, DIRECTORY_SEPARATOR);
        $analysisFiles = $this->generateAnalysisFilenames($dirToAnalyze);

        // If there was already a pDepend run, all files must be exist. If yes, exit here
        if ($this->doesAnalysisFilesAlreadyExists($analysisFiles) === true) {
            $context = array(
                'versionId' => $messageData->versionId,
                'directory' => $messageData->directory
            );
            $this->getLogger()->info('Directory already analyzed with pDepend', $context);
            $this->acknowledgeMessage($message);
            return;
        }

        /** @var \Symfony\Component\Process\Process $process */
        list($process, $exception) = $this->executePDepend($dirToAnalyze, $analysisFiles);
        if ($exception !== null || $process->isSuccessful() === false) {
            $context = $this->getContextOfCommand($process, $exception);
            $this->getLogger()->critical('pDepend command failed', $context);
            $this->rejectMessage($this->getMessage());
            return;
        }

        if ($this->doesMinimumOneAnalysisFileNotExists($analysisFiles) === true) {
            $context = $analysisFiles;
            $this->getLogger()->critical('pDepend analysis result files does not exist!', $context);
            $this->rejectMessage($message);
            return;
        }

        // @todo add further consumer to parse and store the jDependXml- and summaryXml-file

        $this->acknowledgeMessage($message);

        $this->getLogger()->info('Finish processing message', (array)$messageData);
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
     * @return array [
     *                  0 => Symfony Process object,
     *                  1 => Exception if one was thrown otherwise null
     *               ]
     */
    private function executePDepend($dirToAnalyze, array $analysisFiles)
    {
        $config = $this->getConfig();
        $filePattern = $config['Application']['PDepend']['FilePattern'];
        $filePattern = ProcessUtils::escapeArgument($filePattern);

        $analysisFiles['jDependChartFile'] = ProcessUtils::escapeArgument($analysisFiles['jDependChartFile']);
        $analysisFiles['jDependXmlFile'] = ProcessUtils::escapeArgument($analysisFiles['jDependXmlFile']);
        $analysisFiles['overviewPyramidFile'] = ProcessUtils::escapeArgument($analysisFiles['overviewPyramidFile']);
        $analysisFiles['summaryXmlFile'] = ProcessUtils::escapeArgument($analysisFiles['summaryXmlFile']);

        $dirToAnalyze = ProcessUtils::escapeArgument($dirToAnalyze . DIRECTORY_SEPARATOR);

        // Execute pDepend
        $command = $config['Application']['PDepend']['Binary'];
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
        $processFactory = new ProcessFactory();
        $process = $processFactory->createProcess($command, $processTimeout);

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
