<?php
/**
 * This file is part of the Jacobine package.
 *
 * (c) Andreas Grunwald <andygrunwald@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Jacobine\Consumer\Crawler;

use Jacobine\Consumer\ConsumerAbstract;
use Jacobine\Component\AMQP\MessageQueue;
use Jacobine\Component\Crawler\CrawlerFactory;
use Jacobine\Component\Process\ProcessFactory;
use Jacobine\Entity\DataSource;
use Symfony\Component\Process\ProcessUtils;
use Buzz\Browser;

/**
 * Class Mailinglist
 *
 * This consumer got two different tasks:
 *  * Crawl a complete mailinglist Server (e.g. Mailman) => type: server
 *    This consumer is responsible to receive all mailinglists from a single mailinglist server
 *    and create + send a separate message for each mailinglist group to the message broker (type: list)
 *  * Crawl a single mailinglist => type: list
 *    This consumer is responsible to receive all posts from a mailinglist.
 *    For this job mlstats (https://github.com/MetricsGrimoire/MailingListStats) is used.
 *
 * Message format (json encoded):
 *  [
 *      type: "server" or "list" to identify the task of the consumer
 *      host: Host of the mailinglist / mailinglist server
 *      project: Project to be analyzed. Must be a configured project in "configFile"
 *  ]
 *
 * Usage:
 *  php console jacobine:consumer Crawler\\Mailinglist
 *
 * @package Jacobine\Consumer\Crawler
 * @author Andy Grunwald <andygrunwald@gmail.com>
 */
class Mailinglist extends ConsumerAbstract
{

    /**
     * HTTP Client
     *
     * @var \Buzz\Browser
     */
    protected $remoteService;

    /**
     * Factory to create DOMCrawler
     *
     * @var \Jacobine\Component\Crawler\CrawlerFactory
     */
    protected $crawlerFactory;

    /**
     * @var \Jacobine\Component\Process\ProcessFactory
     */
    protected $processFactory;

    /**
     * Database credentials for mlstats
     *
     * TODO REFACTOR THIS! Really dirty hack ...
     *
     * @var array
     */
    protected $databaseCredentials = [
        'driver' => '',
        'host' => '',
        'username' => '',
        'password' => '',
        'name' => ''
    ];

    /**
     * Constructor to set dependencies
     *
     * @param MessageQueue $messageQueue
     * @param \Buzz\Browser $remoteService
     * @param CrawlerFactory $crawlerFactory
     * @param ProcessFactory $processFactory
     * @param string $databaseDriver
     * @param string $databaseHost
     * @param string $databaseUsername
     * @param string $databasePassword
     * @param string $databaseName
     */
    public function __construct(
        MessageQueue $messageQueue,
        Browser $remoteService,
        CrawlerFactory $crawlerFactory,
        ProcessFactory $processFactory,
        $databaseDriver,
        $databaseHost,
        $databaseUsername,
        $databasePassword,
        $databaseName
    ) {
        $this->setMessageQueue($messageQueue);
        $this->remoteService = $remoteService;
        $this->crawlerFactory = $crawlerFactory;
        $this->processFactory = $processFactory;

        // TODO DIRTY HACK! Refactor it, please :(
        $this->databaseCredentials['driver'] = $databaseDriver;
        $this->databaseCredentials['host'] = $databaseHost;
        $this->databaseCredentials['username'] = $databaseUsername;
        $this->databaseCredentials['password'] = $databasePassword;
        $this->databaseCredentials['name'] = $databaseName;
    }

    /**
     * Gets a description of the consumer
     *
     * @return string
     */
    public function getDescription()
    {
        return 'Crawls a single mailinglist or a mailinglist server (e.g. Mailman)';
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

        $this->setQueueOption('name', 'crawler.mailinglist');
        $this->enableDeadLettering();

        $this->setRouting('crawler.mailinglist');
    }

