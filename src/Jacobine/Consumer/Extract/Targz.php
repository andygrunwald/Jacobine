<?php
/**
 * This file is part of the Jacobine package.
 *
 * (c) Andreas Grunwald <andygrunwald@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Jacobine\Consumer\Extract;

use Jacobine\Consumer\ConsumerAbstract;
use Jacobine\Component\Process\ProcessFactory;
use Jacobine\Component\Database\Database;
use Jacobine\Component\AMQP\MessageQueue;
use Symfony\Component\Process\ProcessUtils;

/**
 * Class Targz
 *
 * A consumer to extract a tar.gz archive.
 *
 * Message format (json encoded):
 *  [
 *      versionId: ID of a version record in the database. A succesful download will be flagged
 *      filename: Name of the file which will be extracted
 *  ]
 *
 * Usage:
 *  php console jacobine:consumer Extract\\Targz
 *
 * @package Jacobine\Consumer\Extract
 * @author Andy Grunwald <andygrunwald@gmail.com>
 */
class Targz extends ConsumerAbstract
{

    /**
     * @var \Jacobine\Component\Process\ProcessFactory
     */
    protected $processFactory;

    /**
     * Constructor to set dependencies
     *
     * @param Database $database
     * @param ProcessFactory $processFactory
     */
    public function __construct(Database $database, ProcessFactory $processFactory)
    {
        $this->processFactory = $processFactory;
        $this->setDatabase($database);
    }

    /**
     * Gets a description of the consumer
     *
     * @return string
     */
    public function getDescription()
    {
        return 'Extracts a *.tar.gz archive.';
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

        $this->setQueueOption('name', 'extract.targz');
        $this->enableDeadLettering();

        $this->setRouting('extract.targz');
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
        $record = $this->getVersionFromDatabase($message->versionId);
        $context = ['versionId' => $message->versionId];

        // If the record does not exists in the database exit here
        if ($record === false) {
            $this->getLogger()->critical('Record does not exist in version table', $context);
            throw new \Exception('Record does not exist in version table', 1398949998);
        }

        // If the file has already been extracted exit here
        if (isset($record['extracted']) === true && $record['extracted']) {
            $this->getLogger()->info('Record marked as already extracted', $context);
            return;
        }

        // If there is no file, exit here
        if (file_exists($message->filename) !== true) {
            $this->getLogger()->critical('File does not exist', ['filename' => $message->filename]);
            throw new \Exception('File does not exist', 1398950024);
        }

        $pathInfo = pathinfo($message->filename);
        $folder = rtrim($pathInfo['dirname'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        chdir($folder);

        // Create folder first and change the target folder of tar command via -C parameter
        // via this way we ensure a parent folder for a extracted tar.gz archive every time
        $config = $this->getConfig();
        $projectConfig = $config['Projects'][$message->project];
        $targetFolderPrefix = $projectConfig['Consumer']['Extract\Targz']['TargetFolderPrefix'];
        $targetFolder = $targetFolderPrefix . $record['version'] . DIRECTORY_SEPARATOR;

        mkdir($targetFolder);

        if (is_dir($targetFolder) === false) {
            $this->getLogger()->critical('Directory can`t be created', ['folder' => $folder]);
            throw new \Exception('Directory can`t be created', 1398950058);
        }

        /** @var \Symfony\Component\Process\Process $process */
        list($process, $exception) = $this->extractArchive($message->filename, $targetFolder);
        if ($exception !== null || $process->isSuccessful() === false) {
            $context = $this->getContextOfCommand($process, $exception);
            $this->getLogger()->critical('Extract command failed', $context);
            throw new \Exception('Extract command failed', 1398950082);
        }

        // Set the correct access rights. 0777 is a bit to much ;)
        chmod($targetFolder, 0744);

        // Store in the database, that a file is extracted ;)
        $this->setVersionAsExtractedInDatabase($message->versionId);

        // Adds new messages to queue: analyze phploc
        $this->addFurtherMessageToQueue($message->project, $record['id'], $folder . $targetFolder);
    }

    /**
     * Receives a single version of the database
     *
     * @param integer $id
     * @return bool|array
     */
    private function getVersionFromDatabase($id)
    {
        $fields = array('id', 'version', 'extracted');
        $rows = $this->getDatabase()->getRecords($fields, 'jacobine_versions', array('id' => $id), '', '', 1);

        $row = false;
        if (count($rows) === 1) {
            $row = array_shift($rows);
            unset($rows);
        }

        return $row;
    }

    /**
     * Updates a single version and sets them to 'extracted'
     *
     * @param integer $id
     * @return void
     */
    private function setVersionAsExtractedInDatabase($id)
    {
        $this->getDatabase()->updateRecord('jacobine_versions', ['extracted' => 1], ['id' => $id]);
        $this->getLogger()->info('Set version record as extracted', array('versionId' => $id));
    }

    /**
     * Adds new messages to queue system to analyze the folder and start github linguist analysis
     *
     * @param string $project
     * @param integer $versionId
     * @param string $directory
     * @return void
     */
    private function addFurtherMessageToQueue($project, $versionId, $directory)
    {
        $config = $this->getConfig();
        $projectConfig = $config['Projects'][$project];
        $exchange = $projectConfig['RabbitMQ']['Exchange'];

        $message = [
            'project' => $project,
            'versionId' => $versionId,
            'directory' => $directory
        ];

        $pDependMessage = $message;
        $pDependMessage['type'] = 'analyze';

        $this->getMessageQueue()->sendSimpleMessage($message, $exchange, 'analysis.phploc');
        $this->getMessageQueue()->sendSimpleMessage($message, $exchange, 'analysis.linguist');
        $this->getMessageQueue()->sendSimpleMessage($pDependMessage, $exchange, 'analysis.pdepend');
    }

    /**
     * Unpacks a single $archive in a specified $target folder
     *
     * @param string $archive tar.gz archive which should be extracted
     * @param string $target Target folder where the $archive will be extracted
     * @return array [0 => Symfony Process object, 1 => Exception if one was thrown otherwise null]
     */
    private function extractArchive($archive, $target)
    {
        $context = array(
            'filename' => $archive,
            'targetFolder' => $target
        );
        $this->getLogger()->info('Extracting file', $context);

        $config = $this->getConfig();

        // We didnt use the \PharData class to decompress + extract, because
        // a) it is much more slower (performance) than a tar system call
        // b) it eats much more PHP memory which is not useful in a message based env
        $archive = ProcessUtils::escapeArgument($archive);
        $target = ProcessUtils::escapeArgument($target);
        $command = $config['Application']['Tar']['Binary'];
        $command .= ' -xzf ' . $archive . ' -C ' . $target;

        $timeout = (int) $config['Application']['Tar']['Timeout'];
        $process = $this->processFactory->createProcess($command, $timeout);

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
