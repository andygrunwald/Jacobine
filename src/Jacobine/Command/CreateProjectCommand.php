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

use Jacobine\Entity\DataSource;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 *
 * Class CreateProjectCommand
 *
 * Command to create or update a new project for Jacobine.
 * This command try to helps the user to get into Jacobine and to add a first project.
 *
 * To analyze a project with Jacobine, some details a necessary.
 * Like a name or data sources.
 * Data sources can be anything. Git repositories, mailing lists, Github organisations, etc.
 * This CLI interface will help the user to fulfill the necessary requirements to kickstart a analysis of a project.
 *
 * Usage:
 *  php console jacobine:create-project
 *
 * @package Jacobine\Command
 * @author Andy Grunwald <andygrunwald@gmail.com>
 */
class CreateProjectCommand extends Command implements ContainerAwareInterface
{

    use ContainerAwareTrait;

    /**
     * Symfony2 console InputInterface
     *
     * @var InputInterface
     */
    private $input;

    /**
     * Symfony2 console OutputInterface
     *
     * @var OutputInterface
     */
    private $output;

    /**
     * MessageQueue connection
     *
     * @var \Jacobine\Helper\MessageQueue
     */
    private $messageQueue;

    /**
     * Number to choose if the user got no more datasources to add.
     *
     * @var int
     */
    const TYPE_NO_MORE_DATASOURCES = 42;

    /**
     * Text for const TYPE_NO_MORE_DATASOURCES
     *
     * @var string
     */
    const TEXT_NO_MORE_DATASOURCES = 'I got no more data sources (finish)';

    /**
     * Message Queue routing
     *
     * @var string
     */
    const ROUTING = 'project.cud';

    /**
     * Configures the current command.
     *
     * @return void
     */
    protected function configure()
    {
        $this->setName('jacobine:create-project')
             ->setDescription('CLI interface to create a new project to analyze');
    }

    /**
     * Returns the input interface
     *
     * @return InputInterface
     */
    protected function getInput()
    {
        return $this->input;
    }

    /**
     * Sets the correct input interface
     *
     * @param InputInterface $input
     * @return void
     */
    protected function setInput(InputInterface $input)
    {
        $this->input = $input;
    }

    /**
     * Returns the output interface
     *
     * @return OutputInterface
     */
    protected function getOutput()
    {
        return $this->output;
    }

    /**
     * Sets the correct output interface
     *
     * @param OutputInterface $output
     * @return void
     */
    protected function setOutput(OutputInterface $output)
    {
        $this->output = $output;
    }

    /**
     * Initializes the command just after the input has been validated.
     *
     * Sets up the input + output interface and the message queue
     *
     * @param InputInterface $input An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     * @return void
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->setInput($input);
        $this->setOutput($output);
        $this->messageQueue = $this->container->get('helper.messageQueue');
    }

    /**
     * Executes the current command.
     *
     * @param InputInterface $input An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     * @return null|integer null or 0 if everything went fine, or an error code
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $applicationName = $this->container->getParameter('application.name');
        $exchange = $this->container->getParameter('messagequeue.exchange');

        $this->outputWelcomeMessage($applicationName);

        $question = 'Please enter a name of the project: ';
        $validator = [$this->getNonEmptyValidator('The project name can not be empty')];
        $projectName = $this->ask($question, $validator);

        $question = 'Please enter a website of the project: ';
        $website = $this->ask($question, []);

        $this->outputDataSourcesMessage();
        $dataSources = $this->dataSourceChoiceQuestion();

        // Only count all childs
        $dataSourcesCount = count($dataSources, COUNT_RECURSIVE) - count($dataSources);
        $this->outputDataSourcesEndMessage($dataSourcesCount);

        $this->sendProjectMessageToBroker($exchange, $projectName, $website, $dataSources);

        $this->outputEndMessage($applicationName);

        return null;
    }

    /**
     * Sends further message(s) to the broker.
     * In this case we drop a message to create or update a project
     *
     * @param string $exchange
     * @param string $projectName
     * @param string $website
     * @param array $dataSources
     * @return void
     */
    private function sendProjectMessageToBroker($exchange, $projectName, $website, array $dataSources)
    {
        $message = [
            'name' => $projectName,
            'website' => $website,
            'dataSources' => $dataSources
        ];
        $this->messageQueue->sendSimpleMessage($message, $exchange, self::ROUTING);
    }

