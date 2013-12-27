<?php
/**
 * @todo adds a description (license text, description of this class / file, etc)
 */
namespace TYPO3Analysis\Consumer\Crawler;

use TYPO3Analysis\Consumer\ConsumerAbstract;

class NNTP extends ConsumerAbstract {

    /**
     * Gets a description of the consumer
     *
     * @return string
     */
    public function getDescription() {
        return 'Crawls a NNTP server for groups and add further messages for every single group';
    }

    /**
     * Initialize the consumer.
     * Sets the queue and routing key
     *
     * @return void
     */
    public function initialize() {
        $this->setQueue('crawler.nntp');
        $this->setRouting('crawler.nntp');
    }

    /**
     * The logic of the consumer
     *
     * @param \stdClass $message
     * @return null|void
     */
    public function process($message) {
        $this->setMessage($message);
        $messageData = json_decode($message->body);
        $nntpConfig = $messageData->config;

        $this->getLogger()->info('Receiving message', (array) $messageData);

        if (is_object($nntpConfig) === false || property_exists($nntpConfig, 'Host') === false) {
            $context = array('config' => $nntpConfig);
            $this->getLogger()->critical('NNTP configuration does not exist or is incomplete', $context);
            $this->acknowledgeMessage($message);
            return;
        }

        // Bootstrap NNTP Client
        $nntpClient = new \Net_NNTP_Client();
        $nntpClient->connect($nntpConfig->Host);

        $this->getLogger()->info('Requesting groups', array('host' => $nntpConfig->Host));
        $groups = $nntpClient->getGroups();

        $this->getLogger()->info('Requesting group descriptions', array('host' => $nntpConfig->Host));
        $descriptions = $nntpClient->getDescriptions();

        // Looping over the groups and get the shit done!
        foreach ($groups as $group) {
            $groupSummary = $nntpClient->selectGroup($group['group']);
            $groupRecord = $this->getGroupFromDatabase($group['group']);

            if ($groupRecord === false) {
                $description = ((array_key_exists($group['group'], $descriptions) === true) ? $descriptions[$group['group']]: '');
                $id = $this->insertGroupRecord(
                    $group['group'],
                    $description,
                    $group['first'], $group['last'],
                    $groupSummary['count'],
                    $group['posting']);

            } else {
                $id = $groupRecord['id'];
                $this->getLogger()->info('NNTP group record already exists', array('group' => $group['group']));
            }

            $context = array(
                'project' => $messageData->project,
                'groupId' => $id
            );
            $this->getLogger()->info('Add nntp group to message queue "crawler.nntpgroup"', $context);
            $this->addFurtherMessageToQueue($messageData->project, $nntpConfig->Host, $id);
        }

        $this->acknowledgeMessage($message);

        $this->getLogger()->info('Finish processing message', (array) $messageData);
    }

    /**
     * Adds new messages to queue system to import a single nntp group
     *
     * @param string $project
     * @param string $host
     * @param integer $groupId
     * @return void
     */
    private function addFurtherMessageToQueue($project, $host, $groupId) {
        $message = array(
            'project' => $project,
            'host' => $host,
            'groupId' => $groupId,
        );

        $this->getMessageQueue()->sendMessage($message, 'TYPO3', 'crawler.nntpgroup', 'crawler.nntpgroup');
    }

    /**
     * Receives a single nntp_group record of the database
     *
     * @param string   $group
     * @return bool|array
     */
    private function getGroupFromDatabase($group) {
        $fields = array('id');
        $rows = $this->getDatabase()->getRecords($fields, 'nntp_group', array('name' => $group), '', '', 1);

        $row = false;
        if (count($rows) === 1) {
            $row = array_shift($rows);
            unset($rows);
        }

        return $row;
    }

    /**
     * Inserts a new nntp_group record to database
     *
     * @param string $name
     * @param string $description
     * @param integer $first
     * @param integer $last
     * @param integer $cnt
     * @param string $posting
     * @return void
     */
    private function insertGroupRecord($name, $description, $first, $last, $cnt, $posting) {
        $data = array(
            'name' => $name,
            'description' => $description,
            'first' => $first,
            'last' => $last,
            'cnt' => $cnt,
            'posting' => $posting
        );

        $this->getLogger()->info('Inserted new nntp_group record', $data);
        return $this->getDatabase()->insertRecord('nntp_group', $data);
    }
}