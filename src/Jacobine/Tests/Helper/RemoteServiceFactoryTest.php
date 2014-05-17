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

use Jacobine\Helper\RemoteServiceFactory;

/**
 * Class RemoteServiceFactoryTest
 *
 * Unit test class for \Jacobine\Helper\RemoteServiceFactory
 *
 * @package Jacobine\Tests\Helper
 * @author Andy Grunwald <andygrunwald@gmail.com>
 */
class RemoteServiceFactoryTest extends \PHPUnit_Framework_TestCase
{

    public function testFactoryReturnsBrowserObjectAsHttpService()
    {
        $remoteServiceFactory = new RemoteServiceFactory();
        $httpService = $remoteServiceFactory->createHttpService(42);

        $this->assertInstanceOf('Buzz\Browser', $httpService);
    }

    public function testHttpServiceWithCorrectParameters()
    {
        $remoteServiceFactory = new RemoteServiceFactory();
        $httpService = $remoteServiceFactory->createHttpService(42, true, false);

        $client = $httpService->getClient();
        $this->assertEquals(42, $client->getTimeout());
        $this->assertEquals(true, $client->getVerifyPeer());
        $this->assertEquals(false, $client->getIgnoreErrors());
    }
}
