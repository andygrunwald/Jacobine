<?php
/**
 * This file is part of the TYPO3-Analytics package.
 *
 * (c) Andreas Grunwald <andygrunwald@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TYPO3Analysis\Consumer\Analysis;

use TYPO3Analysis\Consumer\ConsumerAbstract;
use TYPO3Analysis\Helper\ProcessFactory;
use Symfony\Component\Process\ProcessUtils;

/**
 * Class GithubLinguist
 *
 * A consumer to execute githubs linguist (https://github.com/github/linguist).
 * linguist is a tool to detect used programing languages in a project (e.g. php, css and javascript).
 *
 * linguist is written in Ruby.
 * We have to execute linguist via a external command, because we can`t speak from PHP to Ruby libs directly.
 *
 * TODO: Idea -> Port this consumer from PHP to Ruby. With this we can get rid of the system command.
 *
 * Message format (json encoded):
 *  [
 *      directory: Absolute path to folder which will be analyzed. E.g. /var/www/my/sourcecode
 *      versionId: Version ID to get the regarding version record from version database table
 *  ]
 *
 * Usage:
 *  php console analysis:consumer Analysis\\GithubLinguist
 *
 * @package TYPO3Analysis\Consumer\Analysis
 * @author Andy Grunwald <andygrunwald@gmail.com>
 */
class GithubLinguist extends ConsumerAbstract
{

    /**
     * Gets a description of the consumer
     *
     * @return string
     */
    public function getDescription()
    {
        return 'Executes the Github Linguist analysis on a folder and stores the results in linguist database table.';
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

        $this->setQueueOption('name', 'analysis.linguist');
        $this->enableDeadLettering();

        $this->setRouting('analysis.linguist');
    }

    /**
     * The logic of the consumer
     *
     * @param \stdClass $message
     * @return void
     */
    public function process($message)
    {
        $this->setMessage($message);
        $messageData = json_decode($message->body);

        $this->getLogger()->info('Receiving message', (array) $messageData);

        // If there is no directory to analyse, exit here
        if (is_dir($messageData->directory) !== true) {
            $this->getLogger()->critical('Directory does not exist', array('directory' => $messageData->directory));
            $this->rejectMessage($this->getMessage());
            return;
        }

        try {
            $this->clearLinguistRecordsFromDatabase($messageData->versionId);
        } catch (\Exception $e) {
            $this->rejectMessage($message);
            return;
        }

        /** @var \Symfony\Component\Process\Process $process */
        list($process, $exception) = $this->executeGithubLinguist($messageData->directory);
        if ($exception !== null || $process->isSuccessful() === false) {
            $context = $this->getContextOfCommand($process, $exception);
            $this->getLogger()->critical('github-linguist command failed', $context);
            $this->rejectMessage($this->getMessage());
            return;
        }

        $output = $process->getOutput();

        if ($output === []) {
            $msg = 'github-linguist returns no result';
            $this->getLogger()->critical($msg);
            $this->rejectMessage($this->getMessage());
            return;
        }

        $parsedResults = $this->parseGithubLinguistResults($output);

        // Store the github linguist results
        try {
            $this->storeLinguistDataInDatabase($messageData->versionId, $parsedResults);
        } catch (\Exception $e) {
            $this->rejectMessage($message);
            return;
        }

        $this->acknowledgeMessage($message);
        $this->getLogger()->info('Finish processing message', (array)$messageData);
    }

    /**
     * Parse the GithubLinguist results.
     *
     * $results can have a look like this:
     *      array(4) {
     *          [0]=> string(11) "87.58%  PHP"
     *          [1]=> string(18) "12.25%  JavaScript"
     *          [2]=> string(12) "0.16%   XSLT"
     *          [3]=> string(13) "0.01%   Shell"
     *      }
     *
     * @param array $results
     * @return array
     */
    protected function parseGithubLinguistResults(array $results)
    {
        $parsedResults = array();

        foreach ($results as $line) {
            // Formats a string from "87.58%  PHP" to "87.58" and "PHP"
            $parts = explode(' ', $line);

            $percent = str_replace(array('%', ' '), '', array_shift($parts));
            $language = array_pop($parts);
            $language = trim($language);
            $parsedResults[] = array(
                'percent' => $percent,
                'language' => $language
            );
        }

        return $parsedResults;
    }

    /**
     * Inserts the github-linguist results in database
     *
     * @param integer $versionId
     * @param array $result
     * @throws \Exception
     */
    protected function storeLinguistDataInDatabase($versionId, array $result)
    {
        $this->getLogger()->info('Store linguist information in database', array('version' => $versionId));
        foreach ($result as $language) {
            $language['version'] = $versionId;
            $insertedId = $this->getDatabase()->insertRecord('linguist', $language);

            if (!$insertedId) {
                $message = 'Insert of language failed';
                $this->getLogger()->critical($message, $language);
                throw new \Exception($message, 1368805993);
            }
        }
    }

    /**
     * Deletes the linguist records of github linguist analyses
     *
     * @param integer $versionId
     * @return void
     * @throws \Exception
     */
    protected function clearLinguistRecordsFromDatabase($versionId)
    {
        $deleteResult = $this->getDatabase()->deleteRecords('linguist', array('version' => intval($versionId)));

        if ($deleteResult === false) {
            $msg = 'Delete of linguist records for version failed';
            $this->getLogger()->critical($msg, array('version' => $versionId));

            $msg = sprintf('Delete of linguist records for version %s failed', $versionId);
            throw new \Exception($msg, 1368805543);
        }
    }

    /**
     * Starts a analysis of a given $dirToAnalyze with github linguist
     *
     * @param string $dirToAnalyze Directory which should be analyzed by github linguist
     * @return array [
     *                  0 => Symfony Process object,
     *                  1 => Exception if one was thrown otherwise null
     *               ]
     */
    private function executeGithubLinguist($dirToAnalyze)
    {
        $config = $this->getConfig();

        // Execute github-linguist
        $dirToAnalyze = rtrim($dirToAnalyze, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $dirToAnalyze = ProcessUtils::escapeArgument($dirToAnalyze);
        $this->getLogger()->info('Analyze with github-linguist', ['directory' => $dirToAnalyze]);

        // TODO fix this ruby command!
        // This command is "broken" with ruby 1.9 (tested with ruby 1.9.3p194 (2012-04-20 revision 35410) [x86_64-linux])
        // Maybe execute bundle via rvm?
        //
        // /.../github-linguist/lib/linguist/generated.rb:41:in `split': invalid byte sequence in US-ASCII (ArgumentError)
        // curl -L https://get.rvm.io | bash -s stable
        // source /home/vagrant/.rvm/scripts/rvm
        //
        // @link http://stackoverflow.com/questions/6583815/how-to-run-bundle-exec-from-a-specific-ruby-version-using-rvm-when-shell-envir
        // @link https://github.com/github/linguist/issues/353
        $command = 'bundle exec linguist ' . $dirToAnalyze;

        $workingDir = $config['Application']['GithubLinguist']['WorkingDir'];

        // Disable process timeout, because pDepend should take a while
        $processTimeout = null;
        $processFactory = new ProcessFactory();
        $process = $processFactory->createProcess($command, $processTimeout, $workingDir);

        $exception = null;
        try {
            $process->run();
        } catch (\Exception $exception) {
            // This catch section is empty, because we got an error handling in the caller area
            // We check not only the exception. We use the result command of the process as well
        }

        return [$process, $exception];
    }
}
