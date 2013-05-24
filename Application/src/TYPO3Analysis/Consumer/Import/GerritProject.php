<?php
/**
 * @todo adds a description (license text, description of this class / file, etc)
 */
namespace TYPO3Analysis\Consumer\Import;

use TYPO3Analysis\Consumer\ConsumerAbstract;

class GerritProject extends ConsumerAbstract {

    /**
     * Gets a description of the consumer
     *
     * @return string
     */
    public function getDescription() {
        return 'Imports a single project from Gerrit review system';
    }

    /**
     * Initialize the consumer.
     * Sets the queue and routing key
     *
     * @return void
     */
    public function initialize() {
        $this->setQueue('import.gerritproject');
        $this->setRouting('import.gerritproject');
    }

    /**
     * The logic of the consumer
     *
     * @param \stdClass     $message
     * @throws \Exception
     */
    public function process($message) {
        $messageData = json_decode($message->body);

        $this->getLogger()->info(__METHOD__ . $message->body);

        $this->acknowledgeMessage($message);

       }
}