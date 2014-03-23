<?php
/**
 * This file is part of the TYPO3-Analytics package.
 *
 * (c) Andreas Grunwald <andygrunwald@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TYPO3Analysis\Tests\Helper;

use TYPO3Analysis\Helper\MessageQueue;

/**
 * Class MessageQueueTest
 *
 * Unit test class for \TYPO3Analysis\Helper\MessageQueue
 *
 * @package TYPO3Analysis\Tests\Helper
 * @author Andy Grunwald <andygrunwald@gmail.com>
 */
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
        // add check if string or something is incoming
                    ->will($this->returnValue($amqpMessageMock));

        $this->messageQueue = new MessageQueue($amqpConnectionMock, $factoryMock);
    }

    public function testSendSimpleMessageWithAString()
    {
        $message = 'This is a small message';
        $exchange = 'TEST';
        $routing = 'test.routing';

        $this->messageQueue->sendSimpleMessage($message, $exchange, $routing);
    }

    public function testSendSimpleMessageWithAnArray()
    {
        $message = [
            'Value 1',
            'Important value 2'
        ];
        $exchange = 'TEST';
        $routing = 'test.routing';

        $this->messageQueue->sendSimpleMessage($message, $exchange, $routing);
    }

    public function testSendExtendedMessageWithAString()
    {
        $message = 'Important string message';

        $exchangeOptions = $this->messageQueue->getDefaultExchangeOptions();
        $exchangeOptions['name'] = 'logging';

        $queueOptions = $this->messageQueue->getDefaultQueueOptions();
        $queueOptions['name'] = 'logging';

        $routing = 'logging.routing';

        $this->messageQueue->sendExtendedMessage($message, $exchangeOptions, $queueOptions, $routing);
    }

    public function testSendExtendedMessageWithAnArray()
    {
        $message = [
            'Value 1',
            'Important value 2'
        ];

        $exchangeOptions = $this->messageQueue->getDefaultExchangeOptions();
        $exchangeOptions['name'] = 'logging';

        $queueOptions = $this->messageQueue->getDefaultQueueOptions();
        $queueOptions['name'] = 'logging';

        $routing = 'logging.error';

        $this->messageQueue->sendExtendedMessage($message, $exchangeOptions, $queueOptions, $routing);
    }
}
