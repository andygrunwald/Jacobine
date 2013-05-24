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
     * @throws \Exception
     */
    public function process($message) {
        $messageData = json_decode($message->body);

        // If there is no directory to analyse, exit here
        if (is_dir($messageData->checkoutDir) !== true) {
            $this->getLogger()->critical('Directory does not exist', array('directory' => $messageData->checkoutDir));
            $msg = sprintf('Directory %s does not exist', $messageData->checkoutDir);
            throw new \Exception($msg, 1369435176);
        }

        $this->getLogger()->info('Analyze directory with CVSAnaly', array('directory' => $messageData->checkoutDir));

        $command = $this->buildCVSAnalyCommand($this->getConfig(), $messageData->project, $messageData->checkoutDir);
        $this->executeCommand($command);

        $this->acknowledgeMessage($message);
    }

    /**
     * Builds the CVSAnaly command
     *
     * @param array     $config
     * @param string    $project
     * @param string    $directory
     * @return string
     */
    private function buildCVSAnalyCommand($config, $project, $directory) {
        $projectConfig = $config['Projects'][$project];

        $command = escapeshellcmd($config['Application']['CVSAnaly']['Binary']);
        $command .= ' --db-driver ' . escapeshellarg('mysql');
        $command .= ' --db-hostname ' . escapeshellarg($config['MySQL']['Host']);
        $command .= ' --db-user ' . escapeshellarg($config['MySQL']['Username']);
        $command .= ' --db-password ' . escapeshellarg($config['MySQL']['Password']);
        $command .= ' --db-database ' . escapeshellarg($projectConfig['MySQL']['Database']);
        $command .= ' --metrics-all';
        $command .= ' ' . escapeshellarg($directory);

        return $command;
    }
}