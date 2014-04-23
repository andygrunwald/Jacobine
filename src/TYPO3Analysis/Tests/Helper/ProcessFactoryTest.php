<?php
/**
 * This file is part of the Jacobine package.
 *
 * (c) Andreas Grunwald <andygrunwald@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TYPO3Analysis\Tests\Helper;

use TYPO3Analysis\Helper\ProcessFactory;

/**
 * Class ProcessFactoryTest
 *
 * Unit test class for \TYPO3Analysis\Helper\ProcessFactory
 *
 * @package TYPO3Analysis\Tests\Helper
 * @author Andy Grunwald <andygrunwald@gmail.com>
 */
class ProcessFactoryTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var \TYPO3Analysis\Helper\ProcessFactory
     */
    protected $factory;

    public function setUp()
    {
        $this->factory = new ProcessFactory();
    }

    public function testCreateProcessWithCommand()
    {
        $command = 'ls -al';

        $process = $this->factory->createProcess($command);

        $this->assertInstanceOf('\Symfony\Component\Process\Process', $process);
        $this->assertSame($command, $process->getCommandLine());
    }

    public function testCreateProcessWithEmptyCommand()
    {
        $command = '';

        $process = $this->factory->createProcess($command);

        $this->assertInstanceOf('\Symfony\Component\Process\Process', $process);
        $this->assertSame($command, $process->getCommandLine());
    }
}
