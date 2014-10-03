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
use Jacobine\Consumer\Download\Git;

/**
 * Class GitTest
 *
 * Unit test class for \Jacobine\Consumer\Download\Git
 *
 * @package Jacobine\Tests\Consumer\Download
 * @author Andy Grunwald <andygrunwald@gmail.com>
 */
class GitTest extends ConsumerTestAbstract
{

    public function setUp()
    {
        $databaseMock = $this->getDatabaseMock();
        $processFactoryMock = $this->getProcessFactoryMock();
        $projectServiceMock = $this->getProjectServiceMock();

        $this->consumer = new Git($databaseMock, $processFactoryMock, $projectServiceMock);
    }
}
