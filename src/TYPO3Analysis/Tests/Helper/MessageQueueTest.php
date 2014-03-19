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
        $this->messageQueue->sendExtendedMessage($message);
    }

    public function testSendMessageWithAnArray()
    {
        $message = [
            'Value 1',
            'Important value 2'
        ];
        $this->messageQueue->sendExtendedMessage($message);
    }

    public function testSendMessageWithAnArrayAndExchange()
    {
        $message = [
            'Value 1',
            'Important value 2'
        ];

        $exchangeOptions = $this->defaultExchangeOptions;
        $exchangeOptions['name'] = 'logging';

        $this->messageQueue->sendExtendedMessage($message, $exchangeOptions);
    }

    public function testSendMessageWithAnArrayAndExchangeAndQueue()
    {
        $message = [
            'Value 1',
            'Important value 2'
        ];

        $exchangeOptions = $this->defaultExchangeOptions;
        $exchangeOptions['name'] = 'logging';

        $queueOptions = $this->defaultQueueOptions;
        $queueOptions['name'] = 'logging';

        $this->messageQueue->sendExtendedMessage($message, $exchangeOptions, $queueOptions);
    }

    public function testSendMessageWithAnArrayAndExchangeAndQueueAndRouting()
    {
        $message = [
            'Value 1',
            'Important value 2'
        ];

        $exchangeOptions = $this->defaultExchangeOptions;
        $exchangeOptions['name'] = 'logging';

        $queueOptions = $this->defaultQueueOptions;
        $queueOptions['name'] = 'logging';

        $routing = 'logging.error';

        $this->messageQueue->sendExtendedMessage($message, $exchangeOptions, $queueOptions, $routing);
    }
}
