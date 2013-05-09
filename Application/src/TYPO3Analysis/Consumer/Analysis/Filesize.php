<?php
/**
 * @todo adds a description (license text, description of this class / file, etc)
 */
namespace TYPO3Analysis\Consumer\Analysis;

use TYPO3Analysis\Consumer\ConsumerAbstract;

class Filesize extends ConsumerAbstract {

    /**
     * Gets a description of the consumer
     *
     * @return string
     */
    public function getDescription()
    {
        return 'Determines the filesize in bytes and stores them in version database table.';
    }

    public function initialize()
    {
        $this->setQueue('analysis.filesize');
        $this->setRouting('analysis.filesize');
    }

    public function process($message)
    {
        var_dump(__METHOD__ . ' - START');

        $messageData = json_decode($message->body);
        $record = $this->getVersionFromDatabase($messageData->versionId);

        // If the record does not exists in the database OR the filesize is already saved, exit here
        if ($record === false || (isset($record['size_tar']) && $record['size_tar'])) {
            $this->acknowledgeMessage($message);
            return;
        }

        // If there is no file, exit here
        if (file_exists($messageData->filename) !== true) {
            throw new \Exception('File ' . $messageData->filename . ' does not exist', 1367152522);
        }

        $fileSize = filesize($messageData->filename);

        // Update the 'downloaded' flag in database
        $this->saveFileSizeOfVersionInDatabase($record['id'], $fileSize);

        $this->acknowledgeMessage($message);

        var_dump(__METHOD__ . ' - END');
    }

    /**
     * Receives a single version of the database
     *
     * @param integer   $id
     * @return bool|array
     */
    private function getVersionFromDatabase($id) {
        $fields = array('id', 'size_tar');
        $rows = $this->getDatabase()->getRecords($fields, 'versions', array('id' => $id), '', '', 1);

        $row = false;
        if (count($rows) === 1) {
            $row = array_shift($rows);
            unset($rows);
        }

        return $row;
    }

    /**
     * Updates a single version and sets the 'size_tar' value
     *
     * @param integer   $id
     * @return void
     */
    private function saveFileSizeOfVersionInDatabase($id, $fileSize) {
        $this->getDatabase()->updateRecord('versions', array('size_tar' => $fileSize), array('id' => $id));
    }
}