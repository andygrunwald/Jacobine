<?php
/**
 * This file is part of the Jacobine package.
 *
 * (c) Andreas Grunwald <andygrunwald@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Jacobine\Helper;

use Monolog\Logger;
use Monolog\Handler\NullHandler;
use Monolog\Processor\MemoryPeakUsageProcessor;
use Monolog\Processor\MemoryUsageProcessor;
use Monolog\Processor\ProcessIdProcessor;
use Monolog\Processor\GitProcessor;
use Symfony\Component\Console\Output\ConsoleOutput;
use Jacobine\Monolog\Handler\SymfonyConsoleHandler;

/**
 * Class LoggerFactory
 *
 * Factory to create a Logger.
 *
 * @package Jacobine\Helper
 * @author Andy Grunwald <andygrunwald@gmail.com>
 */
class LoggerFactory
{

    /**
     * Creates a logger with incoming $channelName
     *
     * @param string $channelName
     * @param array $handler
     * @return Logger
     * @throws \Exception
     */
    public function create($channelName, array $handler = [])
    {
        if (empty($channelName)) {
            throw new \Exception('Channel name must not be empty!', 1400362530);
        }

        $logger = new Logger($channelName);

        // If there are no configured handler, add a NullHandler and exit
        if (count($handler) === 0) {
            $logger->pushHandler(new NullHandler());
            return $logger;
        }

        // Add global logProcessors
        $logger->pushProcessor(new ProcessIdProcessor());
        $logger->pushProcessor(new MemoryUsageProcessor());
        $logger->pushProcessor(new MemoryPeakUsageProcessor());
        $logger->pushProcessor(new GitProcessor());

        foreach ($handler as $handlerName => $handlerConfig) {
            $logFileName = $channelName . '-' . strtolower($handlerName);
            $loggerInstance = $this->getHandlerInstance(
                $handlerConfig['class'],
                $handlerConfig,
                $logFileName
            );
            $logger->pushHandler($loggerInstance);
        }

        return $logger;
    }

    /**
     * Creates a handler instance based on a config ($handlerConfig)s
     *
     * @param string $handlerClass
     * @param array $handlerConfig
     * @param string $logFileName
     * @return \Monolog\Handler\HandlerInterface
     * @throws \Exception
     */
    private function getHandlerInstance($handlerClass, $handlerConfig, $logFileName)
    {
        switch ($handlerClass) {

            // Monolog StreamHandler
            case 'StreamHandler':
                $stream = rtrim($handlerConfig['path'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
                $stream .= $logFileName . '.log';

                // Determine LogLevel
                $minLogLevel = Logger::DEBUG;

                $configuredLogLevel = null;
                if (array_key_exists('minLogLevel', $handlerConfig)) {
                    $configuredLogLevel = $handlerConfig['minLogLevel'];
                }

                $configuredLogLevel = strtoupper($configuredLogLevel);
                if ($configuredLogLevel && constant('Monolog\Logger::' . $configuredLogLevel)) {
                    $minLogLevel = constant('Monolog\Logger::' . $configuredLogLevel);
                }

                $instance = new \Monolog\Handler\StreamHandler($stream, $minLogLevel);
                break;

            // Custom SymfonyConsoleHandler
            case 'SymfonyConsoleHandler':
                $consoleHandler = new ConsoleOutput();
                $instance = new SymfonyConsoleHandler($consoleHandler);
                break;

            // If there is another handler, skip it :(
            default:
                throw new \Exception('Configured logger "' . $handlerClass . '" not supported yet', 1368216223);
        }

        return $instance;
    }
}
