<?php
/**
 * @todo adds a description (license text, description of this class / file, etc)
 */
namespace TYPO3Analysis\Consumer;

interface ConsumerInterface {

    /**
     * Initialize the consumer.
     * E.g. sets the queue and routing key
     *
     * @return void
     */
    public function initialize();

    /**
     * The logic of the consumer
     *
     * @param stdClass $message
     * @return void
     */
    public function process($message);

    /**
     * Gets the queue name
     *
     * @return string
     */
    public function getQueue();

    /**
     * Sets the queue name
     *
     * @param string $queue
     * @return void
     */
    public function setQueue($queue);

    /**
     * Gets the routing key
     *
     * @return string
     */
    public function getRouting();

    /**
     * Sets the routing key
     *
     * @param string $routing
     * @return void
     */
    public function setRouting($routing);

    /**
     * Gets the exchange name
     *
     * @return string
     */
    public function getExchange();

    /**
     * Sets the exchange name
     *
     * @param string $exchange
     * @return void
     */
    public function setExchange($exchange);

    /**
     * Gets the consumer tag
     *
     * @return string
     */
    public function getConsumerTag();

    /**
     * Gets the database
     *
     * @return \TYPO3Analysis\Helper\Database
     */
    public function getDatabase();

    /**
     * Sets the database
     *
     * @param \TYPO3Analysis\Helper\Database $database
     * @return void
     */
    public function setDatabase($database);

    /**
     * Gets the HTTP client
     *
     * @return \Buzz\Browser
     */
    public function getHttpClient();

    /**
     * Sets the HTTP client
     *
     * @param \Buzz\Browser $httpClient
     * @return void
     */
    public function setHttpClient($httpClient);

    /**
     * Gets the config
     *
     * @return array
     */
    public function getConfig();

    /**
     * Sets the config
     *
     * @param array $config
     * @return void
     */
    public function setConfig(array $config);

    /**
     * Gets the message queue
     *
     * @return \TYPO3Analysis\Helper\MessageQueue
     */
    public function getMessageQueue();

    /**
     * Sets the message queue
     *
     * @param \TYPO3Analysis\Helper\MessageQueue $messageQueue
     * @return void
     */
    public function setMessageQueue($messageQueue);
}