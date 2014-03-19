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
        $this->setQueue('analysis.linguist');
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

        $this->getLogger()->info('Receiving message', (array)$messageData);

        // If there is no directory to analyse, exit here
        if (is_dir($messageData->directory) !== true) {
            $this->getLogger()->critical('Directory does not exist', array('directory' => $messageData->directory));
            $this->acknowledgeMessage($this->getMessage());
            return;
        }

        try {
            $this->clearLinguistRecordsFromDatabase($messageData->versionId);
        } catch (\Exception $e) {
            $this->acknowledgeMessage($message);
            return;
        }

        $config = $this->getConfig();
        $workingDir = $config['Application']['GithubLinguist']['WorkingDir'];
        chdir($workingDir);

        // Execute github-linguist
        $dirToAnalyze = rtrim($messageData->directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $command = 'bundle exec linguist ' . escapeshellarg($dirToAnalyze);

        $this->getLogger()->info('Analyze with github-linguist', array('directory' => $dirToAnalyze));

        try {
            $output = $this->executeCommand($command);
        } catch (\Exception $e) {
            $this->acknowledgeMessage($this->getMessage());
            return;
        }

        if ($output === array()) {
            $msg = 'github-linguist returns no result';
            $this->getLogger()->critical($msg);
            $this->acknowledgeMessage($this->getMessage());
            return;
        }

        $parsedResults = $this->parseGithubLinguistResults($output);

        // Store the github linguist results
        try {
            $this->storeLinguistDataInDatabase($messageData->versionId, $parsedResults);
        } catch (\Exception $e) {
            $this->acknowledgeMessage($message);
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
                $this->getLogger($message, $language);
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
            $msg = 'Delete of inguist records for version failed';
            $this->getLogger()->critical($msg, array('version' => $versionId));

            $msg = sprintf('Delete of inguist records for version %s failed', $versionId);
            throw new \Exception($msg, 1368805543);
        }
    }
}
