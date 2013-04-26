<?php
/**
 * @todo adds a description (license text, description of this class / file, etc)
 */
namespace TYPO3Analysis\Consumer\Extract;

use TYPO3Analysis\Consumer\ConsumerAbstract;

class Zip extends ConsumerAbstract {

    public function initialize()
    {
        $this->setQueue('extract.zip');
        $this->setRouting('extract.zip');
    }

    public function process($message)
    {
        // @todo implement

        // Store in the database, that a file is extracted ;)
        var_dump(__METHOD__);
        var_dump($message->body);
    }
}