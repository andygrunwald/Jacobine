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
}