    /**
     * The main logic of the consumer
     *
     * Switches between the two main tasks:
     * * Detect crawlable mailing lists
     * * Crawl a single mailinglist
     *
     * @param \stdClass $message
     * @throws \Exception
     * @return void
     */
    protected function process($message)
    {
        $type = strtolower($message->type);
        switch ($type) {
            // A complete mailinglist server (e.g. mailman)
            case DataSource::TYPE_MAILMAN_SERVER:
                $this->processMailinglistServer($message);
                break;

            // A single mailinglist
            case DataSource::TYPE_MAILMAN_LIST:
                // TODO TESTEN UND FIXEN
                $this->processSingleMailinglist($message);
                break;

            default:
                throw new \Exception('Type "' . $type . '" not supported', 1403431389);
        }
    }

    /**
     * Method to crawl a single mailinglist.
     * The crawling is done via mlstats (https://github.com/MetricsGrimoire/MailingListStats).
     * So this method will execute an external binary.
     *
     * TODO Refactor! This is quite the same as in CVSAnaly!
     *
     * @param \stdClass $message
     * @throws \Exception
     */
    private function processSingleMailinglist($message)
    {
        // Further more we got two / three more issues to solve:
        // #45: Changed message_body from TEXT to MEDIUMTEXT, because we got messages > 65.535 bytes
        // @link https://github.com/MetricsGrimoire/MailingListStats/pull/45
        // #40: Raise subject field from 255 to 320 chars
        // @link https://github.com/MetricsGrimoire/MailingListStats/pull/40
        // #22: Error parsing list archive: column `Subject` is not big enough
        // @link https://github.com/MetricsGrimoire/MailingListStats/issues/22
        // TODO Write SQL queries to fix #45, #40 and #22

        // TODO Maybe it would be useful to log the (incremental) output of the commands as wel
        // The incremental can be getted every 5 seconds or something
        // Further more most of the tools logs messages with "\n" in it. How to handle this?
        // Another question is if we can handle stderr + stdout (if the tool throws warnings / errors to stderr).

        /** @var \Symfony\Component\Process\Process $process */
        list($process, $exception) = $this->executeMLStats($message->host);
        $context = $this->getContextOfCommand($process, $exception);
        if ($exception !== null || $process->isSuccessful() === false) {
            $this->getLogger()->critical('mlstats command failed', $context);
            throw new \Exception('mlstats command failed', 1403445667);
        }
    }

    /**
     * Builds the mlstats command
     *
     * TODO Refactor this! This is a REALLY HEAVY HACK to inject all database credentials directly in the command :(
     * Idea: Open a issue at MLStats to do this via config file...
     *
     * @param array $config
     * @param string $url
     * @return string
     */
    private function buildMLStatsCommand(array $config, $url)
    {
        $command = escapeshellcmd($this->container->getParameter('application.mlstats.binary'));
        $command .= ' --no-report';
        $command .= ' --db-driver ' . ProcessUtils::escapeArgument($this->databaseCredentials['driver']);
        $command .= ' --db-hostname ' . ProcessUtils::escapeArgument($this->databaseCredentials['host']);
        $command .= ' --db-user ' . ProcessUtils::escapeArgument($this->databaseCredentials['username']);
        $command .= ' --db-password ' . ProcessUtils::escapeArgument($this->databaseCredentials['password']);
        // TODO Currently we log into mlstats database. Why? See comments in $this->processSingleMailinglist();
        $command .= ' --db-name ' . ProcessUtils::escapeArgument($this->databaseCredentials['name']);
        //$command .= ' --db-name ' . ProcessUtils::escapeArgument('mlstats');
        $command .= ' ' . ProcessUtils::escapeArgument($url);

        return $command;
    }

    /**
     * Starts a crawling mechanism of a given $url with mlstats
     *
     * TODO Refactor! This is quite the same as in CVSAnaly!
     *
     * @param string $url Mailinglist url which should be analyzed by mlstats
     * @return array [
     *                  0 => Symfony Process object,
     *                  1 => Exception if one was thrown otherwise null
     *               ]
     */
    private function executeMLStats($url)
    {
        $this->getLogger()->info('Start crawling mailinglist with mlstats', ['mailinglist' => $url]);

        $config = $this->getConfig();
        $command = $this->buildMLStatsCommand($config, $url);
        $process = $this->processFactory->createProcess($command, null);
        $exception = null;
        try {
            $process->run();

        } catch (\Exception $exception) {
            // This catch section is empty, because we got an error handling in the caller area
            // We check not only the exception. We use the result command of the process as well
        }

        return [$process, $exception];
    }

