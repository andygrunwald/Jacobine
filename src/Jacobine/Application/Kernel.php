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
    protected $rootDir;
    protected $booted = false;

    /**
     * @var ContainerBuilder
     */
    protected $container;

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
        $this->container = new ContainerBuilder();

        // load xml config
        $loader = new XmlFileLoader($this->container, new FileLocator($this->getRootDir() . '/config'));
        $loader->load('services.xml');
    }

    /**
     * Initializes the console application.
     */
    protected function initializeApplication()
    {
        if ($this->application) {
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
        if (null === $this->rootDir) {
            $r = new \ReflectionObject($this);
            $this->rootDir = str_replace('\\', '/', dirname($r->getFileName()));
        }

        return $this->rootDir;
    }
}
