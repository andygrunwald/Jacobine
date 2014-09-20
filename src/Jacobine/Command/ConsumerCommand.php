<?php
/**
 * This file is part of the Jacobine package.
 *
 * (c) Andreas Grunwald <andygrunwald@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Jacobine\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Class ConsumerCommand
 *
 * This command is to start a single consumer to receive messages from a message queue broker.
 * The message queue broker must support the AMQP standard.
 *
 * Every consumer can be started via this ConsumerCommand.
 * This class reflects the single entry point for every consumer.
 *
 * Usage:
 *  php console jacobine:consumer ConsumerName [--project=ProjectName]
 *
 * e.g. to start the Download HTTP consumer
 *  php console jacobine:consumer Download\\HTTP --project=TYPO3
 *
 * @package Jacobine\Command
 * @author Andy Grunwald <andygrunwald@gmail.com>
 */
class ConsumerCommand extends Command implements ContainerAwareInterface
{

    use ContainerAwareTrait;

    /**
     * MessageQueue connection
     *
     * @var \Jacobine\Component\AMQP\MessageQueue
     */
    protected $messageQueue;

    /**
     * Config
     *
     * TODO get rid of config here
     *
     * @var array
     */
    protected $config = [];

    /**
     * Project
     *
     * TODO get rid of project here
     *
     * @var string
     */
    protected $project;

    /**
     * Configures the current command.
     *
     * @return void
     */
    protected function configure()
    {
        $this->setName('jacobine:consumer')
             ->setDescription('Generic task for message queue consumer')
            // // TODO get rid of project here
             ->addOption(
                 'project',
                 null,
                 InputOption::VALUE_OPTIONAL,
                 'Choose the project (for configuration, etc.).',
                 'TYPO3'
             )
             ->addArgument('consumer', InputArgument::REQUIRED, 'Part namespace of consumer');
    }

    /**
     * Returns the current project
     *
     * @return string
     */
    protected function getProject()
    {
        // TODO get rid of project here
        return $this->project;
    }

    /**
     * Sets the current project
     *
     * @param string $project
     * @return void
     */
    protected function setProject($project)
    {
        // TODO get rid of project here
        $this->project = $project;
    }

    /**
     * Initializes the command just after the input has been validated.
     *
     * Sets up the project, config and message queue
     *
     * @param InputInterface $input An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     * @return void
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        // TODO get rid of config here + project
        $this->setProject($input->getOption('project'));
        $this->config = Yaml::parse(CONFIG_FILE);

        $this->messageQueue = $this->container->get('component.amqp.messageQueue');
    }

    /**
     * Executes the current command.
     *
     * Initialize and starts a single consumer.
     *
     * @param InputInterface $input An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     * @return null|integer null or 0 if everything went fine, or an error code
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $consumerIdent = $input->getArgument('consumer');
        $consumerToGet = str_replace('\\', '.', $consumerIdent);
        $consumerToGet = 'consumer.' . strtolower($consumerToGet);

        // If the consumer does not exists exit here
        if ($this->container->has($consumerToGet) === false) {
            throw new \Exception('A consumer like "' . $consumerIdent . '" does not exist', 1368100583);
        }

        $logger = $this->container->get('logger.' . $consumerToGet);

        // Create, initialize and start consumer
        $consumer = $this->container->get($consumerToGet);
        /* @var \Jacobine\Consumer\ConsumerAbstract $consumer */
        $consumer->setContainer($this->container);
        // TODO get rid of config here
        $consumer->setConfig($this->config);
        $consumer->setLogger($logger);
        $consumer->initialize();

        $projectConfig = $this->config['Projects'][$this->getProject()];
        $consumer->setExchangeOption('name', $projectConfig['RabbitMQ']['Exchange']);

        $consumerIdent = str_replace('\\', '\\\\', $consumerIdent);
        $logger->info('Consumer starts', ['consumer' => $consumerIdent]);

        // Register consumer at message queue
        $callback = [$consumer, 'consume'];
        $this->messageQueue->basicConsume(
            $consumer->getExchangeOptions(),
            $consumer->getQueueOptions(),
            $consumer->isDeadLetteringEnabled(),
            $consumer->getRouting(),
            $consumer->getConsumerTag(),
            $callback
        );

        return null;
    }
}