    /**
     * Crawls a complete mailinglist server and sends messages for every single mailinglist to crawl this list.
     *
     * TODO Refactor this method, because it is MUCH TO LONG!
     *
     * @param \stdClass $message
     * @throws \Exception
     * @return void
     */
    private function processMailinglistServer($message)
    {
        try {
            $content = $this->getContent($this->remoteService, $message->host);

        } catch (\Exception $e) {
            // At first this seems to be not so smart to catch an exception and throw a new one,
            // but i do this because i want to add the custom error message.
            // If there is a better way a pull request is welcome :)
            $context = [
                'project' => $message->project,
                'url' => $message->host,
                'message' => $e->getMessage()
            ];
            $this->getLogger()->error('Reading mailinglist host frontend failed', $context);
            throw new \Exception('Reading mailinglist host frontend failed', 1403431979);
        }

        $remoteClient = $this->remoteService->getClient();
        /** @var $remoteClient \Buzz\Client\Curl */
        $effectiveUrl = $remoteClient->getInfo(CURLINFO_EFFECTIVE_URL);

        // Every mailman overview page got "listinfo" at the end:
        // Listinfo (overview):
        //  FreeBSD:      http://lists.freebsd.org/mailman/listinfo
        //  TYPO3:        http://lists.typo3.org/cgi-bin/mailman/listinfo
        //  LibreSoft.es: https://lists.libresoft.es/mailman/listinfo
        //  ...
        $effectiveUrl = rtrim($effectiveUrl, '/');
        if (substr($effectiveUrl, -8) !== 'listinfo') {
            $context = [
                'project' => $message->project,
                'effectiveUrl' => $effectiveUrl
            ];
            $this->getLogger()->info('String "listinfo" not found in mailman overview url', $context);
            return;
        }

        $crawler = $this->crawlerFactory->create($content);
        /* @var $crawler \Symfony\Component\DomCrawler\Crawler */

        $projectLinks = $crawler->filterXPath('//table/tr/td[1]/a');
        foreach ($projectLinks as $node) {

            // Sometimes we got single list info links as relative or absolute links.
            // We have to ensure that the links are always absolute.
            // Due to some server redirects of the main hosts we have to figure out the real forwarded location.
            // Thats the reason why we have to get the effectiveUrl via curl.
            // E.g.
            //  TYPO3:   http://lists.typo3.org/   => http://lists.typo3.org/cgi-bin/mailman/listinfo
            //  FreeBSD: http://lists.freebsd.org/ => http://lists.freebsd.org/mailman/listinfo
            // If we got relative urls for a single list, this urls are (mostly) prefixed by "listinfo".
            // So we have to strip it and attach the effective url.
            // But if we got an absolute URL the if condition will not match and we still got our absolute url :)
            // Sounds like a hack, right? But do you got a better idea?
            // If yes, please drop me a note via mail or a github issue or something else. Thanks

            // Listinfo (single):
            //  FreeBSD:      http://lists.freebsd.org/mailman/listinfo/ctm-announce
            //  TYPO3:        http://lists.typo3.org/cgi-bin/mailman/listinfo/flow
            //  LibreSoft.es: https://lists.libresoft.es/listinfo/metrics-grimoire
            $listInfoSingle = $node->getAttribute('href');
            if (substr($listInfoSingle, 0, 9) === 'listinfo/') {
                $listInfoSingle = substr($listInfoSingle, 9);
            }

            $listInfoSingle = $this->makeAbsoluteUrl($effectiveUrl, $listInfoSingle);

            try {
                $content = $this->getContent($this->remoteService, $listInfoSingle);

            } catch (\Exception $e) {
                // At first this seems to be not so smart to catch an exception and throw a new one,
                // but i do this because i want to add the custom error message.
                // If there is a better way a pull request is welcome :)
                $context = [
                    'project' => $message->project,
                    'url' => $listInfoSingle,
                    'message' => $e->getMessage()
                ];
                $this->getLogger()->error('Reading mailinglist listinfo page failed', $context);
                throw new \Exception('Reading mailinglist listinfo page failed', 1403431979);
            }

            // Here we try to find the real single mailing list url.
            // We got the same problem as above with the relative and absolute urls.
            // Sadly :(, but we have to handle it, right?
            // Single list:
            //  FreeBSD:      http://lists.freebsd.org/pipermail/ctm-announce/
            //  TYPO3:        http://lists.typo3.org/pipermail/flow/
            //  LibreSoft.es: https://lists.libresoft.es/pipermail/metrics-grimoire/ (public)
            //  LibreSoft.es: https://lists.libresoft.es/private/commit-watchers/ (private)
            $crawler = $this->crawlerFactory->create($content);
            /* @var $crawler \Symfony\Component\DomCrawler\Crawler */
            $detailUrl = $crawler->filterXPath('//table[1]/tr/td[1]/p/a')->attr('href');
            $detailUrl = $this->makeAbsoluteUrl($message->host, $detailUrl);

            // Check if there is a private or public mailing list
            // Public got "/pipermail/" in it
            // Private got "/private/" in it
            // TODO: Currently we (Jacobine) do not support private mailing lists, but this should be implemented
            // in future, because mlstats supports it (via --web-user && --web-password parameter)
            // Until this we skip private mailinglists
            // And we skip wrong links where is no "pipermail" occurrence.
            if (strstr($detailUrl, '/private/') || strstr($detailUrl, '/pipermail/') === false) {
                continue;
            }

            $this->addFurtherMessageToQueue($message->project, $detailUrl);
        }
    }

