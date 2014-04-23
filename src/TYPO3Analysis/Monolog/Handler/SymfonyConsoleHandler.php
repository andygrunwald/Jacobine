<?php
/**
 * This file is part of the Jacobine package.
 *
 * (c) Andreas Grunwald <andygrunwald@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TYPO3Analysis\Monolog\Handler;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class SymfonyConsoleHandler
 *
 * Monolog handler to send log lines via Symfony/Console.
 * This class is really useful during development and debugging.
 *
 * This source code was originally written by Vitaliy Zhukv.
 * He tried to submit this as PR to Monolog.
 * His sourcecode was slightly modified to fits TYPO3Analyis needs.
 * Thanks to Vitaliy!
 *
 * @link https://github.com/Seldaek/monolog/pull/156
 *
 * @package TYPO3Analysis\Monolog\Handler
 * @author Andy Grunwald <andygrunwald@gmail.com>
 * @author Vitaliy Zhukv <zhuk2205@gmail.com>
 */
class SymfonyConsoleHandler extends AbstractProcessingHandler
{

    /**
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    protected $consoleOutput;

    /**
     * @var array
     */
    protected $levelStyles = array(
        Logger::DEBUG => 'debug',
        Logger::NOTICE => 'notice',
        Logger::INFO => 'info',
        Logger::WARNING => 'warning',
        Logger::ERROR => 'error',
        Logger::CRITICAL => array('critical', 'error'),
        Logger::ALERT => array('alert', 'error'),
        Logger::EMERGENCY => array('emergency', 'error')
    );

    /**
     * Construct
     *
     * @param OutputInterface $output
     * @param bool|int $level
     * @return \TYPO3Analysis\Monolog\Handler\SymfonyConsoleHandler
     */
    public function __construct(OutputInterface $output, $level = Logger::DEBUG)
    {
        $this->consoleOutput = $output;
        parent::__construct($level);
    }

    /**
     * {@inheritdoc}
     */
    public function write(array $record)
    {
        $writeText = $record['formatted'];

        // Check usage formatter
        $formatter = $this->consoleOutput->getFormatter();
        if ($formatter && $formatter->format($writeText) == $writeText) {
            $levelStyle = $this->levelStyles[$record['level']];

            if (is_string($levelStyle)) {
                if ($formatter->hasStyle($levelStyle)) {
                    $writeText = '<' . $levelStyle . '>' . $writeText . '</' . $levelStyle . '>';
                }

            } else {
                if (is_array($levelStyle) || $levelStyle instanceof \Iterator) {
                    foreach ($levelStyle as $style) {
                        if ($formatter->hasStyle($style)) {
                            $writeText = '<' . $style . '>' . $writeText . '</' . $style . '>';
                            break;
                        }
                    }
                }
            }
        }

        $this->consoleOutput->write($writeText);
    }
}
