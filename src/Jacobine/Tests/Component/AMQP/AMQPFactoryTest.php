<?php
/**
 * This file is part of the Jacobine package.
 *
 * (c) Andreas Grunwald <andygrunwald@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Jacobine\Tests\Component\AMQP;

use Jacobine\Component\AMQP\AMQPFactory;

/**
 * Class AMQPFactoryTest
 *
 * Unit test class for \Jacobine\Component\AMQP\AMQPFactory
 *
 * @package Jacobine\Tests\Component\AMQP
 * @author Andy Grunwald <andygrunwald@gmail.com>
 */
class AMQPFactoryTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var \Jacobine\Component\AMQP\AMQPFactory
     */
    protected $factory;

    public function setUp()
    {
        $this->factory = new AMQPFactory();
    }

    /**
     * This unit test is a little bit wired.
     * We try to test the AMQPFactory. The AMQPFactory has to return a valid AMQPConnection object.
     * But to create a AMQPConnection object you need a AMQP Server like RabbitMQ.
     * This behaviour moves a unit test to an integration test.
     * A AMQPConnection object creates the connection in the constructor.
     * Due to this there is no way to check if the factory returns a AMQPConnection object in a _unit_ test.
     * If you know a way how to do this, please ping me.
     *
     * Instead of checking the return value, a AMQPRuntimeException is thrown if the connection cant be build.
     * Here we check of this exception.
     * This does not mean that the factory returns the object.
     * But it does mean that the factory does (something) with AMQPConnection.
     * Not good but better than nothing.
     */
    public function testFactoryReturnsConnectionObject()
    {
        $this->setExpectedException('\PhpAmqpLib\Exception\AMQPRuntimeException');

        $host = 'localhost';
        $port = 1234;
        $username = 'testing';
        $password = '';
        $vHost = 'phpunit';

        $this->factory->createConnection($host, $port, $username, $password, $vHost);
    }

    /**
     * Data provider for empty messages
     *
     * @return array
     */
    public function emptyMessageProvider()
    {
        return array(
            array(''),
            array(0),
            array(null),
            array(false)
        );
    }

    /**
     * @dataProvider emptyMessageProvider
     */
    public function testFactoryReturnsMessageObjectWithEmptyMessage($message)
    {
        $this->setExpectedException('\UnexpectedValueException');

        $this->factory->createMessage($message);
    }

    public function testFactoryReturnsMessageObjectWithProperties()
    {
        $message = 'This is a test message with propteries';
        $properties = [
            'content_type' => 'text/plain',
            'content_encoding' => 'utf8',
        ];
        $amqpMessage = $this->factory->createMessage($message, $properties);

        $this->assertInstanceOf('\PhpAmqpLib\Message\AMQPMessage', $amqpMessage);
        $this->assertSame($message, $amqpMessage->body);
        $this->assertSame($properties['content_type'], $amqpMessage->get('content_type'));
        $this->assertSame($properties['content_encoding'], $amqpMessage->get('content_encoding'));
    }

    public function testFactoryReturnsMessageObjectWithoutProperties()
    {
        $message = 'This is a test message without properties';

        $amqpMessage = $this->factory->createMessage($message);

        $this->assertInstanceOf('\PhpAmqpLib\Message\AMQPMessage', $amqpMessage);
        $this->assertSame($message, $amqpMessage->body);
        $this->assertSame([], $amqpMessage->get_properties());
    }
}
