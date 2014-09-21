<?php
/**
 * This file is part of the Jacobine package.
 *
 * (c) Andreas Grunwald <andygrunwald@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Jacobine\Tests\Consumer;

use \Jacobine\Tests\Fixtures\MessageQueueOptions;

/**
 * Class ConsumerTestAbstract
 *
 * Abstract consumer test class for consumer unit tests.
 * Every consumer unit test extends this class.
 * They just initialize the consumer.
 * Due to the inheritance all tests in this class will be executed for every consumer.
 *
 * @package Jacobine\Tests\Consumer
 * @author Andy Grunwald <andygrunwald@gmail.com>
 */
abstract class ConsumerTestAbstract extends \PHPUnit_Framework_TestCase
{

    /**
     * @var \Jacobine\Consumer\ConsumerInterface
     */
    protected $consumer;

    /**
     * Returns a complete mock object of the message queue
     *
     * @param int $defaultOptionsCall Integer how many times the get*DefaultOptions method will be called
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function getMessageQueueMock($defaultOptionsCall = 1)
    {
        $messageQueueOptions = new MessageQueueOptions();

        $amqpConnectionMock = $this->getMock('\PhpAmqpLib\Connection\AMQPConnection', [], [], '', false);
        $amqpFactoryMock = $this->getMock('\Jacobine\Component\AMQP\AMQPFactory', ['createMessage']);

        $constructorArgs = [$amqpConnectionMock, $amqpFactoryMock];
        $messageQueueMock = $this->getMock('\Jacobine\Component\AMQP\MessageQueue', [], $constructorArgs);

        $messageQueueMock->expects($this->exactly($defaultOptionsCall))
                         ->method('getDefaultQueueOptions')
                         ->will($this->returnValue($messageQueueOptions->defaultQueueOptions));

        $messageQueueMock->expects($this->exactly($defaultOptionsCall))
                         ->method('getDefaultExchangeOptions')
                         ->will($this->returnValue($messageQueueOptions->defaultExchangeOptions));

        return $messageQueueMock;
    }

    protected function getProcessFactoryMock()
    {
        $processMock = $this->getMock('\Symfony\Component\Process\Process', [], ['']);

        $processFactoryMock = $this->getMock('Jacobine\Component\Process\ProcessFactory');
        $processFactoryMock->method('createProcess')
                           ->will($this->returnValue($processMock));

        return $processFactoryMock;
    }

    protected function getProjectServiceMock()
    {
        $projectServiceMock = $this->getMock('Jacobine\Service\Project');

        return $projectServiceMock;
    }

    protected function getDatabaseMock()
    {
        // Mock of \Jacobine\Component\Database\DatabaseFactory
        $databaseFactoryMock = $this->getMock('Jacobine\Component\Database\DatabaseFactory');

        $driver = 'mysql';
        $host = 'localhost';
        $port = 3306;
        $username = 'phpunit';
        $password = '';
        $database = 'testcase';

        $constructorArgs = [$databaseFactoryMock, $driver, $host, $port, $username, $password, $database];
        $databaseMock = $this->getMock('Jacobine\Component\Database\Database', [], $constructorArgs);

        return $databaseMock;
    }

    protected function getBrowserMock()
    {
        $browserMock = $this->getMock('Buzz\Browser');

        return $browserMock;
    }

    protected function mockGetRecordsForDatabaseMock(
        \PHPUnit_Framework_MockObject_MockObject $databaseMock,
        array $records
    ) {
        $databaseMock->expects($this->once())
                     ->method('getRecords')
                     ->will($this->returnValue($records));

        return $databaseMock;
    }

    protected function getLoggerMock()
    {
        $loggerMock = $this->getMock('Psr\Log\LoggerInterface');

        return $loggerMock;
    }

    protected function generateMessage(array $message, $rejectMethodCallTimes = 0)
    {
        $deliveryTag = 'deliver test tag';

        $amqpChannelMock = $amqpChannelMock = $this->getMock('\PhpAmqpLib\Channel\AMQPChannel', [], [], '', false);

        // Reject message
        $amqpChannelcallCount = (($rejectMethodCallTimes > 0) ? $this->exactly($rejectMethodCallTimes): $this->never());
        $amqpChannelMock->expects($amqpChannelcallCount)
                        ->method('basic_reject')
                        ->with($deliveryTag);

        $messageObject = new \stdClass();
        $messageObject->body = json_encode($message);

        $messageObject->delivery_info = [
            'channel' => $amqpChannelMock,
            'delivery_tag' => $deliveryTag
        ];

        return $messageObject;
    }

    public function testConsumerGotDescription()
    {
        $description = $this->consumer->getDescription();

        $this->assertInternalType('string', $description);
        $this->assertTrue(strlen($description) > 0);
    }

    public function testConsumerInitializeWithQueueAndRouting()
    {
        $messageQueueMock = $this->getMessageQueueMock();
        $this->consumer->setMessageQueue($messageQueueMock);

        $this->consumer->initialize();

        $queueOptions = $this->consumer->getQueueOptions();
        $routing = $this->consumer->getRouting();

        $this->assertInternalType('array', $queueOptions);
        $this->assertArrayHasKey('name', $queueOptions);
        $this->assertNotEmpty($queueOptions['name']);

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

    public function testExchangeOptionsGetterAndSetter()
    {
        $this->assertEmpty($this->consumer->getExchangeOptions());

        $exchangeOptions = ['name' => 'EXCHANGE'];
        $this->consumer->setExchangeOptions($exchangeOptions);

        $this->assertSame($exchangeOptions, $this->consumer->getExchangeOptions());
    }

    public function testSingleExchangeOptionGetterAndSetter()
    {
        $this->assertEmpty($this->consumer->getExchangeOptions());

        $this->consumer->setExchangeOption('name', 'EXCHANGE');

        $exchangeOptions = $this->consumer->getExchangeOptions();

        $this->assertInternalType('array', $exchangeOptions);
        $this->assertArrayHasKey('name', $exchangeOptions);
        $this->assertNotEmpty($exchangeOptions['name']);
    }

    public function testEnableDeadlettering()
    {
        $this->assertFalse($this->consumer->isDeadLetteringEnabled());
        $this->consumer->enableDeadLettering();
        $this->assertTrue($this->consumer->isDeadLetteringEnabled());
    }

    public function testDisableDeadLettering()
    {
        $this->assertFalse($this->consumer->isDeadLetteringEnabled());
        $this->consumer->enableDeadLettering();
        $this->assertTrue($this->consumer->isDeadLetteringEnabled());
        $this->consumer->disableDeadLettering();
        $this->assertFalse($this->consumer->isDeadLetteringEnabled());
    }

    public function testConsumerTag()
    {
        $consumerTag = $this->consumer->getConsumerTag();

        $this->assertInternalType('string', $consumerTag);
        $this->assertNotEmpty($consumerTag);
    }

    public function testDatabaseGetterAndSetter()
    {
        // Mock of \Jacobine\Component\Database\DatabaseFactory
        $databaseFactoryMock = $this->getMock('Jacobine\Component\Database\DatabaseFactory');
        $constructorArgs = [$databaseFactoryMock, '', '', '', '', '', ''];
        $databaseMock = $this->getMock('\Jacobine\Component\Database\Database', [], $constructorArgs);

        $this->consumer->setDatabase($databaseMock);

        $this->assertSame($databaseMock, $this->consumer->getDatabase());
    }

    public function testMessageQueueGetterAndSetter()
    {
        $messageQueueMock = $this->getMessageQueueMock(0);

        $this->consumer->setMessageQueue($messageQueueMock);

        $this->assertSame($messageQueueMock, $this->consumer->getMessageQueue());
    }

    public function testLoggerGetterAndSetter()
    {
        $loggerMock = $this->getMock('\Psr\Log\LoggerInterface');

        $this->consumer->setLogger($loggerMock);

        $this->assertSame($loggerMock, $this->consumer->getLogger());
    }
}
