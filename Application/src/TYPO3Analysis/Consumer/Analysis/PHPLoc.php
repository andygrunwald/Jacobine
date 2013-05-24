<?php
/**
 * @todo adds a description (license text, description of this class / file, etc)
 */
namespace TYPO3Analysis\Consumer\Analysis;

use TYPO3Analysis\Consumer\ConsumerAbstract;

class PHPLoc extends ConsumerAbstract {

    /**
     * Gets a description of the consumer
     *
     * @return string
     */
    public function getDescription() {
        return 'Executes the PHPLoc analysis on a given folder and stores the results in phploc database table.';
    }

    /**
     * Initialize the consumer.
     * Sets the queue and routing key
     *
     * @return void
     */
    public function initialize() {
        $this->setQueue('analysis.phploc');
        $this->setRouting('analysis.phploc');
    }

    /**
     * The logic of the consumer
     *
     * @param \stdClass     $message
     * @throws \Exception
     */
    public function process($message) {
        $messageData = json_decode($message->body);

        // If there is already a phploc record in database, exit here
        if ($this->getPhpLocDataFromDatabase($messageData->versionId) !== false) {
            $this->getLogger()->info('Record already analyzed with PHPLoc', array('versionId' =>$messageData->versionId));
            $this->acknowledgeMessage($message);
            return;
        }

        // If there is no directory to analyse, exit here
        if (is_dir($messageData->directory) !== true) {
            $this->getLogger()->critical('Directory does not exist', array('directory' => $messageData->directory));
            $msg = sprintf('Directory %s does not exist', $messageData->directory);
            throw new \Exception($msg, 1367168690);
        }

        $dirToAnalyze = rtrim($messageData->directory, DIRECTORY_SEPARATOR);
        $pathParts = explode(DIRECTORY_SEPARATOR, $dirToAnalyze);
        $dirName = array_pop($pathParts);
        $xmlFile = 'phploc-' . $dirName . '.xml';
        $xmlFile = implode(DIRECTORY_SEPARATOR, $pathParts) . DIRECTORY_SEPARATOR . $xmlFile;

        // Execute PHPLoc
        $config = $this->getConfig();
        $filePattern = $config['Application']['PHPLoc']['FilePattern'];
        $command = $config['Application']['PHPLoc']['Binary'];
        $command .= ' --count-tests --names ' . escapeshellarg($filePattern);
        $command .= ' --log-xml ' . escapeshellarg($xmlFile) . ' ' . escapeshellarg($dirToAnalyze . DIRECTORY_SEPARATOR);

        $this->getLogger()->info('Analyze with PHPLoc', array('directory' => $dirToAnalyze));

        $this->executeCommand($command);

        if (file_exists($xmlFile) === false) {
            $this->getLogger()->critical('phploc result file does not exist!', array('file' => $xmlFile));
            $msg = sprintf('phploc result file "%s" does not exist!', $xmlFile);
            throw new \Exception($msg, 1367169297);
        }

        // Get PHPLoc results and save them
        $phpLocResults = simplexml_load_file($xmlFile);
        $this->storePhpLocDataInDatabase($messageData->versionId, $phpLocResults->children());

        $this->acknowledgeMessage($message);
    }

    /**
     * Stores the result of phploc command to database
     *
     * @param integer               $versionId
     * @param SimpleXMLElement      $phpLocResults
     * @return void
     */
    private function storePhpLocDataInDatabase($versionId, $phpLocResults) {
        $data = array(
            'version'               => (int) $versionId,
            'directories'           => (int) $phpLocResults->directories,
            'files'                 => (int) $phpLocResults->files,
            'loc'                   => (int) $phpLocResults->loc,
            'ncloc_classes'         => (int) $phpLocResults->nclocClasses,
            'cloc'                  => (int) $phpLocResults->cloc,
            'ncloc'                 => (int) $phpLocResults->ncloc,
            'ccn'                   => (int) $phpLocResults->ccn,
            'ccn_methods'           => (int) $phpLocResults->ccnMethods,
            'interfaces'            => (int) $phpLocResults->interfaces,
            'traits'                => (int) $phpLocResults->traits,
            'classes'               => (int) $phpLocResults->classes,
            'abstract_classes'      => (int) $phpLocResults->abstractClasses,
            'concrete_classes'      => (int) $phpLocResults->concreteClasses,
            'anonymous_functions'   => (int) $phpLocResults->anonymousFunctions,
            'functions'             => (int) $phpLocResults->functions,
            'methods'               => (int) $phpLocResults->methods,
            'public_methods'        => (int) $phpLocResults->publicMethods,
            'non_public_methods'    => (int) $phpLocResults->nonPublicMethods,
            'non_static_methods'    => (int) $phpLocResults->nonStaticMethods,
            'static_methods'        => (int) $phpLocResults->staticMethods,
            'constants'             => (int) $phpLocResults->constants,
            'class_constants'       => (int) $phpLocResults->classConstants,
            'global_constants'      => (int) $phpLocResults->globalConstants,
            'test_classes'          => (int) $phpLocResults->testClasses,
            'test_methods'          => (int) $phpLocResults->testMethods,
            'ccn_by_loc'            => (double) $phpLocResults->ccnByLoc,
            'ccn_by_nom'            => (double) $phpLocResults->ccnByNom,
            'ncloc_by_noc'          => (double) $phpLocResults->nclocByNoc,
            'ncloc_by_nom'          => (double) $phpLocResults->nclocByNom,
            'namespaces'            => (int) $phpLocResults->namespaces,
        );
        $insertedId = $this->getDatabase()->insertRecord('phploc', $data);

        $msg = 'Stored analzye results for version record in PHPLoc record';
        $context = array('versionId' => $versionId, 'phpLocRecord' => $insertedId);
        $this->getLogger()->info($msg, $context);
    }

    /**
     * Receives a single phploc data record of the database
     *
     * @param integer   $id
     * @return bool|array
     */
    private function getPhpLocDataFromDatabase($id) {
        $fields = array('version');
        $rows = $this->getDatabase()->getRecords($fields, 'phploc', array('version' => $id), '', '', 1);

        $row = false;
        if (count($rows) === 1) {
            $row = array_shift($rows);
            unset($rows);
        }

        return $row;
    }
}