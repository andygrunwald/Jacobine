<?php
/**
 * This file is part of the TYPO3-Analytics package.
 *
 * (c) Andreas Grunwald <andygrunwald@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TYPO3Analysis\Tests\Helper;

use TYPO3Analysis\Helper\File;

class FileTest extends \PHPUnit_Framework_TestCase
{
    /**
     * File object
     *
     * @var \TYPO3Analysis\Helper\File
     */
    protected $file;

    /**
     * @var string
     */
    protected $setUpFilename = '/var/www/my-notes.txt';

    public function setUp()
    {
        $this->file = new File($this->setUpFilename);
    }

    public function testConstructorSetsFilename()
    {
        $this->assertEquals($this->setUpFilename, $this->file->getFile());
    }

    public function testFilenameSetterAndGetter()
    {
        $filename = '/etc/another/file';
        $this->file->setFile($filename);

        $this->assertEquals($filename, $this->file->getFile());
    }
}
