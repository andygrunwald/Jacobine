<?php
/**
 * This file is part of the Jacobine package.
 *
 * (c) Andreas Grunwald <andygrunwald@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Jacobine\Tests\Monolog\Handler;

use Jacobine\Monolog\Handler\SymfonyConsoleHandler;
use Monolog\Logger;

/**
 * Class SymfonyConsoleHandlerTest
 *
 * Unit test class for \Jacobine\Monolog\Handler\SymfonyConsoleHandler
 *
 * @package Jacobine\Tests\Monolog\Handler
 * @author Andy Grunwald <andygrunwald@gmail.com>
 */
class SymfonyConsoleHandlerTest extends \PHPUnit_Framework_TestCase
{

    /**
     * This method was copied from Monolog\TestCase.
     * We have to copy it, because the test/ directory of Monolog is not included in the namespace autoloader.
     *
     * @param int $level
     * @param string $message
     * @param array $context
     * @return array Record
     */
    protected function getRecord($level = Logger::WARNING, $message = 'test', $context = array())
    {
        return array(
            'message' => $message,
            'context' => $context,
            'level' => $level,
            'level_name' => Logger::getLevelName($level),
            'channel' => 'test',
            'datetime' => \DateTime::createFromFormat('U.u', sprintf('%.6F', microtime(true))),
            'extra' => array(),
        );
    }

    /**
     * This method was copied from Monolog\TestCase.
     * We have to copy it, because the test/ directory of Monolog is not included in the namespace autoloader.
     *
     * @return Monolog\Formatter\FormatterInterface
     */
    protected function getIdentityFormatter()
    {
        $formatterMockCallback = function ($record) {
            return $record['message'];
        };
        $formatter = $this->getMock('Monolog\\Formatter\\FormatterInterface');
        $formatter->expects($this->any())
            ->method('format')
            ->will($this->returnCallback($formatterMockCallback));

        return $formatter;
    }

    protected function getMockObject($writeLineMessage, $addFormatterMock = true, $hasStyleReturnValue = false)
    {
        $formatterMock = null;
        if ($addFormatterMock === true) {
            $formatterMockCallback = function ($record) {
                return $record;
            };
            $formatterMock = $this->getMock('\Symfony\Component\Console\Formatter\OutputFormatterInterface');
            $formatterMock->expects($this->any())
                ->method('format')
                ->will($this->returnCallback($formatterMockCallback));

            $formatterMock->expects($this->any())
                ->method('hasStyle')
                ->will($this->returnValue($hasStyleReturnValue));
        }

        $outputMock = $this->getMock('\Symfony\Component\Console\Output\OutputInterface');
        $outputMock->expects($this->once())
            ->method('getFormatter')
            ->will($this->returnValue($formatterMock));

        $outputMock->expects($this->once())
            ->method('write')
            ->with($this->equalTo($writeLineMessage));

        return $outputMock;
    }

    public function testWriteWithoutFormatter()
    {
        $outputMock = $this->getMockObject('test', false);
        $consoleHandler = new SymfonyConsoleHandler($outputMock);
        $consoleHandler->setFormatter($this->getIdentityFormatter());

        $consoleHandler->handle($this->getRecord());
    }

    public function testWriteWithFormatterWithoutStyle()
    {
        $outputMock = $this->getMockObject('test');
        $consoleHandler = new SymfonyConsoleHandler($outputMock);
        $consoleHandler->setFormatter($this->getIdentityFormatter());

        $consoleHandler->handle($this->getRecord());
    }

    public function testWriteWithFormatterWithStyle()
    {
        $outputMock = $this->getMockObject('<warning>test</warning>', true, true);
        $consoleHandler = new SymfonyConsoleHandler($outputMock);
        $consoleHandler->setFormatter($this->getIdentityFormatter());

        $consoleHandler->handle($this->getRecord());
    }

    public function testWriteWithFormatterWithStyleAsArray()
    {
        $outputMock = $this->getMockObject('<critical>test</critical>', true, true);
        $consoleHandler = new SymfonyConsoleHandler($outputMock);
        $consoleHandler->setFormatter($this->getIdentityFormatter());

        $consoleHandler->handle($this->getRecord(Logger::CRITICAL));
    }
}
