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

use Jacobine\Consumer\ConsumerInterface;

/**
 * Class ConsumerList
 *
 * Storage class to collect all consumer ids from DIC
 *
 * @link http://symfony.com/doc/current/components/dependency_injection/tags.html
 *
 * @package Jacobine\DependencyInjection
 * @author Andy Grunwald <andygrunwald@gmail.com>
 */
class ConsumerList
{

    /**
     * Bag to store all consumer
     *
     * @var array
     */
    protected $consumer = [];

    /**
     * Adds a new consumer
     *
     * @param ConsumerInterface $consumer
     * @return void
     */
    public function addConsumer(ConsumerInterface $consumer)
    {
        $this->consumer[] = $consumer;
    }

    /**
     * Return all consumer
     *
     * @return array
     */
    public function getAllConsumer()
    {
        return $this->consumer;
    }
}
