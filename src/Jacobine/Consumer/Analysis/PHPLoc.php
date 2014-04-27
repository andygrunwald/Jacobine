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
use Jacobine\Helper\ProcessFactory;
use Symfony\Component\Process\ProcessUtils;

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
 * @package Jacobine\Consumer\Analysis
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

        /** @var \Symfony\Component\Process\Process $process */
        list($process, $exception) = $this->executePHPLoc($messageData->directory);
        if ($exception !== null || $process->isSuccessful() === false) {
            $context = $this->getContextOfCommand($process, $exception);
            $this->getLogger()->critical('PHPLoc command failed', $context);
            $this->rejectMessage($this->getMessage());
            return;
        }

        $xmlOutput = $process->getOutput();
        if (empty($xmlOutput) === true) {
            $context = ['commandLine' => $process->getCommandLine()];
            $this->getLogger()->critical('phploc does not returned a result', $context);
            $this->rejectMessage($message);
            return;
        }

        // Get PHPLoc results and save them
        $phpLocResults = simplexml_load_string($xmlOutput);
        $this->storePhpLocDataInDatabase($messageData->versionId, $phpLocResults->children());

        $this->acknowledgeMessage($message);

        $this->getLogger()->info('Finish processing message', (array) $messageData);
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
            'version' => (int) $versionId,
            // Directories
            'directories' => (int) $phpLocResults->directories,
            // Files
            'files' => (int) $phpLocResults->files,
            // Size > Lines of Code (LOC)
            'loc' => (int) $phpLocResults->loc,
            // Size > Comment Lines of Code (CLOC)
            'cloc' => (int) $phpLocResults->cloc,
            // Size > Non-Comment Lines of Code (NCLOC)
            'ncloc' => (int) $phpLocResults->ncloc,
            // Cyclomatic Complexity
            // Sum of the complexity of the whole application
            // Value only in XML output of PHPLoc
            // Used to calculate $count['ccnByLloc'] ($count['ccn'] / $count['lloc'];)
            'ccn' => (int) $phpLocResults->ccn,
            // Cyclomatic Complexity of methods
            // Sum of the complexity of all methods
            // Value only in XML output of PHPLoc
            // Used to calculate $count['ccnByNom'] ( = ($count['methods'] + $count['ccnMethods']) / $count['methods'];)
            'ccn_methods' => (int) $phpLocResults->ccnMethods,
            // Structure > Interfaces
            'interfaces' => (int) $phpLocResults->interfaces,
            // Structure > Traits
            'traits' => (int) $phpLocResults->traits,
            // Structure > Classes
            'classes' => (int) $phpLocResults->classes,
            // Structure > Classes > Abstract Classes
            'abstract_classes' => (int) $phpLocResults->abstractClasses,
            // Structure > Classes > Concrete Classes
            'concrete_classes' => (int) $phpLocResults->concreteClasses,
            // Structure > Functions > Anonymous Functions
            'anonymous_functions' => (int) $phpLocResults->anonymousFunctions,
            // Structure > Functions
            'functions' => (int) $phpLocResults->functions,
            // Structure > Methods
            'methods' => (int) $phpLocResults->methods,
            // Structure > Methods > Visibility > Public Method
            'public_methods' => (int) $phpLocResults->publicMethods,
            // Structure > Methods > Visibility > Non-Public Methods
            'non_public_methods' => (int) $phpLocResults->nonPublicMethods,
            // Structure > Methods > Scope > Non-Static Methods
            'non_static_methods' => (int) $phpLocResults->nonStaticMethods,
            // Structure > Methods > Scope > Static Methods
            'static_methods' => (int) $phpLocResults->staticMethods,
            // Structure > Constants
            'constants' => (int) $phpLocResults->constants,
            // Structure > Constants > Class Constants
            'class_constants' => (int) $phpLocResults->classConstants,
            // Structure > Constants > Global Constants
            'global_constants' => (int) $phpLocResults->globalConstants,
            // Tests > Classes
            'test_classes' => (int) $phpLocResults->testClasses,
            // Tests > Methods
            'test_methods' => (int) $phpLocResults->testMethods,
            // Complexity > Cyclomatic Complexity / LLOC
            'ccn_by_lloc' => (double) $phpLocResults->ccnByLloc,
            // Complexity > Cyclomatic Complexity / Number of Methods
            'ccn_by_nom' => (double) $phpLocResults->ccnByNom,
            // Structure > Namespaces
            'namespaces' => (int) $phpLocResults->namespaces,
            // Size > Logical Lines of Code (LLOC)
            'lloc' => (int) $phpLocResults->lloc,
            // Size > Logical Lines of Code (LLOC) > Classes
            'lloc_classes' => (int) $phpLocResults->llocClasses,
            // Size > Logical Lines of Code (LLOC) > Functions
            'lloc_functions' => (int) $phpLocResults->llocFunctions,
            // Size > Logical Lines of Code (LLOC) > Not in classes or functions
            'lloc_global' => (int) $phpLocResults->llocGlobal,
            // Structure > Functions > Named Functions
            'named_functions' => (int) $phpLocResults->namedFunctions,
            // Size > Logical Lines of Code (LLOC) > Classes > Average Class Length
            'lloc_by_noc' => (double) $phpLocResults->llocByNoc,
            // Size > Logical Lines of Code (LLOC) > Classes > Average Method Length
            'lloc_by_nom' => (double) $phpLocResults->llocByNom,
            // Size > Logical Lines of Code (LLOC) > Functions > Average Function Length
            'lloc_by_nof' => (double) $phpLocResults->llocByNof,
            // Dependencies > Method Calls
            'method_calls' => (int) $phpLocResults->methodCalls,
            // Dependencies > Method Calls > Static
            'static_method_calls' => (int) $phpLocResults->staticMethodCalls,
            // Dependencies > Method Calls > Non-Static
            'instance_method_calls' => (int) $phpLocResults->instanceMethodCalls,
            // Dependencies > Attribute Accesses
            'attribute_accesses' => (int) $phpLocResults->attributeAccesses,
            // Dependencies > Attribute Accesses > Static
            'static_attribute_accesses' => (int) $phpLocResults->staticAttributeAccesses,
            // Dependencies > Attribute Accesses > Non-Static
            'instance_attribute_accesses' => (int) $phpLocResults->instanceAttributeAccesses,
            // Dependencies > Global Accesses
            'global_accesses' => (int) $phpLocResults->globalAccesses,
            // Dependencies > Global Accesses > Global Variables
            'global_variable_accesses' => (int) $phpLocResults->globalVariableAccesses,
            // Dependencies > Global Accesses > Super-Global Variables
            'super_global_variable_accesses' => (int) $phpLocResults->superGlobalVariableAccesses,
            // Dependencies > Global Accesses > Global Constants
            'global_constant_accesses' => (int) $phpLocResults->globalConstantAccesses,
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

    /**
     * Starts a analysis of a given $dirToAnalyze with PHPLoc
     *
     * @param string $dirToAnalyze Directory which should be analyzed by PHPLoc
     * @return array [
     *                  0 => Symfony Process object,
     *                  1 => Exception if one was thrown otherwise null,
     *                  2 => xml result file of PHPLoc
     *               ]
     */
    private function executePHPLoc($dirToAnalyze)
    {
        $dirToAnalyze = rtrim($dirToAnalyze, DIRECTORY_SEPARATOR);
        $xmlOutput = 'php://stdout';

        $config = $this->getConfig();
        $filePattern = $config['Application']['PHPLoc']['FilePattern'];

        $filePattern = ProcessUtils::escapeArgument($filePattern);
        $xmlOutput = ProcessUtils::escapeArgument($xmlOutput);
        $dirToAnalyze = ProcessUtils::escapeArgument($dirToAnalyze . DIRECTORY_SEPARATOR);

        $command = $config['Application']['PHPLoc']['Binary'];
        $command .= ' --count-tests --quiet --names ' . $filePattern;
        $command .= ' --log-xml ' . $xmlOutput . ' ' . $dirToAnalyze;

        $this->getLogger()->info('Start analyzing with PHPLoc', array('directory' => $dirToAnalyze));

        $timeout = (int) $config['Application']['PHPLoc']['Timeout'];
        $processFactory = new ProcessFactory();
        $process = $processFactory->createProcess($command, $timeout);

        $exception = null;
        try {
            $process->run();
        } catch (\Exception $exception) {
            // This catch section is empty, because we got an error handling in the caller area
            // We check not only the exception. We use the result command of the process as well
        }

        return [$process, $exception];
    }
}
