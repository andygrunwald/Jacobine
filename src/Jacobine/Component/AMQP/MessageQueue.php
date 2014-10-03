<?php
/**
 * This file is part of the Jacobine package.
 *
 * (c) Andreas Grunwald <andygrunwald@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Jacobine\Component\AMQP;

/**
 * Class MessageQueue
 *
 * Message queue abstraction.
 * In theory we can talk to every message queue broker which supports AMQP.
 * But in the normal case we use RabbitMQ.
 * This class offers exchange, queue, binding, channel and message handling.
 *
 * @package Jacobine\Component\AMQP
 * @author Andy Grunwald <andygrunwald@gmail.com>
 */
class MessageQueue
{

    /**
     * @var \Jacobine\Component\AMQP
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
    protected $declared = [
        'exchange' => [],
        'queue' => [],
    ];

    /**
     * Default queue options
     *
     * @var array
     */
    protected $defaultQueueOptions = [
        'name' => '',
        'passive' => false,
        'durable' => false,
        'exclusive' => false,
        'auto_delete' => false,
        'nowait' => false,
        'arguments' => null,
        'ticket' => null
    ];

    /**
     * Default exchange options
     *
     * @var array
     */
    protected $defaultExchangeOptions = [
        'name' => '',
        'type' => 'topic',
        'passive' => false,
        'durable' => false,
        'auto_delete' => false,
        'internal' => false,
        'nowait' => false,
        'arguments' => null,
        'ticket' => null
    ];

    /**
     * Constructor to set up a connection to the RabbitMQ server
     *
     * @param \PhpAmqpLib\Connection\AMQPConnection $amqpConnection
     * @param \Jacobine\Component\AMQP\AMQPFactory $amqpFactory
     * @return \Jacobine\Component\AMQP\MessageQueue
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
     * @param array $exchange
     * @return void
     */
    protected function declareExchange(array $exchange)
    {
        if (isset($this->declared['exchange'][$exchange['name']]) === false) {
            $this->getChannel()->exchange_declare(
                $exchange['name'],
                $exchange['type'],
                $exchange['passive'],
                $exchange['durable'],
                $exchange['auto_delete'],
                $exchange['internal'],
                $exchange['nowait'],
                $exchange['arguments'],
                $exchange['ticket']
            );
            $this->declared['exchange'][$exchange['name']] = true;
        }
    }

    /**
     * Declares a new queue at the message queue server
     *
     * @param array $queue
     * @return void
     */
    protected function declareQueue(array $queue)
    {
        $this->getChannel()->queue_declare(
            $queue['name'],
            $queue['passive'],
            $queue['durable'],
            $queue['exclusive'],
            $queue['auto_delete'],
            $queue['nowait'],
            $queue['arguments'],
            $queue['ticket']
        );
        $this->declared['queue'][$queue['name']] = true;
    }

    /**
     * Sends a message to message queue broker.
     *
     * With the usage of this method it is important that the delivered message will alive.
     * The exchange and the queue will be created if they do not exists before the message will be send.
     *
     * If it is not important that those elements exists please use $this->sendSimpleMessage().
     *
     * @param mixed $message
     * @param array $exchangeOptions
     * @param array $queueOptions
     * @param string $routing
     * @return void
     */
    public function sendExtendedMessage($message, array $exchangeOptions, array $queueOptions, $routing)
    {
        $this->declareExchange($exchangeOptions);
        $this->declareQueue($queueOptions);

        $this->sendSimpleMessage($message, $exchangeOptions['name'], $routing);
    }

    /**
     * Sends a simple message to the message queue broker.
     *
     * With the usage of this method it is irrelevant if the exchange, queue and / or binding exists.
     * If those elements won`t exists the message will be lost.
     * If you want to be save that the message won`t be lost, please use $this->sendExtendedMessage()
     * to send a message.
     *
     * @param mixed $message
     * @param string $exchangeName
     * @param string $routing
     * @return void
     */
    public function sendSimpleMessage($message, $exchangeName, $routing)
    {
        $message = $this->encodeMessage($message);

        $message = $this->factory->createMessage($message, ['content_type' => 'text/plain']);
        /* @var \PhpAmqpLib\Message\AMQPMessage $message */
        $this->getChannel()->basic_publish($message, $exchangeName, $routing);
    }

    /**
     * Transforms a message into a string, if it is an array
     *
     * @param mixed $message
     * @return string
     */
    protected function encodeMessage($message)
    {
        if (is_array($message) === true) {
            $message = json_encode($message);
        }

        return $message;
    }

    /**
     * Consumer registration.
     * Registered a new consumer at message queue server to consume messages
     *
     * @param array $exchangeOptions
     * @param array $queueOptions
     * @param boolean $deadLettering
     * @param string $routing
     * @param string $consumerTag
     * @param array $callback
     * @return void
     */
    public function basicConsume(
        array $exchangeOptions,
        array $queueOptions,
        $deadLettering,
        $routing,
        $consumerTag,
        array $callback
    ) {
        // Declare all needed stuff regarding dead lettering
        if ($deadLettering) {
            // Setup dead letter exchange
            $deadLetterExchangeOptions = $exchangeOptions;
            $deadLetterExchangeOptions['name'] .= '.deadletter';
            $this->declareExchange($deadLetterExchangeOptions);

            // Setup dead letter queue
            $deadLetterQueueOptions = $queueOptions;
            $deadLetterQueueOptions['name'] .= '.deadletter';
            $this->declareQueue($deadLetterQueueOptions);

            // Bind them
            $this->getChannel()->queue_bind(
                $deadLetterQueueOptions['name'],
                $deadLetterExchangeOptions['name'],
                $routing
            );

            // Extend the original queue with the dead letter exchange
            $queueOptions['arguments']['x-dead-letter-exchange'] = ['S', $deadLetterExchangeOptions['name']];
        }

        $this->declareQueue($queueOptions);

        if ($exchangeOptions) {
            $this->declareExchange($exchangeOptions);
            $this->getChannel()->queue_bind($queueOptions['name'], $exchangeOptions['name'], $routing);
        }

        $this->getChannel()->basic_consume($queueOptions['name'], $consumerTag, false, false, false, false, $callback);

        // Loop as long as the channel has callbacks registered
        while (count($this->getChannel()->callbacks)) {
            $this->getChannel()->wait();
        }
    }

    /**
     * Returns the default queue options
     *
     * @return array
     */
    public function getDefaultQueueOptions()
    {
        return $this->defaultQueueOptions;
    }

    /**
     * Returns the default exchange options
     *
     * @return array
     */
    public function getDefaultExchangeOptions()
    {
        return $this->defaultExchangeOptions;
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
