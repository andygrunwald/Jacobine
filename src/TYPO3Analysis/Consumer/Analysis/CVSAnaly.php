<?php
/**
 * @todo adds a description (license text, description of this class / file, etc)
 */
namespace TYPO3Analysis\Consumer\Analysis;

use TYPO3Analysis\Consumer\ConsumerAbstract;

class CVSAnaly extends ConsumerAbstract {

    /**
     * Gets a description of the consumer
     *
     * @return string
     */
    public function getDescription() {
        return 'Executes the CVSAnaly analysis on a given folder and stores the results in database.';
    }

    /**
     * Initialize the consumer.
     * Sets the queue and routing key
     *
     * @return void
     */
    public function initialize() {
        $this->setQueue('analysis.cvsanaly');
        $this->setRouting('analysis.cvsanaly');
    }

    /**
     * The logic of the consumer
     *
     * @param \stdClass     $message
     * @return void
     */
    public function process($message) {
        $this->setMessage($message);
        $messageData = json_decode($message->body);

        // If there is no directory to analyse, exit here
        if (is_dir($messageData->checkoutDir) !== true) {
            $this->getLogger()->critical('Directory does not exist', array('directory' => $messageData->checkoutDir));
            $this->acknowledgeMessage($message);
            return;
        }

        $this->getLogger()->info('Analyze directory with CVSAnaly', array('directory' => $messageData->checkoutDir));

        try {
            $extensions = $this->getCVSAnalyExtensions($this->getConfig());
        } catch (\Exception $e) {
            $context = array(
                'dir' => $messageData->checkoutDir
            );
            $this->getLogger()->error('CVSAnaly extensions can not be received', $context);

            $this->acknowledgeMessage($this->getMessage());
            return;
        }

        $command = $this->buildCVSAnalyCommand($this->getConfig(), $messageData->project, $messageData->checkoutDir, $extensions);
        try {
            $this->executeCommand($command, true, array('PYTHONPATH'));
        } catch (\Exception $e) {
            $context = array(
                'dir' => $messageData->checkoutDir,
                'message' => $e->getMessage()
            );
            $this->getLogger()->error('CVSAnaly command failed', $context);

            $this->acknowledgeMessage($this->getMessage());
            return;
        }

        $this->acknowledgeMessage($message);
    }

    /**
     * Builds the CVSAnaly command
     *
     * @param array     $config
     * @param string    $project
     * @param string    $directory
     * @param string    $extensions
     * @return string
     */
    private function buildCVSAnalyCommand($config, $project, $directory, $extensions) {
        $projectConfig = $config['Projects'][$project];

        $configFile = rtrim(dirname(CONFIG_FILE), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $configFile .= $projectConfig['CVSAnaly']['ConfigFile'];

        $command = escapeshellcmd($config['Application']['CVSAnaly']['Binary']);
        $command .= ' --config-file ' . escapeshellarg($configFile);
        $command .= ' --db-driver ' . escapeshellarg('mysql');
        $command .= ' --db-hostname ' . escapeshellarg($config['MySQL']['Host']);
        $command .= ' --db-user ' . escapeshellarg($config['MySQL']['Username']);
        $command .= ' --db-password ' . escapeshellarg($config['MySQL']['Password']);
        $command .= ' --db-database ' . escapeshellarg($projectConfig['MySQL']['Database']);
        $command .= ' --extensions ' . escapeshellarg($extensions);
        $command .= ' --metrics-all';
        $command .= ' ' . escapeshellarg($directory);

        return $command;
    }

    /**
     * Returns all active and usable extensions of CVSAnaly
     *
     * @param array $config
     * @return string
     */
    private function getCVSAnalyExtensions($config) {
        $command = escapeshellcmd($config['Application']['CVSAnaly']['Binary']);
        $command .= ' --list-extensions';

        // Hardcoded extensions, because some extensions may not work correct
        // With this way we can enable / disable various extensions
        // and know that all works fine :)
        // Later on we try to fix all extensions in CVSAnaly to work with all repositories
        // $extensions = $this->executeCommand($command);
        // $extensions = implode('', $extensions);
        $extensions = 'Months, Weeks';

        if ($extensions) {
            $extensions = str_replace(' ', '', $extensions);
        }

        return $extensions;
    }
}