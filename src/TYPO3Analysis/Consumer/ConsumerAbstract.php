<?php
/**
 * This file is part of the TYPO3-Analytics package.
 *
 * (c) Andreas Grunwald <andygrunwald@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TYPO3Analysis\Consumer;

/**
 * Class ConsumerAbstract
 *
 * Base implementation to fit the ConsumerInterface.
 * In general many setter, getter and basic message handling like acknowledgement are implemented.
 *
 * @package TYPO3Analysis\Consumer
 * @author Andy Grunwald <andygrunwald@gmail.com>
 */
abstract class ConsumerAbstract implements ConsumerInterface
{

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
     * @var \TYPO3Analysis\Helper\MessageQueue
     */
    private $messageQueue;

    /**
     * Logger
     *
     * @var \Monolog\Logger
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
     * Gets the queue name
     *
     * @return string
     */
    public function getQueue()
    {
        return $this->queue;
    }

    /**
     * Sets the queue name
     *
     * @param string $queue
     * @return void
     */
    public function setQueue($queue)
    {
        $this->queue = $queue;
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
     * Gets the exchange name
     *
     * @return string
     */
    public function getExchange()
    {
        return $this->exchange;
    }

    /**
     * Sets the exchange name
     *
     * @param string $exchange
     * @return void
     */
    public function setExchange($exchange)
    {
        $this->exchange = $exchange;
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
     * @return \TYPO3Analysis\Helper\Database
     */
    public function getDatabase()
    {
        return $this->database;
    }

    /**
     * Sets the database
     *
     * @param \TYPO3Analysis\Helper\Database $database
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
     * @return \TYPO3Analysis\Helper\MessageQueue
     */
    public function getMessageQueue()
    {
        return $this->messageQueue;
    }

    /**
     * Sets the message queue
     *
     * @param \TYPO3Analysis\Helper\MessageQueue $messageQueue
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
     * @param \Monolog\Logger $logger
     * @return void
     */
    public function setLogger($logger)
    {
        $this->logger = $logger;
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
     * Executes a single command to the system
     *
     * @param string $command
     * @param bool $withUser
     * @param array $environmentVarsToAdd
     * @throws \Exception
     * @return array
     */
    protected function executeCommand($command, $withUser = true, $environmentVarsToAdd = array())
    {
        $output = array();
        $returnValue = 0;

        if ($withUser === true) {
            $envCommandPart = $this->getEnvironmentVarsCommandPart($environmentVarsToAdd);
            $userCommandPart = $this->getUserCommandPart();
            $command = $userCommandPart . ' ' . $envCommandPart . ' ' . $command;
        }

        $command .= ' 2>&1';
        exec($command, $output, $returnValue);

        if ($returnValue > 0) {
            $msg = 'Command returns an error!';
            $this->getLogger()->critical($msg, array('command' => $command, 'output' => $output));

            $msg = 'Command "%s" returns an error!';
            $msg = sprintf($msg, $command);
            throw new \Exception($msg, 1367169216);
        }

        return $output;
    }

    /**
     * Builds the command part to add environment var settings to sudo command
     *
     * @param array $environmentVars
     * @return string
     */
    private function getEnvironmentVarsCommandPart($environmentVars = array())
    {
        $commandPart = array();

        foreach ($environmentVars as $envVar) {
            $envValue = getenv($envVar);

            if ($envValue) {
                $commandPart[] = $envVar . '=' . escapeshellarg($envValue);
            }
        }

        return implode(',', $commandPart);
    }

    /**
     * Builds the command part to execute the command with the same user
     * as this script
     *
     * @return string
     */
    private function getUserCommandPart()
    {
        $userInformation = posix_getpwuid(posix_geteuid());
        $username = $userInformation['name'];
        $commandPart = 'sudo -u ' . escapeshellarg($username);

        return $commandPart;
    }
}
