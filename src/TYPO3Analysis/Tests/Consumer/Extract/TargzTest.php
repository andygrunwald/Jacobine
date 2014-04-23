<?php
/**
 * This file is part of the Jacobine package.
 *
 * (c) Andreas Grunwald <andygrunwald@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TYPO3Analysis\Tests\Consumer\Extract;

use TYPO3Analysis\Tests\Consumer\ConsumerTestAbstract;
use TYPO3Analysis\Consumer\Extract\Targz;

/**
 * Class TargzTest
 *
 * Unit test class for \TYPO3Analysis\Consumer\Extract\Targz
 *
 * @package TYPO3Analysis\Tests\Consumer\Extract
 * @author Andy Grunwald <andygrunwald@gmail.com>
 */
class TargzTest extends ConsumerTestAbstract
{

    public function setUp()
    {
        $this->consumer = new Targz();
    }
}
