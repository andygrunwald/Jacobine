<?php
/**
 * @todo adds a description (license text, description of this class / file, etc)
 */
namespace TYPO3Analysis\Consumer\Download;

use TYPO3Analysis\Consumer\ConsumerAbstract;

class HTTP extends ConsumerAbstract {

    /**
     * Gets a description of the consumer
     *
     * @return string
     */
    public function getDescription() {
        return 'Downloads a HTTP resource.';
    }

    /**
     * Initialize the consumer.
     * Sets the queue and routing key
     *
     * @return void
     */
    public function initialize() {
        $this->setQueue('download.http');
        $this->setRouting('download.http');
    }

    /**
     * The logic of the consumer
     *
     * @param \stdClass     $message
     * @throws \Exception
     */
    public function process($message) {
        $messageData = json_decode($message->body);

        $record = $this->getVersionFromDatabase($messageData->versionId);
        $context = array('versionId' => $messageData->versionId);

        // If the record does not exists in the database exit here
        if ($record === false) {
            $this->getLogger()->info('Record does not exist in version table', $context);
            $this->acknowledgeMessage($message);
            return;
        }

        // If the file has already been downloaded exit here
        if (isset($record['downloaded']) === true && $record['downloaded']) {
            $this->getLogger()->info('Record marked as already downloaded', $context);
            $this->acknowledgeMessage($message);
            return;
        }

        $targetTempDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        // @todo find a better way for the filename ... Download-Prefix in project config?
        // in $messageData->Project you can find the project
        $fileName = 'typo3_' . $record['version'] . '.tar.gz';
        $targetFile = $targetTempDir . $fileName;

        $context = array(
            'downloadUrl' => $record['url_tar'],
            'targetFile' => $targetFile
        );
        $this->getLogger()->info('Start download', $context);

        // We download the file with wget, because we get a progress bar for free :)
        $command = 'wget ' . escapeshellarg($record['url_tar']) . ' --output-document=' . escapeshellarg($targetFile);
        exec($command);

        // If there is no file after download, exit here
        if (file_exists($targetFile) !== true) {
            $this->getLogger()->critical('File does not exist after download', array('targetFile' => $targetFile));
            $msg = sprintf('File %s does not exist after download', $targetFile);
            throw new \Exception($msg, 1366829810);
        }

        $config = $this->getConfig();
        $projectConfig = $config['Projects'][$messageData->project];
        $targetDir = rtrim($projectConfig['ReleasesPath'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        if (is_dir($targetDir) === false) {
            mkdir($targetDir, 0777, true);
        }

        rename($targetFile, $targetDir . $fileName);

        // If the hashes are not equal, exit here
        $md5Hash = md5_file($targetDir . $fileName);
        if ($record['checksum_tar_md5'] && $md5Hash !== $record['checksum_tar_md5']) {

            $msg = 'Checksums for file are not equal';
            $context = array(
                'targetFile' => $targetDir . $fileName,
                'databaseHash' => $record['checksum_tar_md5'],
                'fileHash' => $md5Hash
            );
            $this->getLogger()->critical($msg, $context);

            $msg = 'Checksums for file "%s" are not equal (Database: %s, File hash: %s)';
            $msg = sprintf($msg, $targetDir . $fileName, $record['checksum_tar_md5'], $md5Hash);
            throw new \Exception($msg, 1366830113);
        }

        // Update the 'downloaded' flag in database
        $this->setVersionAsDownloadedInDatabase($record['id']);

        $this->acknowledgeMessage($message);

        // Adds new messages to queue: extract the file, get filesize or tar.gz file
        $this->addFurtherMessageToQueue($messageData->project, $record['id'], $targetDir . $fileName);
    }

    /**
     * Adds new messages to queue system to extract a tar.gz file and get the filesize of this file
     *
     * @param string    $project
     * @param integer   $id
     * @param string    $file
     * @return void
     */
    private function addFurtherMessageToQueue($project, $id, $file) {
        $message = array(
            'project' => $project,
            'versionId' => $id,
            'filename' => $file
        );

        $this->getMessageQueue()->sendMessage($message, 'TYPO3', 'extract.targz', 'extract.targz');
        $this->getMessageQueue()->sendMessage($message, 'TYPO3', 'analysis.filesize', 'analysis.filesize');
    }

    /**
     * Receives a single version of the database
     *
     * @param integer   $id
     * @return bool|array
     */
    private function getVersionFromDatabase($id) {
        $fields = array('id', 'version', 'checksum_tar_md5', 'url_tar', 'downloaded');
        $rows = $this->getDatabase()->getRecords($fields, 'versions', array('id' => $id), '', '', 1);

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
     * @param integer   $id
     * @return void
     */
    private function setVersionAsDownloadedInDatabase($id) {
        $this->getDatabase()->updateRecord('versions', array('downloaded' => 1), array('id' => $id));
        $this->getLogger()->info('Set version as downloaded', array('versionId' => $id));
    }
}