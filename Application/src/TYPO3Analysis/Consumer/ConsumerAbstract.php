<?php
/**
 * @todo adds a description (license text, description of this class / file, etc)
 */
namespace TYPO3Analysis\Consumer;

abstract class ConsumerAbstract implements ConsumerInterface {

    /**
     * The queue name
     *
     * @var string
     */
    private $queue = '';

    /**
     * The exchange name
     *
     * @var string
     */
    private $exchange = '';

    /**
     * The routing key
     *
     * @var string
     */
    private $routing = '';

    /**
     * Database connection
     *
     * @var \TYPO3Analysis\Helper\Database
     */
    private $database = null;

    /**
     * Config
     *
     * @var array
     */
    private $config = array();

    /**
     * MessageQueue connection
     *
     * @var \TYPO3Analysis\Helper\MessageQueue
     */
    private $messageQueue = null;

    /**
     * Gets the queue name
     *
     * @return string
     */
    public function getQueue() {
        return $this->queue;
    }

    /**
     * Sets the queue name
     *
     * @param string $queue
     * @return void
     */
    public function setQueue($queue) {
        $this->queue = $queue;
    }

    /**
     * Gets the routing key
     *
     * @return string
     */
    public function getRouting() {
        return $this->routing;
    }

    /**
     * Sets the routing key
     *
     * @param string $routing
     * @return void
     */
    public function setRouting($routing) {
        $this->routing = $routing;
    }

    /**
     * Gets the exchange name
     *
     * @return string
     */
    public function getExchange() {
        return $this->exchange;
    }

    /**
     * Sets the exchange name
     *
     * @param string $exchange
     * @return void
     */
    public function setExchange($exchange) {
        $this->exchange = $exchange;
    }

    /**
     * Gets the consumer tag
     *
     * @return string
     */
    public function getConsumerTag() {
        return get_class($this);
    }

    /**
     * Gets the database
     *
     * @return \TYPO3Analysis\Helper\Database
     */
    public function getDatabase() {
        return $this->database;
    }

    /**
     * Sets the database
     *
     * @param \TYPO3Analysis\Helper\Database $database
     * @return void
     */
    public function setDatabase($database) {
        $this->database = $database;
    }

    /**
     * Gets the config
     *
     * @return array
     */
    public function getConfig() {
        return $this->config;
    }

    /**
     * Sets the config
     *
     * @param array $config
     * @return void
     */
    public function setConfig(array $config) {
        $this->config = $config;
    }

    /**
     * Gets the message queue
     *
     * @return \TYPO3Analysis\Helper\MessageQueue
     */
    public function getMessageQueue() {
        return $this->messageQueue;
    }

    /**
     * Sets the message queue
     *
     * @param \TYPO3Analysis\Helper\MessageQueue $messageQueue
     * @return void
     */
    public function setMessageQueue($messageQueue) {
        $this->messageQueue = $messageQueue;
    }

    /**
     * Acknowledges a message of a consumer to the message queue server
     *
     * @param $message
     * @return void
     */
    protected function acknowledgeMessage($message) {
        $message->delivery_info['channel']->basic_ack($message->delivery_info['delivery_tag']);
    }
}