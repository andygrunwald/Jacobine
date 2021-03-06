<?php
/**
 * This file is part of the Jacobine package.
 *
 * (c) Andreas Grunwald <andygrunwald@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Jacobine\Consumer;

use \Psr\Log\LoggerInterface;

/**
 * Interface ConsumerInterface
 *
 * Interface of a single consumer.
 * Every consumer must implement this interface.
 * With this it is possible to start this consumer via our consumer command `php console jacobine:consumer CONSUMERNAME`
 *
 * @package Jacobine\Consumer
 * @author Andy Grunwald <andygrunwald@gmail.com>
 */
interface ConsumerInterface
{

    /**
     * Gets a description of the consumer
     *
     * @return string
     */
    public function getDescription();

    /**
     * Initialize the consumer.
     * E.g. sets the queue and routing key
     *
     * @return void
     */
    public function initialize();

    /**
     * Method to consume a single message delivered by the message broker.
     * The message broker will put its message into this method and the message will be consumed.
     *
     * In the regular case this consume method is not overwritten by any special implementation of a consumer.
     * It provides a general handling for a single process() to provide a more convenient handling of messages.
     *
     * @param \stdClass $message
     * @return void
     */
    public function consume($message);

    /**
     * Returns the queue options
     *
     * @return array
     */
    public function getQueueOptions();

    /**
     * Sets a bulk of queue options
     *
     * @param array $queue
     * @return void
     */
    public function setQueueOptions(array $queue);

    /**
     * Sets a single queue option
     *
     * @param string $name
     * @param mixed $value
     * @return void
     */
    public function setQueueOption($name, $value);

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
     * Returns the exchange options
     *
     * @return array
     */
    public function getExchangeOptions();

    /**
     * Sets a bulk of exchange options
     *
     * @param array $exchange
     * @return void
     */
    public function setExchangeOptions(array $exchange);

    /**
     * Sets a single exchange option
     *
     * @param string $name
     * @param mixed $value
     * @return void
     */
    public function setExchangeOption($name, $value);

    /**
     * Gets the consumer tag
     *
     * @return string
     */
    public function getConsumerTag();

    /**
     * Gets the database
     *
     * @return \Jacobine\Component\Database\Database
     */
    public function getDatabase();

    /**
     * Sets the database
     *
     * @param \Jacobine\Component\Database\Database $database
     * @return void
     */
    public function setDatabase($database);

    /**
     * Gets the message queue
     *
     * @return \Jacobine\Component\AMQP\MessageQueue
     */
    public function getMessageQueue();

    /**
     * Sets the message queue
     *
     * @param \Jacobine\Component\AMQP\MessageQueue $messageQueue
     * @return void
     */
    public function setMessageQueue($messageQueue);

    /**
     * Gets the logger
     *
     * @return \Monolog\Logger
     */
    public function getLogger();

    /**
     * Sets the logger
     *
     * @param LoggerInterface $logger
     * @return void
     */
    public function setLogger(LoggerInterface $logger);

    /**
     * Sets the message
     *
     * @param \stdClass $message
     * @return void
     */
    public function setMessage($message);

    /**
     * Gets the message
     *
     * @return \stdClass
     */
    public function getMessage();

    /**
     * Enable dead lettering
     *
     * @return void
     */
    public function enableDeadLettering();

    /**
     * Checks if dead lettering is enabled
     *
     * @return boolean
     */
    public function isDeadLetteringEnabled();

    /**
     * Disable dead lettering
     *
     * @return void
     */
    public function disableDeadLettering();
}
