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
use Jacobine\DependencyInjection\CommandCompilerPass;
use Jacobine\DependencyInjection\ConsumerCompilerPass;

/**
 * Class Kernel
 *
 * Base implementation to fit the KernelInterface.
 * This is the base implementation of a Kernel to use in Jacobine.
 *
 * @package Jacobine\Application
 * @author Andy Grunwald <andygrunwald@gmail.com>
 * @author Markus Poerschke <markus@eluceo.de>
 */
abstract class Kernel implements KernelInterface
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
     * Dependency Injection Container
     *
     * @var \Symfony\Component\DependencyInjection\TaggedContainerInterface
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
        $this->container->addCompilerPass(new CommandCompilerPass());
        $this->container->addCompilerPass(new ConsumerCompilerPass());

        // Load xml config
        $fileLocator = new FileLocator($this->getRootDir() . '/config');

        $loader = new XmlFileLoader($this->container, $fileLocator);
        $loader->load('config.xml');
        $loader->load('services.xml');

        $this->container->compile();

        // In theory we are ready to dump the container here (for performance)
        // I (Andy) think that this is (currently) not necessary. Maybe this make sense in the feature.
        // The imprtant thing: We are ready for this change, because all tags are collected by compiler passes
        // See the @link and chapter about "Dumping the Configuration for Performance"
        // @link http://symfony.com/doc/current/components/dependency_injection/compilation.html
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

        /* @var \Jacobine\DependencyInjection\CommandList $commandListService */
        $commandListService = $this->container->get('dependencyInjection.commandList');
        $commandList = $commandListService->getAllCommands();

        foreach ($commandList as $command) {
            /** @var Command $command */

            // Inject DIC if it is needed
            if ($command instanceof \Symfony\Component\DependencyInjection\ContainerAwareInterface) {
                $command->setContainer($this->container);
            }

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
