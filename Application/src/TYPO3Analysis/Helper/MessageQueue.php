<?php
/**
 * @todo adds a description (license text, description of this class / file, etc)
 */
namespace TYPO3Analysis\Helper;

use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;

class MessageQueue {

    /**
     * Message Queue connection
     *
     * @var \PhpAmqpLib\Connection\AMQPConnection
     */
    protected $handle = null;

    /**
     * Message Queue channel
     *
     * @var \PhpAmqpLib\Channel\AMQPChannel
     */
    protected $channel = null;

    /**
     * Store of declared exchanges and queues
     *
     * @var array
     */
    protected $declared = array(
        'exchange' => array(),
        'queue' => array(),
    );

    public function __construct($host, $port, $username, $password, $vHost) {
        $this->handle = new AMQPConnection($host, $port, $username, $password, $vHost);
        $this->renewChannel();
    }

    protected function renewChannel() {
        $this->channel = $this->handle->channel();
        $this->channel->basic_qos(0, 1, false);
    }

    protected function getHandle() {
        return $this->handle;
    }

    protected function getChannel() {
        return $this->channel;
    }

    protected function declareExchange($exchange, $exchangeType) {
        if (isset($this->declared['exchange'][$exchange]) === false) {
            $this->getChannel()->exchange_declare($exchange, $exchangeType, false, true, true);
            $this->declared['exchange'][$exchange] = true;
        }
    }

    protected function declareQueue($queue) {
        #if (isset($this->declared['queue'][$queue]) === false) {
            #$this->renewChannel();
            $this->getChannel()->queue_declare($queue, false, true, false, false);
            $this->declared['queue'][$queue] = true;
        #}
    }

    public function sendMessage($message, $exchange = '', $queue = '', $routing = '', $exchangeType = 'topic') {
        if (is_array($message) === true) {
            $message = json_encode($message);
        }

        if ($exchange) {
            $this->declareExchange($exchange, $exchangeType);
        }

        if ($queue) {
            $this->declareQueue($queue);
        }

        $message = new AMQPMessage($message, array('content_type' => 'text/plain'));
        return $this->getChannel()->basic_publish($message, $exchange, $routing);
    }

    public function basicConsume($exchange, $queue, $routing, $consumerTag, array $callback, $exchangeType = 'topic') {
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

    public function close() {
        #$this->getChannel()->close();
        #$this->getHandle()->close();
    }

    public function __destruct() {
        $this->close();
    }
}