<?php
/**
 * @todo adds a description (license text, description of this class / file, etc)
 */
namespace TYPO3Analysis\Helper;

use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;

class AMQPFactory
{

    /**
     * Creates a new AMQP Connection :)
     *
     * @param string $host
     * @param integer $port
     * @param string $username
     * @param string $password
     * @param string $vHost
     * @return AMQPConnection
     */
    public function createConnection($host, $port, $username, $password, $vHost)
    {
        return new AMQPConnection($host, $port, $username, $password, $vHost);
    }

    /**
     * Factory to create a simple AMQP message
     *
     * @param string $message
     * @param array $options
     * @throws \UnexpectedValueException
     * @return AMQPMessage
     */
    public function createMessage($message, $options = array())
    {
        if (!$message) {
            throw new \UnexpectedValueException('Message must not be empty!', 1392656481);
        }
        return new AMQPMessage($message, $options);
    }
}
