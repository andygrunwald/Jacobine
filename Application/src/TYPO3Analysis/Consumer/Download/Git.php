<?php
/**
 * @todo adds a description (license text, description of this class / file, etc)
 */
namespace TYPO3Analysis\Consumer\Download;

use TYPO3Analysis\Consumer\ConsumerAbstract;

class Git extends ConsumerAbstract {

    /**
     * Gets a description of the consumer
     *
     * @return string
     */
    public function getDescription() {
        return 'Downloads a Git repository.';
    }

    /**
     * Initialize the consumer.
     * Sets the queue and routing key
     *
     * @return void
     */
    public function initialize() {
        $this->setQueue('download.git');
        $this->setRouting('download.git');
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

        $record = $this->getGitwebFromDatabase($messageData->id);
        $context = array('id' => $messageData->id);

        // If the record does not exists in the database exit here
        if ($record === false) {
            $this->getLogger()->info('Record does not exist in gitweb table', $context);
            $this->acknowledgeMessage($message);
            return;
        }

        $config = $this->getConfig();
        $projectConfig = $config['Projects'][$messageData->project];
        $checkoutPath = $projectConfig['GitCheckoutPath'];
        $checkoutPath = rtrim($checkoutPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        $search = array('/', '.git', '.');
        $replace = array('_', '', '-');
        $checkoutPath .= str_replace($search, $replace, $record['name']);

        $gitDirInCheckoutPath = $checkoutPath . DIRECTORY_SEPARATOR . '.git';

        try {
            if (is_dir($checkoutPath) === true && is_dir($gitDirInCheckoutPath) === true) {
                $this->gitUpdate($config['Application']['Git']['Binary'], $checkoutPath);
            } else {
                $this->gitClone($config['Application']['Git']['Binary'], $record['git'], $checkoutPath);
            }
        } catch (\Exception $e) {
            $this->acknowledgeMessage($this->getMessage());
            return;
        }

        $this->acknowledgeMessage($message);

        // Adds new messages to queue: Analyze this via CVSAnalY
        $this->addFurtherMessageToQueue($messageData->project, $record['id'], $checkoutPath);
    }

    /**
     * Adds new messages to queue system to analyze the checkout with CVSAnalY
     *
     * @param string    $project
     * @param integer   $id
     * @param string    $dir
     * @return void
     */
    private function addFurtherMessageToQueue($project, $id, $dir) {
        $message = array(
            'project' => $project,
            'gitwebId' => $id,
            'checkoutDir' => $dir
        );

        $this->getMessageQueue()->sendMessage($message, 'TYPO3', 'analysis.cvsanaly', 'analysis.cvsanaly');
    }

    /**
     * Updates a existing git clone
     *
     * @param string    $git
     * @param string    $checkoutPath
     * @return array
     */
    private function gitUpdate($git, $checkoutPath) {
        chdir($checkoutPath);

        $context = array(
            'dir' => $checkoutPath
        );
        $this->getLogger()->info('Updating git repository', $context);

        $command = escapeshellcmd($git);
        $command .= ' checkout master';
        $this->executeCommand($command);

        $command = escapeshellcmd($git);
        $command .= ' pull';

        return $this->executeCommand($command);
    }

    /**
     * Clones a git repository
     *
     * @todo clone all branches http://stackoverflow.com/questions/67699/how-do-i-clone-all-remote-branches-with-git
     *
     * @param string    $git
     * @param string    $repository
     * @param string    $checkoutPath
     * @return array
     */
    private function gitClone($git, $repository, $checkoutPath) {
        mkdir($checkoutPath, 0777, true);

        $command = escapeshellcmd($git);
        $command .= ' clone --recursive';
        $command .= ' ' . escapeshellarg($repository);
        $command .= ' ' . escapeshellarg($checkoutPath);

        $context = array(
            'git' => $repository,
            'dir' => $checkoutPath
        );
        $this->getLogger()->info('Checkout git repository', $context);

        return $this->executeCommand($command);
    }

    /**
     * Receives a single gitweb record of the database
     *
     * @param integer   $id
     * @return bool|array
     */
    private function getGitwebFromDatabase($id) {
        $fields = array('id', 'name', 'git');
        $rows = $this->getDatabase()->getRecords($fields, 'gitweb', array('id' => $id), '', '', 1);

        $row = false;
        if (count($rows) === 1) {
            $row = array_shift($rows);
            unset($rows);
        }

        return $row;
    }
}