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

/**
 * Class MessageQueue
 *
 * Message queue abstraction.
 * In theory we can talk to every message queue broker which supports AMQP.
 * But in the normal case we use RabbitMQ.
 * This class offers exchange, queue, binding, channel and message handling.
 *
 * @package TYPO3Analysis\Helper
 * @author Andy Grunwald <andygrunwald@gmail.com>
 */
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
     * Default queue options
     *
     * @var array
     */
    protected $defaultQueueOptions = [
        'name' => '',
        'passive' => false,
        'durable' => false,
        'exclusive' => false,
        'auto_delete' => true,
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
        'auto_delete' => true,
        'internal' => false,
        'nowait' => false,
        'arguments' => null,
        'ticket' => null
    ];

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
     * Sends a new message to message queue server
     *
     * @param mixed $message
     * @param array $exchangeOptions
     * @param array $queueOptions
     * @param string $routing
     * @param bool $doNotDeclare If false exchange and message will be declared, if true the message will be just sent
     */
    public function sendMessage($message, array $exchangeOptions, $queueOptions, $routing = '', $doNotDeclare = false)
    {
        if (is_array($message) === true) {
            $message = json_encode($message);
        }

        if ($doNotDeclare === false && $exchangeOptions) {
            $this->declareExchange($exchangeOptions);
        }

        if ($doNotDeclare === false && $queueOptions) {
            $this->declareQueue($queueOptions);
        }

        $message = $this->factory->createMessage($message, ['content_type' => 'text/plain']);
        /* @var \PhpAmqpLib\Message\AMQPMessage $message */
        $this->getChannel()->basic_publish($message, $exchangeOptions['name'], $routing);
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
    public function basicConsume(array $exchangeOptions, array $queueOptions, $deadLettering, $routing, $consumerTag, array $callback)
    {

        // Declare all needed stuff regarding dead lettering
        if ($deadLettering) {
            // Setup dead letter exchange
            $deadLetterExchangeOptions = $exchangeOptions;
            $deadLetterExchangeOptions['name'] .= '.deadletter';
            //$deadLetterExchangeOptions['type'] = 'direct';
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
