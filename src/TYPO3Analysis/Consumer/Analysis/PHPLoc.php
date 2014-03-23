<?php
/**
 * This file is part of the TYPO3-Analytics package.
 *
 * (c) Andreas Grunwald <andygrunwald@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TYPO3Analysis\Consumer\Analysis;

use TYPO3Analysis\Consumer\ConsumerAbstract;

/**
 * Class PHPLoc
 *
 * A consumer to execute PHPLOC (https://github.com/sebastianbergmann/phploc).
 * PHPLOC measured a PHP project very fast with some more general metrics like lines of code or num of classes.
 * This metrics will be saved in the database in the phploc table.
 *
 * Message format (json encoded):
 *  [
 *      directory: Absolute path to folder which will be analyzed. E.g. /var/www/my/sourcecode
 *      versionId: Version ID to get the regarding version record from version database table
 *  ]
 *
 * Usage:
 *  php console analysis:consumer Analysis\\PHPLoc
 *
 * @package TYPO3Analysis\Consumer\Analysis
 * @author Andy Grunwald <andygrunwald@gmail.com>
 */
class PHPLoc extends ConsumerAbstract
{

    /**
     * Gets a description of the consumer
     *
     * @return string
     */
    public function getDescription()
    {
        return 'Executes the PHPLoc analysis on a given folder and stores the results in phploc database table.';
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

        $this->setQueueOption('name', 'analysis.phploc');
        $this->enableDeadLettering();

        $this->setRouting('analysis.phploc');
    }

