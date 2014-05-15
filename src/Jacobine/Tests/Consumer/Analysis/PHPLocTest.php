<?php
/**
 * This file is part of the Jacobine package.
 *
 * (c) Andreas Grunwald <andygrunwald@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Jacobine\Tests\Consumer\Analysis;

use Jacobine\Tests\Consumer\ConsumerTestAbstract;
use Jacobine\Consumer\Analysis\PHPLoc;

/**
 * Class PHPLocTest
 *
 * Unit test class for \Jacobine\Consumer\Analysis\PHPLoc
 *
 * @package Jacobine\Tests\Consumer\Analysis
 * @author Andy Grunwald <andygrunwald@gmail.com>
 */
class PHPLocTest extends ConsumerTestAbstract
{

    public function setUp()
    {
        $databaseMock = $this->getDatabaseMock();
        $processFactoryMock = $this->getProcessFactoryMock();

        $this->consumer = new PHPLoc($databaseMock, $processFactoryMock);
    }
}
