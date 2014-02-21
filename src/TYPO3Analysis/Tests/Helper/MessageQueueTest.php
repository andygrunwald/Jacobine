<?php
/**
 * @todo adds a description (license text, description of this class / file, etc)
 */
namespace TYPO3Analysis\Tests\Helper;

use TYPO3Analysis\Helper\MessageQueue;

class MessageQueueTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var \TYPO3Analysis\Helper\MessageQueue
     */
    protected $messageQueue;

    public function setUp()
    {
        $amqpChannelMock = $this->getMock('\PhpAmqpLib\Channel\AMQPChannel', [], [], '', false);
        $amqpChannelMock->expects($this->once())
                        ->method('basic_publish')
                        ->with($this->isInstanceOf('\PhpAmqpLib\Message\AMQPMessage'));

        $amqpConnectionMock = $this->getMock('\PhpAmqpLib\Connection\AMQPConnection', [], [], '', false);
        $amqpConnectionMock->expects($this->once())
                           ->method('channel')
                           ->will($this->returnValue($amqpChannelMock));

        $amqpMessageMock = $this->getMock('\PhpAmqpLib\Message\AMQPMessage');

        $factoryMock = $this->getMock('\TYPO3Analysis\Helper\AMQPFactory', ['createMessage']);
        $factoryMock->expects($this->once())
                    ->method('createMessage')
                    ->will($this->returnValue($amqpMessageMock));

        $this->messageQueue = new MessageQueue($amqpConnectionMock, $factoryMock);
    }

    public function testSendMessageWithAString()
    {
        $message = 'This is a small message';
        $this->messageQueue->sendMessage($message);
    }

    public function testSendMessageWithAnArray()
    {
        $message = [
            'Value 1',
            'Important value 2'
        ];
        $this->messageQueue->sendMessage($message);
    }

    public function testSendMessageWithAnArrayAndExchange()
    {
        $message = [
            'Value 1',
            'Important value 2'
        ];
        $exchange = 'logging';

        $this->messageQueue->sendMessage($message, $exchange);
    }

    public function testSendMessageWithAnArrayAndExchangeAndQueue()
    {
        $message = [
            'Value 1',
            'Important value 2'
        ];
        $exchange = 'logging';
        $queue = 'logging';

        $this->messageQueue->sendMessage($message, $exchange, $queue);
    }

    public function testSendMessageWithAnArrayAndExchangeAndQueueAndRouting()
    {
        $message = [
            'Value 1',
            'Important value 2'
        ];
        $exchange = 'logging';
        $queue = 'logging';
        $routing = 'logging.error';

        $this->messageQueue->sendMessage($message, $exchange, $queue, $routing);
    }

    public function testSendMessageWithAnArrayAndExchangeAndQueueAndRoutingAndExchangeType()
    {
        $message = [
            'Value 1',
            'Important value 2'
        ];
        $exchange = 'logging';
        $queue = 'logging';
        $routing = 'logging.error';
        $exchangeType = 'topic';

        $this->messageQueue->sendMessage($message, $exchange, $queue, $routing, $exchangeType);
    }
}