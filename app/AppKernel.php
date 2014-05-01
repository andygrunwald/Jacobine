<?php
/**
 * This file is part of the Jacobine package.
 *
 * (c) Andreas Grunwald <andygrunwald@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class AppKernel extends \Jacobine\Application\Kernel
{
    protected function initializeApplication()
    {
        parent::initializeApplication();

        $this->application->add(new \Jacobine\Command\GetTYPO3OrgCommand());
        $this->application->add(new \Jacobine\Command\ConsumerCommand());
        $this->application->add(new \Jacobine\Command\ListConsumerCommand());
        $this->application->add(new \Jacobine\Command\ListProjectsCommand());
        $this->application->add(new \Jacobine\Command\GitwebCommand());
        $this->application->add(new \Jacobine\Command\GerritCommand());
        $this->application->add(new \Jacobine\Command\NNTPCommand());
    }
}
