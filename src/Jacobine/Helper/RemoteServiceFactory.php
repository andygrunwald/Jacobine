<?php
/**
 * This file is part of the Jacobine package.
 *
 * (c) Andreas Grunwald <andygrunwald@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Jacobine\Helper;

use Buzz\Client\Curl;
use Buzz\Browser;

/**
 * Class RemoteServiceFactory
 *
 * Factory to create a objects to communicate with remote services over e.g. HTTP.
 *
 * @package Jacobine\Helper
 * @author Andy Grunwald <andygrunwald@gmail.com>
 */
class RemoteServiceFactory
{

    /**
     * Creates a HTTP service
     *
     * @param integer $timeout
     * @param bool $verifyPeer
     * @param bool $ignoreErrors
     * @return Browser
     */
    public function createHttpService($timeout, $verifyPeer = false, $ignoreErrors = true)
    {
        $timeout = (int) $timeout;

        $curlClient = new Curl();
        $curlClient->setTimeout($timeout);
        $curlClient->setVerifyPeer($verifyPeer);
        $curlClient->setIgnoreErrors($ignoreErrors);

        $browser = new Browser($curlClient);

        return $browser;
    }
}
