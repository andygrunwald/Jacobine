<?php
/**
 * This file is part of the Jacobine package.
 *
 * (c) Andreas Grunwald <andygrunwald@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Jacobine\Component\Filesystem;

/**
 * Class File
 *
 * Basic file abstraction.
 * Offers some basic functionality like renaming, md5 hashing or downloading.
 *
 * TODO Implement symfony filesystem component
 * @link https://github.com/symfony/Filesystem
 *
 * @package Jacobine\Component\Filesystem
 * @author Andy Grunwald <andygrunwald@gmail.com>
 */
class File
{

    /**
     * @var string
     */
    protected $file;

    /**
     * @param string $file
     */
    public function __construct($file)
    {
        $this->setFile($file);
    }

    /**
     * Sets a file
     *
     * @param string $file
     * @return void
     */
    public function setFile($file)
    {
        $this->file = $file;
    }

    /**
     * Returns the filepath + name
     *
     * @return string
     */
    public function getFile()
    {
        return $this->file;
    }

    /**
     * Download a source into in the constructor given file
     * We will download the file via curl
     *
     * TODO How to check if a file was downloaded successful?
     *      How to get the filesize of a file _before_ downloading?
     *      This would be great if this will be implemented
     *
     * @param string $source
     * @param int $timeout Download timeout in seconds. Default 1h
     * @return bool
     */
    public function download($source, $timeout = 3600)
    {
        $filePathHandle = fopen($this->getFile(), 'w+');

        $curlClient = new \Buzz\Client\Curl();
        $curlClient->setTimeout(intval($timeout));
        $curlClient->setVerifyPeer(false);
        $curlClient->setOption(CURLOPT_RETURNTRANSFER, false);
        $curlClient->setOption(CURLOPT_BINARYTRANSFER, true);
        $curlClient->setOption(CURLOPT_HEADER, false);
        $curlClient->setOption(CURLOPT_FILE, $filePathHandle);

        $browser = new \Buzz\Browser($curlClient);
        $browser->get($source);

        fclose($filePathHandle);

        return $this->exists();
    }

    /**
     * Checks if the file exists
     *
     * @return bool
     */
    public function exists()
    {
        return file_exists($this->getFile());
    }

    /**
     * Returns the MD5 hash of the file content
     *
     * @return string
     */
    public function getMd5OfFile()
    {
        return md5_file($this->getFile());
    }

    /**
     * Renames a file
     *
     * @param string $to
     * @return bool
     */
    public function rename($to)
    {
        $result = false;
        if ($this->exists() === true) {
            $result = rename($this->getFile(), $to);
        }

        if ($result) {
            $this->setFile($to);
        }

        return $result;
    }
}
