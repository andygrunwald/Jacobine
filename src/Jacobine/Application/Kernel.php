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

use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

abstract class Kernel
{
    /**
     * Root directory of application
     *
     * @var string
     */
    protected $rootDir = null;

    /**
     * Flag if the application is already booted
     *
     * @var bool
     */
    protected $booted = false;

    /**
     * Dependency Dependency Injection Container Builder
     *
     * @var \Symfony\Component\DependencyInjection\ContainerBuilder
     */
    protected $container;

    /**
     * Console application
     *
     * @var \Symfony\Component\Console\Application
     */
    protected $application = null;

    /**
     * Constructor of the Kernel
     *
     * @return \Jacobine\Application\Kernel
     */
    public function __construct()
    {
        $this->rootDir = $this->getRootDir();
    }

    /**
     * Entry point to run the application.
     * This is the first method to call.
     *
     * For an example have a look at the console file
     *
     * @return void
     */
    public function run()
    {
        $this->boot();
        $this->application->run();
    }

    /**
     * Boots the current kernel.
     *
     * @return void
     */
    protected function boot()
    {
        if ($this->booted === true) {
            return;
        }

        $this->initializeContainer();
        $this->initializeApplication();

        $this->booted = true;
    }

    /**
     * Initializes the service container.
     *
     * The cached version of the service container is used when fresh, otherwise the container is built.
     *
     * @return void
     */
    protected function initializeContainer()
    {
        $this->container = new ContainerBuilder();

        // Load xml config
        $fileLocator = new FileLocator($this->getRootDir() . '/config');

        $loader = new XmlFileLoader($this->container, $fileLocator);
        $loader->load('services.xml');
    }

    /**
     * Initializes the console application.
     *
     * For example:
     *  Adds all console commands via DIC tag to the application
     *
     * @return void
     */
    protected function initializeApplication()
    {
        if ($this->application !== null) {
            return;
        }

        $this->application = $this->container->get('console_application');
        $commandServiceIds = $this->container->findTaggedServiceIds('jacobine.command');

        foreach ($commandServiceIds as $serviceId => $options) {
            /** @var Command $command */
            $command = $this->container->get($serviceId);
            $this->application->add($command);
        }
    }

    /**
     * Gets the application root dir.
     *
     * @return string
     */
    public function getRootDir()
    {
        if ($this->rootDir === null) {
            $r = new \ReflectionObject($this);
            $this->rootDir = str_replace('\\', '/', dirname($r->getFileName()));
        }

        return $this->rootDir;
    }
}
