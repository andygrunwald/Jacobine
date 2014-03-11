<?php
/**
 * This file is part of the TYPO3-Analytics package.
 *
 * (c) Andreas Grunwald <andygrunwald@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TYPO3Analysis\Helper;

class MessageQueue
{

    /**
     * @var AMQPFactory
     */
    protected $factory;

    /**
     * Message Queue connection
     *
     * @var \PhpAmqpLib\Connection\AMQPConnection
     */
    protected $handle;

    /**
     * Message Queue channel
     *
     * @var \PhpAmqpLib\Channel\AMQPChannel
     */
    protected $channel;

    /**
     * Store of declared exchanges and queues
     *
     * @var array
     */
    protected $declared = array(
        'exchange' => array(),
        'queue' => array(),
    );

    /**
     * Constructor to set up a connection to the RabbitMQ server
     *
     * @param \PhpAmqpLib\Connection\AMQPConnection $amqpConnection
     * @param \TYPO3Analysis\Helper\AMQPFactory $amqpFactory
     * @return \TYPO3Analysis\Helper\MessageQueue
     */
    public function __construct(\PhpAmqpLib\Connection\AMQPConnection $amqpConnection, AMQPFactory $amqpFactory)
    {
        $this->handle = $amqpConnection;
        $this->factory = $amqpFactory;
        $this->renewChannel();
    }

    /**
     * Creates a new channel!
     * We do not define any QoS, because the message queue server will take care of this :)
     *
     * @return void
     */
    protected function renewChannel()
    {
        $this->channel = $this->getHandle()->channel();
    }

    /**
     * Gets the AMQPConnection
     *
     * @return null|AMQPConnection
     */
    protected function getHandle()
    {
        return $this->handle;
    }

    /**
     * Gets the channel for AMQPConnection
     *
     * @return null|\PhpAmqpLib\Channel\AMQPChannel
     */
    protected function getChannel()
    {
        return $this->channel;
    }

    /**
     * Declares a new exchange at the message queue server
     *
     * @param string $exchange
     * @param string $exchangeType
     * @return void
     */
    protected function declareExchange($exchange, $exchangeType)
    {
        if (isset($this->declared['exchange'][$exchange]) === false) {
            $this->getChannel()->exchange_declare($exchange, $exchangeType, false, true, true);
            $this->declared['exchange'][$exchange] = true;
        }
    }

    /**
     * Declares a new queue at the message queue server
     *
     * @param string $queue
     * @return void
     */
    protected function declareQueue($queue)
    {
        $this->getChannel()->queue_declare($queue, false, true, false, false);
        $this->declared['queue'][$queue] = true;
    }

    /**
     * Sends a new message to message queue server
     *
     * @param mixed $message
     * @param string $exchange
     * @param string $queue
     * @param string $routing
     * @param string $exchangeType
     */
    public function sendMessage($message, $exchange = '', $queue = '', $routing = '', $exchangeType = 'topic')
    {
        if (is_array($message) === true) {
            $message = json_encode($message);
        }

        if ($exchange) {
            $this->declareExchange($exchange, $exchangeType);
        }

        if ($queue) {
            $this->declareQueue($queue);
        }

        $message = $this->factory->createMessage($message, ['content_type' => 'text/plain']);
        /* @var \PhpAmqpLib\Message\AMQPMessage $message */
        $this->getChannel()->basic_publish($message, $exchange, $routing);
    }

    /**
     * Consumer registration.
     * Registered a new consumer at message queue server to consume messages
     *
     * @param string $exchange
     * @param string $queue
     * @param string $routing
     * @param string $consumerTag
     * @param array $callback
     * @param string $exchangeType
     * @return void
     */
    public function basicConsume($exchange, $queue, $routing, $consumerTag, array $callback, $exchangeType = 'topic')
    {
        $this->declareQueue($queue);

        if ($exchange) {
            $this->declareExchange($exchange, $exchangeType);
            $this->getChannel()->queue_bind($queue, $exchange, $routing);
        }
        $this->getChannel()->basic_consume($queue, $consumerTag, false, false, false, false, $callback);

        // Loop as long as the channel has callbacks registered
        while (count($this->getChannel()->callbacks)) {
            $this->getChannel()->wait();
        }
    }

    /**
     * Closes the message queue connection
     *
     * @return void
     */
    public function close()
    {
        #$this->getChannel()->close();
        #$this->getHandle()->close();
    }
}
