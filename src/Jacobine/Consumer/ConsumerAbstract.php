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
 * Class ConsumerAbstract
 *
 * Base implementation to fit the ConsumerInterface.
 * In general many setter, getter and basic message handling like acknowledgement are implemented.
 *
 * @package Jacobine\Consumer
 * @author Andy Grunwald <andygrunwald@gmail.com>
 */
abstract class ConsumerAbstract implements ConsumerInterface
{

    /**
     * The queue options
     *
     * @var array
     */
    private $queueOptions = [];

    /**
     * The exchange options
     *
     * @var array
     */
    private $exchangeOptions = [];

    /**
     * The routing key
     *
     * @var string
     */
    private $routing = '';

    /**
     * Bool if deadlettering is enabled
     *
     * @link http://www.rabbitmq.com/dlx.html
     *
     * @var string
     */
    private $deadLettering = false;

    /**
     * Database connection
     *
     * @var \Jacobine\Helper\Database
     */
    private $database;

    /**
     * Config
     *
     * @var array
     */
    private $config = [];

    /**
     * MessageQueue connection
     *
     * @var \Jacobine\Helper\MessageQueue
     */
    private $messageQueue;

    /**
     * Logger
     *
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * Message of consumer
     *
     * @var \stdClass
     */
    private $message;

    /**
     * Sets the message
     *
     * @param \stdClass $message
     * @return void
     */
    public function setMessage($message)
    {
        $this->message = $message;
    }

    /**
     * Gets the message
     *
     * @return \stdClass
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * Returns the queue options
     *
     * @return array
     */
    public function getQueueOptions()
    {
        return $this->queueOptions;
    }

    /**
     * Sets a bulk of queue options
     *
     * @param array $queue
     * @return void
     */
    public function setQueueOptions(array $queue)
    {
        $this->queueOptions = $queue;
    }

    /**
     * Sets a single queue option
     *
     * @param string $name
     * @param mixed $value
     * @return void
     */
    public function setQueueOption($name, $value)
    {
        $this->queueOptions[$name] = $value;
    }

    /**
     * Returns the exchange options
     *
     * @return array
     */
    public function getExchangeOptions()
    {
        return $this->exchangeOptions;
    }

    /**
     * Sets a bulk of exchange options
     *
     * @param array $exchange
     * @return void
     */
    public function setExchangeOptions(array $exchange)
    {
        $this->exchangeOptions = $exchange;
    }

    /**
     * Sets a single exchange option
     *
     * @param string $name
     * @param mixed $value
     * @return void
     */
    public function setExchangeOption($name, $value)
    {
        $this->exchangeOptions[$name] = $value;
    }

    /**
     * Gets the routing key
     *
     * @return string
     */
    public function getRouting()
    {
        return $this->routing;
    }

    /**
     * Sets the routing key
     *
     * @param string $routing
     * @return void
     */
    public function setRouting($routing)
    {
        $this->routing = $routing;
    }

    /**
     * Gets the consumer tag
     *
     * @return string
     */
    public function getConsumerTag()
    {
        return get_class($this);
    }

    /**
     * Gets the database
     *
     * @return \Jacobine\Helper\Database
     */
    public function getDatabase()
    {
        return $this->database;
    }

    /**
     * Sets the database
     *
     * @param \Jacobine\Helper\Database $database
     * @return void
     */
    public function setDatabase($database)
    {
        $this->database = $database;
    }

    /**
     * Gets the config
     *
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * Sets the config
     *
     * @param array $config
     * @return void
     */
    public function setConfig(array $config)
    {
        $this->config = $config;
    }

    /**
     * Gets the message queue
     *
     * @return \Jacobine\Helper\MessageQueue
     */
    public function getMessageQueue()
    {
        return $this->messageQueue;
    }

    /**
     * Sets the message queue
     *
     * @param \Jacobine\Helper\MessageQueue $messageQueue
     * @return void
     */
    public function setMessageQueue($messageQueue)
    {
        $this->messageQueue = $messageQueue;
    }

    /**
     * Gets the logger
     *
     * @return \Monolog\Logger
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * Sets the logger
     *
     * @param LoggerInterface $logger
     * @return void
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Enable dead lettering
     *
     * @return void
     */
    public function enableDeadLettering()
    {
        $this->deadLettering = true;
    }

    /**
     * Checks if dead lettering is enabled
     *
     * @return boolean
     */
    public function isDeadLetteringEnabled()
    {
        return $this->deadLettering;
    }

    /**
     * Disable dead lettering
     *
     * @return void
     */
    public function disableDeadLettering()
    {
        $this->deadLettering = false;
    }

    /**
     * Initialize the consumer.
     * E.g. sets the queue and routing key
     *
     * @return void
     */
    public function initialize()
    {
        $messageQueue = $this->getMessageQueue();

        $this->setQueueOptions($messageQueue->getDefaultQueueOptions());
        $this->setExchangeOptions($messageQueue->getDefaultExchangeOptions());
    }

    /**
     * Acknowledges a message of a consumer to the message queue server
     *
     * @param \stdClass $message
     * @return void
     */
    protected function acknowledgeMessage($message)
    {
        $message->delivery_info['channel']->basic_ack($message->delivery_info['delivery_tag']);
    }

    /**
     * Reject a message of a consumer
     *
     * @param \stdClass $message
     * @param bool $requeue
     */
    protected function rejectMessage($message, $requeue = false)
    {
        $message->delivery_info['channel']->basic_reject($message->delivery_info['delivery_tag'], $requeue);
    }

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
    public function consume($message)
    {
        $this->setMessage($message);
        $messageData = json_decode($message->body);

        $this->getLogger()->info('Receiving message', (array) $messageData);

        // @TODO do we need a special kind of ConsumerException to handle a "data bag" for $context?
        try {
            $this->process($messageData);

        } catch(\Exception $e) {
            $context = [
                'message' => (array) $messageData,
                'exceptionCode' => $e->getCode(),
                'exceptionMessage' => $e->getMessage(),
                'exceptionFile' => $e->getFile()
            ];
            $this->getLogger()->critical('Consume process of message failed', $context);
            $this->rejectMessage($message);
            return;
        }

        $this->acknowledgeMessage($message);
        $this->getLogger()->info('Finish processing message', (array) $messageData);
    }

    /**
     * The (business) logic of the consumer.
     * Will be called by the public API ($this->consume()).
     *
     * @param \stdClass $message
     * @return void
     */
    abstract protected function process($message);

    /**
     * Provides a context (e.g. for logging) of a executed command.
     *
     * @param \Symfony\Component\Process\Process $process
     * @param \Exception $exception
     * @return array
     */
    protected function getContextOfCommand(\Symfony\Component\Process\Process $process, \Exception $exception = null)
    {
        $command = (($process instanceof \Symfony\Component\Process\Process) ? $process->getCommandLine(): null);
        $exceptionCode = (($exception instanceof \Exception) ? $exception->getCode(): 0);
        $exceptionMessage = (($exception instanceof \Exception) ? $exception->getMessage(): '');

        $context = [
            'command' => $command,
            'commandOutput' => $process->getOutput(),
            'wasSuccessful' => var_export($process->isSuccessful(), true),
            'exitCode' => $process->getExitCode(),
            'exceptionCode' => $exceptionCode,
            'exceptionMessage' => $exceptionMessage
        ];

        return $context;
    }
}