    /**
     * Asks the user for data sources.
     * The user will get a choice list of data sources like
     *
     *  Please add a data source
     *      [1 ] Github organisation
     *      [2 ] Github repository
     *      [3 ] Mailman server
     *      ...
     *      [42] I got no more data sources (finish)
     *
     * to choose from.
     * This method will return all entered data sources.
     *
     * @return array
     */
    private function dataSourceChoiceQuestion()
    {
        $dataSources = [];
        $answer = null;
        $input = $this->getInput();
        $output = $this->getOutput();
        $questionHelper = $this->getHelper('question');

        $predefinedDataSources = $this->getDataSourcesAsArray();
        $dataSourceChoices = array_column($predefinedDataSources, 'text', 'type');

        do {

            // Only output detail question if there is an answer.
            if ($answer !== null) {
                $dataSourceType = $this->getDataSourceTypeByAnswer($answer);
                $dataSource = $predefinedDataSources[$dataSourceType];

                $question = sprintf('Please enter %s: ', $dataSource['question']);
                $dataSources[$dataSourceType][] = $this->ask($question, $dataSource['validator']);
            }

            $question = new ChoiceQuestion('Please add a data source', $dataSourceChoices);
            $question->setErrorMessage('%s is not a valid data source.');

            $answer = $questionHelper->ask($input, $output, $question);
        } while($answer != self::TEXT_NO_MORE_DATASOURCES);

        return $dataSources;
    }

    /**
     * Returns the type of a data source by the given (text) answer
     *
     * @param string $answer
     * @return integer
     */
    private function getDataSourceTypeByAnswer($answer)
    {
        $dataSources = $this->getDataSourcesAsArray();
        $dataSources = array_column($dataSources, 'type', 'text');

        return $dataSources[$answer];
    }

    /**
     * Returns all valid data sources as array.
     * One array entry contains the type id, the type text, a question and an array of validators.
     *
     * @return array
     */
    private function getDataSourcesAsArray()
    {
        $dataSources = [
            DataSource::TYPE_GITHUB_ORGANISATION => [
                'type' => DataSource::TYPE_GITHUB_ORGANISATION,
                'text' => DataSource::getTextForType(DataSource::TYPE_GITHUB_ORGANISATION),
                'question' => 'the name of a github organisation (e.g. MetricsGrimoire)',
                'validator' => [
                    $this->getNonEmptyValidator('The github organisation name can not be empty')
                ]
            ],
            DataSource::TYPE_GITHUB_REPOSITORY => [
                'type' => DataSource::TYPE_GITHUB_REPOSITORY,
                'text' => DataSource::getTextForType(DataSource::TYPE_GITHUB_REPOSITORY),
                'question' => 'the name of a github repository (e.g. andygrunwald/Jacobine)',
                'validator' => [
                    $this->getNonEmptyValidator('The github repository name can not be empty')
                ]
            ],
            DataSource::TYPE_MAILMAN_SERVER => [
                'type' => DataSource::TYPE_MAILMAN_SERVER,
                'text' => DataSource::getTextForType(DataSource::TYPE_MAILMAN_SERVER),
                'question' => 'the URL of a mailman server (e.g. http://lists.freebsd.org/)',
                'validator' => [
                    $this->getNonEmptyValidator('The mailman server url can not be empty'),
                    $this->getURLValidator()
                ]
            ],
            DataSource::TYPE_MAILMAN_LIST => [
                'type' => DataSource::TYPE_MAILMAN_LIST,
                'text' => DataSource::getTextForType(DataSource::TYPE_MAILMAN_LIST),
                'question' => 'the URL of a single mailman list (e.g. http://lists.typo3.org/pipermail/typo3-dev/)',
                'validator' => [
                    $this->getNonEmptyValidator('The mailman list url can not be empty'),
                    $this->getURLValidator()
                ]
            ],
            DataSource::TYPE_GITWEB_SERVER => [
                'type' => DataSource::TYPE_GITWEB_SERVER,
                'text' => DataSource::getTextForType(DataSource::TYPE_GITWEB_SERVER),
                'question' => 'the URL of a gitweb server (e.g. https://git.typo3.org/)',
                'validator' => [
                    $this->getNonEmptyValidator('The gitweb server url can not be empty'),
                    $this->getURLValidator()
                ]
            ],
            DataSource::TYPE_REPOSITORY_GIT => [
                'type' => DataSource::TYPE_REPOSITORY_GIT,
                'text' => DataSource::getTextForType(DataSource::TYPE_REPOSITORY_GIT),
                'question' => 'the URL of a git repository (e.g. https://github.com/andygrunwald/Jacobine.git)',
                'validator' => [
                    $this->getNonEmptyValidator('The git repository url can not be empty'),
                    $this->getURLValidator()
                ]
            ],
            DataSource::TYPE_REPOSITORY_SUBVERSION => [
                'type' => DataSource::TYPE_REPOSITORY_SUBVERSION,
                'text' => DataSource::getTextForType(DataSource::TYPE_REPOSITORY_SUBVERSION),
                'question' => 'the URL of a subversion repository (e.g. http://v8.googlecode.com/svn/trunk/)',
                'validator' => [
                    $this->getNonEmptyValidator('The subversion repository url can not be empty'),
                    $this->getURLValidator()
                ]
            ],
            DataSource::TYPE_GERRIT_SERVER => [
                'type' => DataSource::TYPE_GERRIT_SERVER,
                'text' => DataSource::getTextForType(DataSource::TYPE_GERRIT_SERVER),
                'question' => 'the URL of a gerrit server (e.g. https://review.typo3.org/)',
                'validator' => [
                    $this->getNonEmptyValidator('The gerrit server url can not be empty'),
                    $this->getURLValidator()
                ]
            ],
            DataSource::TYPE_GERRIT_PROJECT => [
                'type' => DataSource::TYPE_GERRIT_PROJECT,
                'text' => DataSource::getTextForType(DataSource::TYPE_GERRIT_PROJECT),
                'question' => 'the URL of a gerrit project (e.g. https://review.typo3.org/#/q/project:Packages/TYPO3.CMS)s',
                'validator' => [
                    $this->getNonEmptyValidator('The gerrit server url can not be empty'),
                    $this->getURLValidator()
                ]
            ],
            self::TYPE_NO_MORE_DATASOURCES => [
                'type' => self::TYPE_NO_MORE_DATASOURCES,
                'text' => self::TEXT_NO_MORE_DATASOURCES,
                'question' => '',
                'validator' => []
            ],
        ];

        return $dataSources;
    }

