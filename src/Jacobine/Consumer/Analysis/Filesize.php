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
use Jacobine\Component\Database\Database;

/**
 * Class Filesize
 *
 * A consumer to measure the filefize of given filename.
 * The filesize will be written back into the regarding version record of version database table.
 * This is the reason why a versionId is necessary in message.
 *
 * Message format (json encoded):
 *  [
 *      versionId: Version ID to get the regarding version record from version database table
 *      filename: Filename to measure the filesize of
 *  ]
 *
 * Usage:
 *  php console analysis:consumer Analysis\\Filesize
 *
 * @package Jacobine\Consumer\Analysis
 * @author Andy Grunwald <andygrunwald@gmail.com>
 */
class Filesize extends ConsumerAbstract
{

    /**
     * Constructor to set dependencies
     *
     * @param Database $database
     */
    public function __construct(Database $database)
    {
        $this->setDatabase($database);
    }

    /**
     * Gets a description of the consumer
     *
     * @return string
     */
    public function getDescription()
    {
        return 'Determines the filesize in bytes and stores them in version database table.';
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

        $this->setQueueOption('name', 'analysis.filesize');
        $this->enableDeadLettering();

        $this->setRouting('analysis.filesize');
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

        // If the record does not exists in the database exit here
        if ($record === false) {
            $context = array('versionId' => $message->versionId);
            $this->getLogger()->critical('Record does not exist in version table', $context);
            throw new \Exception('Record does not exist in version table', 1398885424);
        }

        // If the filesize is already saved exit here
        if (isset($record['size_tar']) === true && $record['size_tar']) {
            $context = array('versionId' => $message->versionId);
            $this->getLogger()->info('Record marked as already analyzed', $context);
            return;
        }

        // If there is no file, exit here
        if (file_exists($message->filename) !== true) {
            $context = array('filename' => $message->filename);
            $this->getLogger()->critical('File does not exist', $context);
            throw new \Exception('File does not exist', 1398885531);
        }

        $this->getLogger()->info('Getting filesize', array('filename' => $message->filename));
        $fileSize = filesize($message->filename);

        // Update the 'downloaded' flag in database
        $this->saveFileSizeOfVersionInDatabase($record['id'], $fileSize);
    }

    /**
     * Receives a single version of the database
     *
     * @param integer $id
     * @return bool|array
     */
    private function getVersionFromDatabase($id)
    {
        $fields = array('id', 'size_tar');
        $rows = $this->getDatabase()->getRecords($fields, 'jacobine_versions', array('id' => $id), '', '', 1);

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
     * @param integer $id
     * @param integer $fileSize
     * @return void
     */
    private function saveFileSizeOfVersionInDatabase($id, $fileSize)
    {
        $this->getDatabase()->updateRecord('jacobine_versions', ['size_tar' => $fileSize], ['id' => $id]);

        $context = array('filesize' => $fileSize, 'versionId' => $id);
        $this->getLogger()->info('Save filesize for version record', $context);
    }
}
