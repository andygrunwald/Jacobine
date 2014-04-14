<?php
/**
 * This file is part of the TYPO3-Analytics package.
 *
 * (c) Andreas Grunwald <andygrunwald@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TYPO3Analysis\Consumer\Extract;

use TYPO3Analysis\Consumer\ConsumerAbstract;
use TYPO3Analysis\Helper\ProcessFactory;
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
 *  php console analysis:consumer Extract\\Targz
 *
 * @package TYPO3Analysis\Consumer\Extract
 * @author Andy Grunwald <andygrunwald@gmail.com>
 */
class Targz extends ConsumerAbstract
{

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
     * @return void
     */
    public function process($message)
    {
        $this->setMessage($message);
        $messageData = json_decode($message->body);

        $this->getLogger()->info('Receiving message', (array) $messageData);

        $record = $this->getVersionFromDatabase($messageData->versionId);
        $context = array('versionId' => $messageData->versionId);

        // If the record does not exists in the database exit here
        if ($record === false) {
            $this->getLogger()->critical('Record does not exist in version table', $context);
            $this->rejectMessage($message);
            return;
        }

        // If the file has already been extracted exit here
        if (isset($record['extracted']) === true && $record['extracted']) {
            $this->getLogger()->info('Record marked as already extracted', $context);
            $this->acknowledgeMessage($message);
            return;
        }

        // If there is no file, exit here
        if (file_exists($messageData->filename) !== true) {
            $this->getLogger()->critical('File does not exist', array('filename' => $messageData->filename));
            $this->rejectMessage($this->getMessage());
            return;
        }

        $pathInfo = pathinfo($messageData->filename);
        $folder = rtrim($pathInfo['dirname'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        chdir($folder);

        // Create folder first and change the target folder of tar command via -C
        // because typo3_src-4.6.0alpha1 does not contain a parent root in tar.
        // Version typo3_src-4.6.0alpha1 is different from other versions.
        $targetFolder = 'typo3_src-' . $record['version'] . DIRECTORY_SEPARATOR;
        mkdir($targetFolder);

        if (is_dir($targetFolder) === false) {
            $this->getLogger()->critical('Directory can`t be created', array('folder' => $folder));
            $this->rejectMessage($this->getMessage());
            return;
        }

        /** @var \Symfony\Component\Process\Process $process */
        list($process, $exception) = $this->extractArchive($messageData->filename, $targetFolder);
        if ($exception !== null || $process->isSuccessful() === false) {
            $context = $this->getContextOfCommand($process, $exception);
            $this->getLogger()->critical('Extract command failed', $context);
            $this->rejectMessage($this->getMessage());
            return;
        }

        // Set the correct access rights. 0777 is a bit to much ;)
        chmod($targetFolder, 0755);

        // Store in the database, that a file is extracted ;)
        $this->setVersionAsExtractedInDatabase($messageData->versionId);

        $this->acknowledgeMessage($message);

        // Adds new messages to queue: analyze phploc
        $this->addFurtherMessageToQueue($messageData->project, $record['id'], $folder . $targetFolder);

        $this->getLogger()->info('Finish processing message', (array)$messageData);
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
        $rows = $this->getDatabase()->getRecords($fields, 'versions', array('id' => $id), '', '', 1);

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
        $this->getDatabase()->updateRecord('versions', array('extracted' => 1), array('id' => $id));
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
        $message = array(
            'project' => $project,
            'versionId' => $versionId,
            'directory' => $directory
        );

        $this->getMessageQueue()->sendSimpleMessage($message, 'TYPO3', 'analysis.phploc');
        $this->getMessageQueue()->sendSimpleMessage($message, 'TYPO3', 'analysis.pdepend');
        $this->getMessageQueue()->sendSimpleMessage($message, 'TYPO3', 'analysis.linguist');
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

        // We didnt use the \PharData class to decompress + extract, because
        // a) it is much more slower (performance) than a tar system call
        // b) it eats much more PHP memory which is not useful in a message based env
        $archive = ProcessUtils::escapeArgument($archive);
        $target = ProcessUtils::escapeArgument($target);
        $command = 'tar -xzf ' . $archive . ' -C ' . $target;

        $processFactory = new ProcessFactory();
        $process = $processFactory->createProcess($command);

        $exception = null;
        try {
            $process->run();
        } catch (\Exception $exception) {}

        return [$process, $exception];
    }
}