    /**
     * Outputs the welcome message of this command.
     *
     * @param string $applicationName
     * @return void
     */
    private function outputWelcomeMessage($applicationName)
    {
        $messages = [
            '',
            sprintf('Welcome to %s.', $applicationName),
            '',
            'This CLI client will help you to kickstart a new analysis of a specific project.',
            'In the next few sentences you will be asked several questions to specify details about your project.',
            'This questions are about some properties like the project name or a website and about the data sources you want to integrate, but i think you will get it really fast.',
            '',
            'So, now lets stop talking and start with some exciting things!',
            ''
        ];

        $this->outputMessageArray($messages);
    }

    /**
     * Outputs the end message of this command.
     *
     * @param string $applicationName
     * @return void
     */
    private function outputEndMessage($applicationName)
    {
        $messages = [
            '',
            sprintf('Thanks for using the %s create project CLI interface.', $applicationName),
            'A message was created and sent to create / update your project.',
            'Please note that this can take some seconds.',
            '',
            'After your project is created all our hardworking consumer will start to update your data sources.',
            'Please not that the update process can take some more minutes.',
            '',
            'Anyway. I hope you enjoyed this way to create your new project!',
            'Cu next time!',
            ''
        ];

        $this->outputMessageArray($messages);
    }

    /**
     * Outputs a message before the user is able to enter data sources
     *
     * @return void
     */
    private function outputDataSourcesMessage()
    {
        $messages = [
            '',
            'Thanks for the amount of properties you have entered a few seconds for your new project.',
            'Now it comes to your data sources.',
            'In the next step(s) you can enter as much data sources you want.',
            'If you are finished, just type the "I got no more data sources (finish)" number :)',
            'Have fun!',
            ''
        ];

        $this->outputMessageArray($messages);
    }

    /**
     * Outputs the message after the user entered several data sources
     *
     * @param integer $amount
     * @return void
     */
    private function outputDataSourcesEndMessage($amount)
    {
        $messages = [
            '',
            sprintf('Yeah! You added %d data sources!', $amount),
            'If you want to add some later on, this is no problem.',
            'Just restart this wizard and type the same project name as before :)',
        ];

        $this->outputMessageArray($messages);
    }

    /**
     * Outputs an array of messages with a given type (info, comment, ...)
     *
     * @param array $messages
     * @param string $type
     * @return void
     */
    private function outputMessageArray(array $messages, $type = 'info')
    {
        $output = $this->getOutput();

        foreach ($messages as $message) {
            $messageToWrite = sprintf('<%s>%s</%s>', $type, $message, $type);
            $output->writeln($messageToWrite);
        }
    }

    /**
     * Small generic method to ask a normal question.
     *
     * @param string $question
     * @param array $validator
     * @return string
     */
    private function ask($question, array $validator = [])
    {
        $input = $this->getInput();
        $output = $this->getOutput();

        $questionHelper = $this->getHelper('question');

        $question = sprintf('<%s>%s</%s>', 'comment', $question, 'comment');
        $question = new Question($question);

        foreach ($validator as $singleValidator) {
            $question->setValidator($singleValidator);
        }

        $answer = $questionHelper->ask($input, $output, $question);

        return $answer;
    }

    /**
     * Returns a validator for non empty input
     *
     * @param string $message
     * @return callable
     */
    private function getNonEmptyValidator($message)
    {
        $validator = function ($value) use ($message) {
            if (trim($value) == '') {
                throw new \Exception($message, 1405361655);
            }

            return $value;
        };

        return $validator;
    }

    /**
     * Returns a validator for URLs
     *
     * @return callable
     */
    private function getURLValidator()
    {
        $validator = function ($value) {
            $result = filter_var($value, FILTER_VALIDATE_URL, FILTER_FLAG_SCHEME_REQUIRED);
            if ($result === false) {
                throw new \Exception('Please enter a valid URL.', 1405361650);
            }

            return $result;
        };

        return $validator;
    }
}