    /**
     * Returns the content of a web page
     *
     * @param \Buzz\Browser $browser
     * @param string $url
     * @return mixed
     * @throws \Exception
     */
    private function getContent($browser, $url)
    {
        $this->getLogger()->info('Requesting url', ['url' => $url]);
        $response = $browser->get($url);
        /** @var \Buzz\Message\Response $response */

        if ($response->getStatusCode() !== 200) {
            $context = [
                'url' => $url,
                'statusCode' => $response->getStatusCode()
            ];
            $this->getLogger()->error('URL is not crawlable', $context);
            $exceptionMessage = sprintf('URL "%s" is not crawlable', $url);
            throw new \Exception($exceptionMessage, 1403431932);
        }

        return $response->getContent();
    }

    /**
     * Adds new messages to queue system to crawl a single mailinglist
     *
     * @param string $project
     * @param string $detailUrl
     * @return void
     */
    private function addFurtherMessageToQueue($project, $detailUrl)
    {
        $message = [
            'project' => $project,
            'type' => DataSource::TYPE_MAILMAN_LIST,
            'host' => $detailUrl,
        ];
        $exchange = $this->container->getParameter('messagequeue.exchange');

        $this->getMessageQueue()->sendSimpleMessage($message, $exchange, 'crawler.mailinglist');
    }

    /**
     * Ensures that the url is prefixed with a host.
     * Some mailinglists make relative urls to the listinfo pages.
     * But we need always absolute urls (with scheme + host)
     *
     * @param string $host
     * @param string $url
     * @return string
     * @throws \InvalidArgumentException
     */
    private function makeAbsoluteUrl($host, $url)
    {
        if (!$url) {
            throw new \InvalidArgumentException('$url parameter is empty!', 1403434727);
        }
        $absUrl = $url;

        $urlComponents = parse_url($absUrl, PHP_URL_SCHEME);
        // If it is no absolute url (no scheme available) add the host to the url
        if (!$urlComponents && strstr($absUrl, $host) === false) {
            $absUrl = rtrim($host, '/') . '/' . ltrim($url, '/');
        }

        return $absUrl;
    }
}
