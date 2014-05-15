<?php
/**
 * This file is part of the Jacobine package.
 *
 * (c) Andreas Grunwald <andygrunwald@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Jacobine\DependencyInjection;

use Symfony\Component\Console\Command\Command;

/**
 * Class CommandList
 *
 * Storage class to collect all commands from DIC
 *
 * @link http://symfony.com/doc/current/components/dependency_injection/tags.html
 *
 * @package Jacobine\DependencyInjection
 * @author Andy Grunwald <andygrunwald@gmail.com>
 */
class CommandList
{

    /**
     * Bag to store all commands
     *
     * @var array
     */
    protected $commands = [];

    /**
     * Adds a new command
     *
     * @param Command $command
     * @return void
     */
    public function addCommand(Command $command)
    {
        $this->commands[] = $command;
    }

    /**
     * Return all commands
     *
     * @return array
     */
    public function getAllCommands()
    {
        return $this->commands;
    }
}
