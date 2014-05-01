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

/**
 * Interface KernelInterface
 *
 * Interface of a Kernel.
 * The Kernel is the heart of the application.
 *
 * @package Jacobine\Consumer
 * @author Andy Grunwald <andygrunwald@gmail.com>
 */
interface KernelInterface
{

    /**
     * Entry point to run the application.
     * This is the first method to call.
     *
     * For an example have a look at the console file
     *
     * @return void
     */
    public function run();

    /**
     * Gets the application root dir.
     *
     * @return string
     */
    public function getRootDir();
}