    /**
     * The logic of the consumer
     *
     * @param \stdClass $message
     * @return void
     */
    public function process($message)
    {
        $this->setMessage($message);
        $messageData = json_decode($message->body);

        $this->getLogger()->info('Receiving message', (array) $messageData);

        // If there is already a phploc record in database, exit here
        if ($this->getPhpLocDataFromDatabase($messageData->versionId) !== false) {
            $this->getLogger()->info(
                'Record already analyzed with PHPLoc',
                array('versionId' => $messageData->versionId)
            );
            $this->acknowledgeMessage($message);
            return;
        }

        // If there is no directory to analyse, exit here
        if (is_dir($messageData->directory) !== true) {
            $this->getLogger()->critical('Directory does not exist', array('directory' => $messageData->directory));
            $this->rejectMessage($message);
            return;
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
        $command .= ' --log-xml ' . escapeshellarg($xmlFile) . ' ' . escapeshellarg(
            $dirToAnalyze . DIRECTORY_SEPARATOR
        );

        $this->getLogger()->info('Start analyzing with PHPLoc', array('directory' => $dirToAnalyze));

        try {
            $this->executeCommand($command, false);
        } catch (\Exception $e) {
            $this->rejectMessage($this->getMessage());
            return;
        }

        if (file_exists($xmlFile) === false) {
            $this->getLogger()->critical('phploc result file does not exist!', array('file' => $xmlFile));
            $this->rejectMessage($message);
            return;
        }

        // Get PHPLoc results and save them
        $phpLocResults = simplexml_load_file($xmlFile);
        $this->storePhpLocDataInDatabase($messageData->versionId, $phpLocResults->children());

        $this->acknowledgeMessage($message);

        $this->getLogger()->info('Finish processing message', (array)$messageData);
    }

    /**
     * Stores the result of phploc command to database
     *
     * @param integer $versionId
     * @param \SimpleXMLElement $phpLocResults
     * @return void
     */
    private function storePhpLocDataInDatabase($versionId, $phpLocResults)
    {
        $data = array(
            'version' => (int)$versionId,
            'directories' => (int)$phpLocResults->directories,
            // Directories
            'files' => (int)$phpLocResults->files,
            // Files
            'loc' => (int)$phpLocResults->loc,
            // Lines of Code (LOC)
            'cloc' => (int)$phpLocResults->cloc,
            // Comment Lines of Code (CLOC)
            'ncloc' => (int)$phpLocResults->ncloc,
            // Non-Comment Lines of Code (NCLOC)
            'ccn' => (int)$phpLocResults->ccn,
            // Cyclomatic Complexity
            'ccn_methods' => (int)$phpLocResults->ccnMethods,
            // Cyclomatic Complexity of methods
            'interfaces' => (int)$phpLocResults->interfaces,
            // Interfaces
            'traits' => (int)$phpLocResults->traits,
            // Traits
            'classes' => (int)$phpLocResults->classes,
            // Classes
            'abstract_classes' => (int)$phpLocResults->abstractClasses,
            // Abstract classes
            'concrete_classes' => (int)$phpLocResults->concreteClasses,
            // Concrete classes
            'anonymous_functions' => (int)$phpLocResults->anonymousFunctions,
            // Anonymous functions
            'functions' => (int)$phpLocResults->functions,
            // Functions
            'methods' => (int)$phpLocResults->methods,
            // Methods
            'public_methods' => (int)$phpLocResults->publicMethods,
            // Public methods
            'non_public_methods' => (int)$phpLocResults->nonPublicMethods,
            // Non public methods
            'non_static_methods' => (int)$phpLocResults->nonStaticMethods,
            // Non static methods
            'static_methods' => (int)$phpLocResults->staticMethods,
            // Static methods
            'constants' => (int)$phpLocResults->constants,
            // Constants
            'class_constants' => (int)$phpLocResults->classConstants,
            // Class constants
            'global_constants' => (int)$phpLocResults->globalConstants,
            // Global constants
            'test_classes' => (int)$phpLocResults->testClasses,
            // Test classes
            'test_methods' => (int)$phpLocResults->testMethods,
            // Test methods
            'ccn_by_lloc' => (double)$phpLocResults->ccnByLloc,
            // Cyclomatic Complexity / LLOC
            'ccn_by_nom' => (double)$phpLocResults->ccnByNom,
            // Cyclomatic Complexity / Number of Methods
            'namespaces' => (int)$phpLocResults->namespaces,
            // Namespaces
            'lloc' => (int)$phpLocResults->lloc,
            // Logical Lines of Code (LLOC)
            'lloc_classes' => (int)$phpLocResults->llocClasses,
            // Logical Lines of Code (LLOC) in Classes
            'lloc_functions' => (int)$phpLocResults->llocFunctions,
            // Logical Lines of Code (LLOC) in Functions
            'lloc_global' => (int)$phpLocResults->llocGlobal,
            // Logical Lines of Code (LLOC) Not in classes or functions
            'named_functions' => (int)$phpLocResults->namedFunctions,
            // Named functions
            'lloc_by_noc' => (double)$phpLocResults->llocByNoc,
            // Logical Lines of Code (LLOC) - Classes - Average Class Length
            'lloc_by_nom' => (double)$phpLocResults->llocByNom,
            // Logical Lines of Code (LLOC) - Classes - Average Method Length
            'lloc_by_nof' => (double)$phpLocResults->llocByNof,
            // Logical Lines of Code (LLOC) - Functions - Average Function Length
            'method_calls' => (int)$phpLocResults->methodCalls,
            // Dependencies - Method Calls
            'static_method_calls' => (int)$phpLocResults->staticMethodCalls,
            // Dependencies - Method Calls (static methods)
            'instance_method_calls' => (int)$phpLocResults->instanceMethodCalls,
            // Dependencies - Method Calls (non static)
            'attribute_accesses' => (int)$phpLocResults->attributeAccesses,
            // Dependencies - Attribute Accesses
            'static_attribute_accesses' => (int)$phpLocResults->staticAttributeAccesses,
            // Dependencies - Attribute Accesses (static)
            'instance_attribute_accesses' => (int)$phpLocResults->instanceAttributeAccesses,
            // Dependencies - Attribute Accesses (non static)
            'global_accesses' => (int)$phpLocResults->globalAccesses,
            // Dependencies - Global Accesses
            'global_variable_accesses' => (int)$phpLocResults->globalVariableAccesses,
            // Dependencies - Global Accesses - Global Variables
            'super_global_variable_accesses' => (int)$phpLocResults->superGlobalVariableAccesses,
            // Dependencies - Global Accesses - Super-Global Variables
            'global_constant_accesses' => (int)$phpLocResults->globalConstantAccesses,
            // Dependencies - Global Accesses - Global Constants
        );

        $insertedId = $this->getDatabase()->insertRecord('phploc', $data);

        $msg = 'Stored analzye results for version record in PHPLoc record';
        $context = array('versionId' => $versionId, 'phpLocRecord' => $insertedId);
        $this->getLogger()->info($msg, $context);
    }

    /**
     * Receives a single phploc data record of the database
     *
     * @param integer $id
     * @return bool|array
     */
    private function getPhpLocDataFromDatabase($id)
    {
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
