<?php
/**
 * This file is part of the TYPO3-Analytics package.
 *
 * (c) Andreas Grunwald <andygrunwald@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TYPO3Analysis\Tests\Monolog\Handler;

use TYPO3Analysis\Monolog\Handler\SymfonyConsoleHandler;
use Monolog\Logger;

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
        $formatter = $this->getMock('Monolog\\Formatter\\FormatterInterface');
        $formatter->expects($this->any())
            ->method('format')
            ->will($this->returnCallback(function($record) { return $record['message']; }));

        return $formatter;
    }

    protected function getMockObject($writeLineMessage, $addFormatterMock = true)
    {
        $formatterMock = null;
        if ($addFormatterMock === true) {
            $formatterMock = $this->getMock('\Symfony\Component\Console\Formatter\OutputFormatterInterface');
            $formatterMock->expects($this->any())
                ->method('format')
                ->will($this->returnCallback(function($record) { return $record; }));

            // TODO formatter hasStyle has to be mocked as well: $formatter->hasStyle($style)
        }

        $outputMock = $this->getMock('\Symfony\Component\Console\Output\OutputInterface');
        $outputMock->expects($this->once())
            ->method('getFormatter')
            ->will($this->returnValue($formatterMock));

        $outputMock->expects($this->once())
            ->method('writeln')
            ->with($this->equalTo($writeLineMessage));

        return $outputMock;
    }

    public function testWriteWithoutFormatter()
    {
        $outputMock = $this->getMockObject('test');
        $consoleHandler = new SymfonyConsoleHandler($outputMock);
        $consoleHandler->setFormatter($this->getIdentityFormatter());

        $consoleHandler->handle($this->getRecord());
    }


}
