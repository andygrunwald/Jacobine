<?php
/**
 * @todo adds a description (license text, description of this class / file, etc)
 */
namespace TYPO3Analysis\Consumer\Download;

use TYPO3Analysis\Consumer\ConsumerAbstract;

class HTTP extends ConsumerAbstract {

    public function initialize()
    {
        $this->setQueue('download.http');
        $this->setRouting('download.http');
    }

    public function process($message)
    {
        $record = $this->getVersionFromDatabase($message->body);

        // If the record does not exists in the database OR the file has already been downloaded, exit here
        if ($record === false || (isset($record['downloaded']) && $record['downloaded'])) {
            $this->acknowledgeMessage($message);
            return;
        }

        $targetTempDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        // @todo find a better way for the filename
        $fileName = 'typo3_' . $record['version'] . '.zip';

        // We download the file with wget, because we get a progress bar for free :)
        $command = 'wget ' . escapeshellarg($record['url_zip']) . ' --output-document=' . escapeshellarg($targetTempDir . $fileName);
        exec($command);

        // If there is no file after download, exit here
        if (file_exists($targetTempDir . $fileName) !== true) {
            throw new \Exception('File ' . $targetTempDir . $fileName . ' does not exist after download', 1366829810);
            return;
        }

        $config = $this->getConfig();
        $targetDir = rtrim($config['Projects']['TYPO3']['DownloadPath'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        rename($targetTempDir . $fileName, $targetDir . $fileName);

        // If the hashes are not equal, exit here
        $md5Hash = md5_file($targetDir . $fileName);
        if ($record['checksum_zip_md5'] && $md5Hash !== $record['checksum_zip_md5']) {
            $exceptionMessage = 'Checksums for file "' . $targetDir . $fileName . '" are not equal';
            $exceptionMessage .= ' (Database: ' . $record['checksum_zip_md5'] . ', File hash: ' . $md5Hash . ')';
            throw new \Exception($exceptionMessage, 1366830113);
        }

        // Update the 'downloaded' flag in database
        $this->setVersionAsDownloadedInDatabase($record['id']);

        // Add a new message to extract the file
        $this->addZipExtractMessageToQueue($record['id'], $targetDir . $fileName);

        $this->acknowledgeMessage($message);
    }

    /**
     * Adds a new message to the queue system to extract a zip file
     *
     * @param integer   $id
     * @param string    $file
     * @return void
     */
    private function addZipExtractMessageToQueue($id, $file) {
        $message = array(
            'id' => $id,
            'file' => $file
        );
        $message = json_encode($message);
        $this->getMessageQueue()->sendMessage($message, 'TYPO3', 'extract.zip', 'extract.zip');
    }

    /**
     * Receives a single version of the database
     *
     * @param integer   $id
     * @return bool|array
     */
    private function getVersionFromDatabase($id) {
        $fields = array('id', 'version', 'checksum_zip_md5', 'url_zip', 'downloaded');
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
    }
}