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
use Jacobine\Consumer\Crawler\GerritProject;

/**
 * Class GerritProjectTest
 *
 * Unit test class for \Jacobine\Consumer\Crawler\GerritProject
 *
 * @package Jacobine\Tests\Consumer\Crawler
 * @author Andy Grunwald <andygrunwald@gmail.com>
 */
class GerritProjectTest extends ConsumerTestAbstract
{

    public function setUp()
    {
        $this->consumer = new GerritProject();
    }
}
