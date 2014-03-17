<?php
/**
 * This file is part of the TYPO3-Analytics package.
 *
 * (c) Andreas Grunwald <andygrunwald@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TYPO3Analysis\Tests\Consumer;

/**
 * Class ConsumerTestAbstract
 *
 * Abstract consumer test class for consumer unit tests.
 * Every consumer unit test extends this class.
 * They just initialize the consumer.
 * Due to the inheritance all tests in this class will be executed for every consumer.
 *
 * @package TYPO3Analysis\Tests\Consumer
 * @author Andy Grunwald <andygrunwald@gmail.com>
 */
abstract class ConsumerTestAbstract extends \PHPUnit_Framework_TestCase
{

    /**
     * @var \TYPO3Analysis\Consumer\ConsumerInterface
     */
    protected $consumer;

    public function testConsumerGotDescription()
    {
        $description = $this->consumer->getDescription();

        $this->assertInternalType('string', $description);
        $this->assertTrue(strlen($description) > 0);
    }

    public function testConsumerInitializeWithQueueAndRouting()
    {
        $this->consumer->initialize();

        $queue = $this->consumer->getQueue();
        $routing = $this->consumer->getRouting();

        $this->assertInternalType('string', $queue);
        $this->assertTrue(strlen($queue) > 0);

        $this->assertInternalType('string', $routing);
        $this->assertTrue(strlen($routing) > 0);
    }

    public function testMessageGetterAndSetter()
    {
        $this->assertNull($this->consumer->getMessage());

        $message = new \stdClass();
        $message->dummy = 'attribute';

        $this->consumer->setMessage($message);

        $this->assertSame($message, $this->consumer->getMessage());
    }

    public function testExchangeGetterAndSetter()
    {
        $this->assertEmpty($this->consumer->getExchange());

        $exchange = 'EXCHANGE';
        $this->consumer->setExchange($exchange);

        $this->assertSame($exchange, $this->consumer->getExchange());
    }

    public function testConsumerTag()
    {
        $consumerTag = $this->consumer->getConsumerTag();

        $this->assertInternalType('string', $consumerTag);
        $this->assertNotEmpty($consumerTag);
    }

    public function testDatabaseGetterAndSetter()
    {
        $this->assertNull($this->consumer->getDatabase());

        // Mock of \TYPO3Analysis\Helper\DatabaseFactory
        $databaseFactoryMock = $this->getMock('TYPO3Analysis\Helper\DatabaseFactory');
        $constructorArgs = [$databaseFactoryMock, '', '', '', '', ''];
        $databaseMock = $this->getMock('\TYPO3Analysis\Helper\Database', [], $constructorArgs);

        $this->consumer->setDatabase($databaseMock);

        $this->assertSame($databaseMock, $this->consumer->getDatabase());
    }

    public function testMessageQueueGetterAndSetter()
    {
        $this->assertNull($this->consumer->getMessageQueue());

        $amqpConnectionMock = $this->getMock('\PhpAmqpLib\Connection\AMQPConnection', [], [], '', false);
        $amqpFactoryMock = $this->getMock('\TYPO3Analysis\Helper\AMQPFactory', ['createMessage']);
        $constructorArgs = [$amqpConnectionMock, $amqpFactoryMock];
        $messageQueueMock = $this->getMock('\TYPO3Analysis\Helper\MessageQueue', [], $constructorArgs);

        $this->consumer->setMessageQueue($messageQueueMock);

        $this->assertSame($messageQueueMock, $this->consumer->getMessageQueue());
    }

    public function testLoggerGetterAndSetter()
    {
        $this->assertNull($this->consumer->getLogger());

        $loggerMock = $this->getMock('\Monolog\Logger', [], ['']);

        $this->consumer->setLogger($loggerMock);

        $this->assertSame($loggerMock, $this->consumer->getLogger());
    }
}
