<?php
/**
 * This file is part of the Jacobine package.
 *
 * (c) Andreas Grunwald <andygrunwald@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Jacobine\Tests\Consumer\Download;

use Jacobine\Tests\Consumer\ConsumerTestAbstract;
use Jacobine\Consumer\Download\HTTP;

/**
 * Class HTTPTest
 *
 * Unit test class \Jacobine\Consumer\Download\HTTP
 *
 * @package Jacobine\Tests\Consumer\Download
 * @author Andy Grunwald <andygrunwald@gmail.com>
 */
class HTTPTest extends ConsumerTestAbstract
{

    public function setUp()
    {
        $this->consumer = new HTTP();
    }
}
