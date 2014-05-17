<?php
/**
 * This file is part of the Jacobine package.
 *
 * (c) Andreas Grunwald <andygrunwald@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Jacobine\Tests\Helper;

use Jacobine\Helper\LoggerFactory;
use Monolog\Logger;

/**
 * Class LoggerFactoryTest
 *
 * Unit test class for \Jacobine\Helper\LoggerFactory
 *
 * @package Jacobine\Tests\Helper
 * @author Andy Grunwald <andygrunwald@gmail.com>
 */
class LoggerFactoryTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var \Jacobine\Helper\LoggerFactory
     */
    protected $factory;

    public function setUp()
    {
        $this->factory = new LoggerFactory();
    }

    public function testCreateLoggerWithEmptyChannel()
    {
        $this->setExpectedException('\Exception');

        $this->factory->create(null);
    }

    public function testCreateLoggerWithouthandler()
    {
        $logger = $this->factory->create('channel');
        $handler = $logger->popHandler();

        $this->assertInstanceOf('Monolog\Handler\NullHandler', $handler);
    }

    public function testCreateLoggerWithConsoleHandler()
    {
        $handlerConfig = [
            'Consoler' => [
                'class' => 'SymfonyConsoleHandler'
            ]
        ];
        $logger = $this->factory->create('channel', $handlerConfig);
        $handler = $logger->popHandler();

        $this->assertInstanceOf('Jacobine\Monolog\Handler\SymfonyConsoleHandler', $handler);
    }

    public function testCreateLoggerWithStreamHandlerWithoutMinLogLevel()
    {
        $handlerConfig = [
            'Stream' => [
                'class' => 'StreamHandler',
                'path' => '/var/log/analysis/'
            ]
        ];
        $logger = $this->factory->create('channel', $handlerConfig);
        $handler = $logger->popHandler();

        $this->assertInstanceOf('Monolog\Handler\StreamHandler', $handler);
    }

    public function testCreateLoggerWithStreamHandlerWithMinLogLevel()
    {
        $handlerConfig = [
            'Stream' => [
                'class' => 'StreamHandler',
                'path' => '/var/log/analysis/',
                'minLogLevel' => 'Error'
            ]
        ];
        $logger = $this->factory->create('channel', $handlerConfig);
        $handler = $logger->popHandler();


        $this->assertInstanceOf('Monolog\Handler\StreamHandler', $handler);
        $this->assertEquals(Logger::ERROR, $handler->getLevel());
    }
}
