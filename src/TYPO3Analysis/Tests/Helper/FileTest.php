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

use org\bovigo\vfs\vfsStream;
use Jacobine\Helper\File;

/**
 * Class FileTest
 *
 * Unit test class for \Jacobine\Helper\File
 *
 * @package Jacobine\Tests\Helper
 * @author Andy Grunwald <andygrunwald@gmail.com>
 */
class FileTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var string
     */
    protected $dummyFilename = 'my-test-file.txt';

    public function testConstructorSetsFilename()
    {
        $file = new File($this->dummyFilename);
        $this->assertEquals($this->dummyFilename, $file->getFile());
    }

    public function testFilenameSetterAndGetter()
    {
        $file = new File($this->dummyFilename);

        $filename = '/etc/another/file.zip';
        $file->setFile($filename);

        $this->assertEquals($filename, $file->getFile());
    }

    public function testFileExistsOfExistingFile()
    {
        $vfs = vfsStream::setup('root', null, [$this->dummyFilename => '']);

        $file = new File($vfs->getChild($this->dummyFilename)->url());

        $this->assertTrue($file->exists());
    }

    public function testFileExistsOfNonExistingFile()
    {
        vfsStream::setup();
        $fileUrl = vfsStream::url('root/' . $this->dummyFilename);
        $file = new File($fileUrl);

        $this->assertFalse($file->exists());
    }

    public function testGetMD5OfFileContent()
    {
        $content = 'This is content for a unit test.';
        $content .= 'We just need some content.';
        $content .= 'And of course, we need always contributer.';
        $content .= 'If you want to improve this test suite, check our github repository and contribute!';
        $content .= 'https://github.com/andygrunwald/TYPO3-Analytics';

        $vfs = vfsStream::setup('root', null, [$this->dummyFilename => $content]);

        $file = new File($vfs->getChild($this->dummyFilename)->url());

        $this->assertEquals('9ce29d3b573eba5de16f0eab944e2e77', $file->getMd5OfFile());
    }

    public function testRenameWithExistingFile()
    {
        $vfs = vfsStream::setup('root', null, [$this->dummyFilename => '']);

        $file = new File($vfs->getChild($this->dummyFilename)->url());

        $targetFileName = 'root/new-' . $this->dummyFilename;
        $targetFileUrl = vfsStream::url($targetFileName);

        $this->assertTrue($file->rename($targetFileUrl));
        $this->assertEquals('vfs://' . $targetFileName, $file->getFile());
        $this->assertTrue($vfs->hasChild($targetFileName));
    }

    public function testRenameWithoutExistingFile()
    {
        $vfs = vfsStream::setup('root', null, []);

        $file = new File(vfsStream::url($this->dummyFilename));

        $targetFileName = 'root/new-' . $this->dummyFilename;
        $targetFileUrl = vfsStream::url($targetFileName);

        $this->assertFalse($file->rename($targetFileUrl));
        $this->assertEquals('vfs://' . $this->dummyFilename, $file->getFile());
        $this->assertFalse($vfs->hasChild($targetFileName));
    }
}
