<?php
/**
 * This file is part of the Jacobine package.
 *
 * (c) Andreas Grunwald <andygrunwald@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Jacobine\Tests\Consumer\Crawler;

use Jacobine\Tests\Consumer\ConsumerTestAbstract;
use Jacobine\Consumer\Crawler\Gitweb;

/**
 * Class GitwebTest
 *
 * Unit test class for \Jacobine\Consumer\Crawler\Gitweb
 *
 * @package Jacobine\Tests\Consumer\Crawler
 * @author Andy Grunwald <andygrunwald@gmail.com>
 */
class GitwebTest extends ConsumerTestAbstract
{

    public function setUp()
    {
        $databaseMock = $this->getDatabaseMock();
        $browserMock = $this->getBrowserMock();
        $crawlerFactoryMock = $this->getMock('Jacobine\Component\Crawler\CrawlerFactory');

        $this->consumer = new Gitweb($databaseMock, $browserMock, $crawlerFactoryMock);
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
