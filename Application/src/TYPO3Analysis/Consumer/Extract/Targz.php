<?php
/**
 * @todo adds a description (license text, description of this class / file, etc)
 */
namespace TYPO3Analysis\Consumer\Extract;

use TYPO3Analysis\Consumer\ConsumerAbstract;

class Targz extends ConsumerAbstract {

    public function initialize()
    {
        $this->setQueue('extract.targz');
        $this->setRouting('extract.targz');
    }

    public function process($message)
    {
        var_dump(__METHOD__ . ' - START');

        $messageParts = json_decode($message->body);
        $record = $this->getVersionFromDatabase($messageParts->id);

        // If the record does not exists in the database OR the file has already been extracted
        // OR the file does not exists, exit here
        if ($record === false || (isset($record['extracted']) && $record['extracted'])) {
            $this->acknowledgeMessage($message);
            return;
        }

        // If there is no file, exit here
        if (file_exists($messageParts->file) !== true) {
            throw new \Exception('File ' . $messageParts->file . ' does not exist', 1367152938);
            return;
        }

        $pathInfo = pathinfo($messageParts->file);
        $folder = rtrim($pathInfo['dirname'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        chdir($folder);

        $command = 'tar -xvzf ' . escapeshellarg($messageParts->file);
        exec($command);

        $folder .= 'typo3_src-' . $record['version'];

        if (is_dir($folder) === false) {
            $exceptionMessage = 'Directory "' . $folder . '" does not exists after extracting tar.gz archive';
            throw new \Exception($exceptionMessage, 1367010680);
        }

        // Store in the database, that a file is extracted ;)
        $this->setVersionAsExtractedInDatabase($messageParts->id);

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
     * @param integer   $id
     * @return void
     */
    private function setVersionAsExtractedInDatabase($id) {
        $this->getDatabase()->updateRecord('versions', array('extracted' => 1), array('id' => $id));
    }
}