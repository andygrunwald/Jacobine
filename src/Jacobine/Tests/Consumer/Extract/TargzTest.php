<?php
/**
 * This file is part of the Jacobine package.
 *
 * (c) Andreas Grunwald <andygrunwald@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Jacobine\Tests\Consumer\Extract;

use Jacobine\Tests\Consumer\ConsumerTestAbstract;
use Jacobine\Consumer\Extract\Targz;

/**
 * Class TargzTest
 *
 * Unit test class for \Jacobine\Consumer\Extract\Targz
 *
 * @package Jacobine\Tests\Consumer\Extract
 * @author Andy Grunwald <andygrunwald@gmail.com>
 */
class TargzTest extends ConsumerTestAbstract
{

    public function setUp()
    {
        $this->consumer = new Targz();
    }
}
