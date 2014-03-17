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
}
