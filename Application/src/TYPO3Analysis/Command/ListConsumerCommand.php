<?php
/**
 * @todo adds a description (license text, description of this class / file, etc)
 */
namespace TYPO3Analysis\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

class ListConsumerCommand extends Command {

    /**
     * Base path of library
     *
     * @var String
     */
    const BASE_PATH = '/var/application/src/';

    /**
     * Base namespace of consumer
     *
     * @var String
     */
    const BASE_NAMESPACE = 'TYPO3Analysis\Consumer\\';

    /**
     * Pad length for consumer name
     *
     * @var int
     */
    const PAD_LENGTH = 30;

    /**
     * Path of consumer
     *
     * @var String
     */
    protected $consumerPath = null;

    protected function configure() {
        $this->setName('analysis:list-consumer')
             ->setDescription('Lists all available consumer');
    }

    /**
     * Sets the consumer path
     *
     * @param String $consumerPath
     */
    public function setConsumerPath($consumerPath) {
        $this->consumerPath = $consumerPath;
    }

    /**
     * Gets the consumer path
     *
     * @return String
     */
    public function getConsumerPath() {
        return $this->consumerPath;
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $consumerPath = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'Consumer';
        $consumerPath = realpath($consumerPath);
        $this->setConsumerPath($consumerPath);
    }

    protected function execute(InputInterface $input, OutputInterface $output) {

        $path = $this->getConsumerPath();
        $path = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        $finder = new Finder();
        $finder->files()->in($path)->name('*.php')->notName('*Abstract.php')->notName('*Interface.php');

        foreach ($finder as $file) {
            /* @var $file SplFileInfo */
            $className = $file->getRealpath();
            $className = str_replace(self::BASE_PATH, '', $className);
            $className = substr($className, 0, -4);
            $className = '\\' . str_replace(DIRECTORY_SEPARATOR, '\\', $className);

            $consumer = new $className();
            $reflection = new \ReflectionClass($consumer);

            $parentClass = $reflection->getParentClass();
            if ($parentClass === false || $parentClass->getName() !== 'TYPO3Analysis\Consumer\ConsumerAbstract') {
                continue;
            }

            $consumerName = str_replace(self::BASE_NAMESPACE, '', $className);
            $consumerName = substr($consumerName, 1, strlen($consumerName) - 1);
            $consumerName = str_replace('\\', '\\\\', $consumerName);

            $message = str_pad($consumerName, self::PAD_LENGTH, ' ');
            $message = '<comment>' . $message . '</comment>';
            $message .= '<comment>' . $consumer->getDescription() . '</comment>';
            $output->writeln($message);
        }

        return true;
    }
}