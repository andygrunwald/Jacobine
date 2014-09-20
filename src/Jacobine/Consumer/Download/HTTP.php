<?php
/**
 * This file is part of the Jacobine package.
 *
 * (c) Andreas Grunwald <andygrunwald@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Jacobine\Consumer\Download;

use Jacobine\Consumer\ConsumerAbstract;
use Jacobine\Component\Filesystem\File;
use Jacobine\Component\Database\Database;
use Jacobine\Component\AMQP\MessageQueue;

/**
 * Class HTTP
 *
 * A consumer to download a HTTP resource.
 *
 * Message format (json encoded):
 *  [
 *      project: Project to be analyzed. Must be a configured project in "configFile"
 *      versionId: ID of a version record in the database. A succesful download will be flagged
 *      filenamePrefix: Prefix which will be added to the filename if the file is downloaded
 *      filenamePostfix: Postfix which will be added to the filename if the file is downloaded
 *  ]
 *
 * Usage:
 *  php console jacobine:consumer Download\\HTTP
 *
 * @package Jacobine\Consumer\Download
 * @author Andy Grunwald <andygrunwald@gmail.com>
 */
class HTTP extends ConsumerAbstract
{

    /**
     * Constructor to set dependencies
     *
     * @param MessageQueue $messageQueue
     * @param Database $database
     */
    public function __construct(MessageQueue $messageQueue, Database $database)
    {
        $this->setDatabase($database);
        $this->setMessageQueue($messageQueue);
    }

    /**
     * Gets a description of the consumer
     *
     * @return string
     */
    public function getDescription()
    {
        return 'Downloads a HTTP resource';
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

        $this->setQueueOption('name', 'download.http');
        $this->enableDeadLettering();

        $this->setRouting('download.http');
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
        $context = [
            'versionId' => $message->versionId
        ];

        // If the record does not exists in the database, reject message
        if ($record === false) {
            $this->getLogger()->critical('Record does not exist in version table', $context);
            throw new \Exception('Record does not exist in version table', 1398949703);
        }

        // If the file has already been downloaded, skip this message
        if (isset($record['downloaded']) === true && $record['downloaded']) {
            $this->getLogger()->info('Record marked as already downloaded', $context);
            return;
        }

        $targetTempDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $fileName = $message->filenamePrefix . $record['version'] . $message->filenamePostfix;
        $downloadFile = new File($targetTempDir . $fileName);

        $config = $this->getConfig();
        $projectConfig = $config['Projects'][$message->project];
        $targetDir = rtrim($projectConfig['ReleasesPath'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $targetFile = new File($targetDir . $fileName);

        // If the file already there do not download it again
        if ($targetFile->exists() === true && $record['checksum_tar_md5']
            && $targetFile->getMd5OfFile() === $record['checksum_tar_md5']
        ) {
            $context = array(
                'targetFile' => $targetFile->getFile()
            );
            $this->getLogger()->info('File already exists', $context);
            $this->setVersionAsDownloadedInDatabase($record['id']);
            $this->addFurtherMessageToQueue($message->project, $record['id'], $targetFile->getFile());
            return;
        }

        $context = array(
            'downloadUrl' => $record['url_tar'],
            'targetDownloadFile' => $downloadFile->getFile()
        );
        $this->getLogger()->info('Starting download', $context);

        // TODO: Replace timeout ith new config setting
        $downloadResult = $downloadFile->download($record['url_tar'], $config['Various']['Downloads']['Timeout']);
        if (!$downloadResult) {
            $context = array(
                'file' => $record['url_tar'],
                'timeout' => $config['Various']['Downloads']['Timeout'],
            );
            $this->getLogger()->critical('Download command failed', $context);
            throw new \Exception('Download command failed', 1398949775);
        }

        // If there is no file after download, exit here
        if ($downloadFile->exists() !== true) {
            $context = ['targetFile' => $downloadFile->getFile()];
            $this->getLogger()->critical('File does not exist after download', $context);
            throw new \Exception('File does not exist after download', 1398949793);
        }

        if (is_dir($targetDir) === false) {
            mkdir($targetDir, 0744, true);
        }

        $context = array(
            'oldFile' => $downloadFile->getFile(),
            'newFile' => $targetFile->getFile()
        );
        $this->getLogger()->info('Rename downloaded file', $context);
        $renameResult = $downloadFile->rename($targetFile->getFile());

        if ($renameResult !== true) {
            $this->getLogger()->critical('Rename operation failed. Rights issue?', $context);
            throw new \Exception('Rename operation failed. Rights issue?', 1398949817);
        }

        // If the hashes are not equal, exit here
        $md5Hash = $downloadFile->getMd5OfFile();
        if ($record['checksum_tar_md5'] && $md5Hash !== $record['checksum_tar_md5']) {
            $context = array(
                'targetFile' => $downloadFile->getFile(),
                'databaseHash' => $record['checksum_tar_md5'],
                'fileHash' => $md5Hash
            );
            $this->getLogger()->critical('Checksums for file are not equal', $context);
            throw new \Exception('Checksums for file are not equal', 1398949838);
        }

        // Update the 'downloaded' flag in database
        $this->setVersionAsDownloadedInDatabase($record['id']);

        // Adds new messages to queue: extract the file, get filesize or tar.gz file
        $this->addFurtherMessageToQueue($message->project, $record['id'], $downloadFile->getFile());
    }

    /**
     * Adds new messages to queue system to extract a tar.gz file and get the filesize of this file
     *
     * @param string $project
     * @param integer $id
     * @param string $file
     * @return void
     */
    private function addFurtherMessageToQueue($project, $id, $file)
    {
        $config = $this->getConfig();
        $projectConfig = $config['Projects'][$project];

        $message = [
            'project' => $project,
            'versionId' => $id,
            'filename' => $file
        ];

        $exchange = $projectConfig['RabbitMQ']['Exchange'];
        $this->getMessageQueue()->sendSimpleMessage($message, $exchange, 'extract.targz');
        $this->getMessageQueue()->sendSimpleMessage($message, $exchange, 'analysis.filesize');
    }

    /**
     * Receives a single version of the database
     *
     * @param integer $id
     * @return bool|array
     */
    private function getVersionFromDatabase($id)
    {
        $fields = array('id', 'version', 'checksum_tar_md5', 'url_tar', 'downloaded');
        $rows = $this->getDatabase()->getRecords($fields, 'jacobine_versions', array('id' => $id), '', '', 1);

        $row = false;
        if (count($rows) === 1) {
            $row = array_shift($rows);
            unset($rows);
        }

        return $row;
    }

    /**
     * Updates a single version and sets them to 'downloaded'
     *
     * @param integer $id
     * @return void
     */
    private function setVersionAsDownloadedInDatabase($id)
    {
        $this->getDatabase()->updateRecord('jacobine_versions', ['downloaded' => 1], ['id' => $id]);
        $this->getLogger()->info('Set version as downloaded', ['versionId' => $id]);
    }
}
