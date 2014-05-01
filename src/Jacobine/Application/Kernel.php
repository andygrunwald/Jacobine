<?php
/**
 * This file is part of the Jacobine package.
 *
 * (c) Andreas Grunwald <andygrunwald@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Jacobine\Application;

use Symfony\Component\Console\Application;

abstract class Kernel
{
    protected $rootDir;
    protected $booted = false;

    /**
     * @var Application
     */
    protected $application;

    function __construct()
    {
        $this->rootDir = $this->getRootDir();
    }

    public function run()
    {
        $this->boot();
        $this->application->run();
    }

    /**
     * Boots the current kernel.
     */
    protected function boot()
    {
        if ($this->booted) {
            return;
        }

        $this->initializeContainer();
        $this->initializeApplication();

        $this->booted = true;
    }

    /**
     * Initializes the service container.
     *
     * The cached version of the service container is used when fresh,
     * otherwise the container is built.
     */
    protected function initializeContainer()
    {
        // TODO initialize the container
    }

    /**
     * Initializes the console application.
     */
    protected function initializeApplication()
    {
        if ($this->application) {
            return;
        }

        // TODO fetch application from dependency container.
        // TODO add commands as tagged services

        $this->application = new Application();
    }

    /**
     * Gets the application root dir.
     *
     * @return string
     */
    public function getRootDir()
    {
        if (null === $this->rootDir) {
            $r = new \ReflectionObject($this);
            $this->rootDir = str_replace('\\', '/', dirname($r->getFileName()));
        }

        return $this->rootDir;
    }
}
