<?php
/**
 * This file is part of the TYPO3-Analytics package.
 *
 * (c) Andreas Grunwald <andygrunwald@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TYPO3Analysis\Helper;

use Symfony\Component\Process\Process;

/**
 * Class ProcessFactory
 *
 * Factory to create a new system process (like calls with "system", "exec", ...).
 * This factory is used in many commands like CVSAnaly or GithubLinguist.
 *
 * @package TYPO3Analysis\Helper
 * @author Andy Grunwald <andygrunwald@gmail.com>
 */
class ProcessFactory
{

    /**
     * Method to create a new process.
     *
     * At the moment this method is really simple and does not support complete features of the process component.
     * But maybe the features will implemented in the near future.
     * Maybe this is your first pull request? I would LOVE to see this ;=)
     *
     * Maybe this method implementation should be changed to create a new Symfony\Component\Process\ProcessBuilder.
     *
     * @param string $command Command which will be executed.
     * @param int $timeout The timeout in seconds or null to disable
     * @return \Symfony\Component\Process\Process
     */
    public function createProcess($command, $timeout = 60)
    {
        $cwd = null;
        $env = null;
        $stdin = null;

        $process = new Process($command, $cwd, $env, $stdin, $timeout);
        return $process;
    }
}
