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

        // Create folder first and change the target folder of tar command via -C
        // because typo3_src-4.6.0alpha1 does not contain a parent root in tar.
        // Version typo3_src-4.6.0alpha1 is different from other versions.
        $targetFolder = 'typo3_src-' . $record['version'] . DIRECTORY_SEPARATOR;
        mkdir($targetFolder);

        if (is_dir($targetFolder) === false) {
            $exceptionMessage = 'Directory "' . $folder . '" can`t be created';
            throw new \Exception($exceptionMessage, 1367010680);
        }

        $command = 'tar -xzf ' . escapeshellarg($messageParts->file) . ' -C ' . escapeshellarg($targetFolder);
        $output = array();
        $returnValue = 0;
        exec($command, $output, $returnValue);

        if ($returnValue > 0) {
            $exceptionMessage = 'tar command returns an error!';
            throw new \Exception($exceptionMessage, 1367160535);
        }

        // Set the correct access rights. 0777 is a bit to much ;)
        chmod($targetFolder, 0755);

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