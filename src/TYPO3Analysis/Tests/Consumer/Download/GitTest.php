<?php
/**
 * This file is part of the Jacobine package.
 *
 * (c) Andreas Grunwald <andygrunwald@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TYPO3Analysis\Tests\Consumer\Download;

use TYPO3Analysis\Tests\Consumer\ConsumerTestAbstract;
use TYPO3Analysis\Consumer\Download\Git;

/**
 * Class GitTest
 *
 * Unit test class for \TYPO3Analysis\Consumer\Download\Git
 *
 * @package TYPO3Analysis\Tests\Consumer\Download
 * @author Andy Grunwald <andygrunwald@gmail.com>
 */
class GitTest extends ConsumerTestAbstract
{

    public function setUp()
    {
        $this->consumer = new Git();
    }
}
