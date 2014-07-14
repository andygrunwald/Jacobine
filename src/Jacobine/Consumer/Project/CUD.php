<?php
/**
 * This file is part of the Jacobine package.
 *
 * (c) Andreas Grunwald <andygrunwald@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Jacobine\Consumer\Project;

use Jacobine\Consumer\ConsumerAbstract;
use Jacobine\Helper\MessageQueue;
use Jacobine\Helper\Database;
use Jacobine\Service\Project;

/**
 * Class CUD
 *
 * A consumer to create, update and delete projects from Jacobine.
 *
 * Message format (json encoded):
 *  [
 *      id: ID the project
 *      name: Name of the project
 *      website: Website of the project
 *      dataSources: An array of data sources of the project
 *  ]
 *
 * Usage:
 *  php console analysis:consumer Project\\CUD
 *
 * @package Jacobine\Consumer\Project
 * @author Andy Grunwald <andygrunwald@gmail.com>
 */
class CUD extends ConsumerAbstract
{

    /**
     * Project service
     *
     * @var \Jacobine\Service\Project
     */
    protected $projectService;

    /**
     * Constructor to set dependencies
     *
     * @param MessageQueue $messageQueue
     * @param \Jacobine\Service\Project $projectService
     */
    public function __construct(MessageQueue $messageQueue, Project $projectService)
    {
        $this->setMessageQueue($messageQueue);
        $this->projectService = $projectService;
    }

    /**
     * Gets a description of the consumer
     *
     * @return string
     */
    public function getDescription()
    {
        return 'CUD (Create, Update, Delete) operations for Jacobine projects.';
    }

    /**
     * Initialize the consumer.
     * Sets the queue and routing key
     *
     * @return void
     */
    public function initialize()
    {
        parent::initialize();

        $this->setQueueOption('name', 'project.cud');
        $this->enableDeadLettering();

        $this->setRouting('project.cud');
    }

    /**
     * The logic of the consumer
     *
     * @param \stdClass $message
     * @throws \Exception
     * @return void
     */
    protected function process($message)
    {
        // TODO Implement Update
        // TODO Implement Delete

        $this->createProject($message);
    }

    /**
     * Functionality to create a new project
     *
     * @param \stdClass $message
     * @return void
     */
    private function createProject($message)
    {
        $context = [
            'name' => $message->name
        ];
        $this->getLogger()->info('Create project', $context);

        $context['projectId'] = $this->projectService->createProject(
            $message->name,
            $message->website,
            (array) $message->dataSources
        );

        $this->getLogger()->info('Project was created', $context);
    }
}
