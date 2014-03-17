<?php
/**
 * This file is part of the TYPO3-Analytics package.
 *
 * (c) Andreas Grunwald <andygrunwald@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TYPO3Analysis\Tests\Consumer\Crawler;

use TYPO3Analysis\Tests\Consumer\ConsumerTestAbstract;
use TYPO3Analysis\Consumer\Crawler\Gitweb;

/**
 * Class GitwebTest
 *
 * Unit test class for \TYPO3Analysis\Consumer\Crawler\Gitweb
 *
 * @package TYPO3Analysis\Tests\Consumer\Crawler
 * @author Andy Grunwald <andygrunwald@gmail.com>
 */
class GitwebTest extends ConsumerTestAbstract
{

    public function setUp()
    {
        $this->consumer = new Gitweb();
    }

    public function testConsumerInitializeWithQueueAndRouting()
    {
        $config = [
            'Various' => [
                'Requests' => [
                    'Timeout' => 10
                ]
            ]
        ];
        $this->consumer->setConfig($config);

        parent::testConsumerInitializeWithQueueAndRouting();
    }
}
