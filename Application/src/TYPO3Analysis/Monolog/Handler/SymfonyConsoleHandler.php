<?php
/**
 * @todo adds a description (license text, description of this class / file, etc)
 *
 * This source code was originally written by Vitaliy Zhukv.
 * He tried to submit this as PR to Monolog.
 * His sourcecode was slightly modified to fits TYPO3Analyis needs.
 * Thanks to Vitaliy!
 *
 * @link https://github.com/Seldaek/monolog/pull/156
 */
namespace TYPO3Analysis\Monolog\Handler;

use Symfony\Component\Console\Output\OutputInterface;
use Monolog\Logger;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Formatter\LineFormatter;

/**
 * Handler sending logs to Symfony/Console output
 *
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
        Logger::DEBUG       =>  'debug',
        Logger::NOTICE      =>  'notice',
        Logger::INFO        =>  'info',
        Logger::WARNING     =>  'warning',
        Logger::ERROR       =>  'error',
        Logger::CRITICAL    =>  array('critical', 'error'),
        Logger::ALERT       =>  array('alert', 'error'),
        Logger::EMERGENCY   =>  array('emergency', 'error')
    );

    /**
     * Construct
     *
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @param integer $level
     */
    public function __construct(OutputInterface $output, $level = Logger::DEBUG) {
        $this->consoleOutput = $output;
        parent::__construct($level);
    }

    /**
     * Set level style
     *
     * @param integer $level
     * @param string|array $style
     */
    public function setLevelStyle($level, $style) {
        try {
            Logger::getLevelName($level);
        }
        catch (\InvalidArgumentException $exception) {
            throw new \InvalidArgumentException(sprintf(
                'Can\'t set style "%s" to error level "%s".',
                $style, $level
            ), 0, $exception);
        }

        $this->levelStyles[$level] = $style;

        return $this;
    }

    /**
     * Get level style
     *
     * @param integer $level
     * @return string|array
     */
    public function getLevelStyle($level) {
        if (!isset($this->levelStyles[$level])) {
            throw new \InvalidArgumentException('Level "'.$level.'" is not defined, use one of: '.implode(', ', array_keys($this->levelStyles)));
        }

        return $this->levelStyles[$level];
    }

    /**
     * @{inerhitDoc}
     */
    public function write(array $record) {
        $writeText = $record['formatted'];

        // Check usage formatter
        $formatter = $this->consoleOutput->getFormatter();
        if ($formatter && $formatter->format($writeText) == $writeText) {
            $levelStyle = $this->levelStyles[$record['level']];

            if (is_string($levelStyle)) {
                if ($formatter->hasStyle($levelStyle)) {
                    $writeText = '<' . $levelStyle . '>' . $writeText . '</' . $levelStyle . '>';
                }

            } else if (is_array($levelStyle) || $levelStyle instanceof \Iterator) {
                foreach ($levelStyle as $style) {
                    if ($formatter->hasStyle($style)) {
                        $writeText = '<' . $style . '>' . $writeText . '</' . $style . '>';
                        break;
                    }
                }
            }
        }

        $this->consoleOutput->writeln($writeText);
    }

    /**
     * @{inerhitDoc}
     */
    public function getDefaultFormatter() {
        return new LineFormatter();
    }
}