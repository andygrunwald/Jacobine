<?php
/**
 * This file is part of the Jacobine package.
 *
 * (c) Andreas Grunwald <andygrunwald@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Jacobine\Tests\Consumer\Extract;

use Jacobine\Tests\Consumer\ConsumerTestAbstract;
use Jacobine\Consumer\Extract\Targz;

/**
 * Class TargzTest
 *
 * Unit test class for \Jacobine\Consumer\Extract\Targz
 *
 * @package Jacobine\Tests\Consumer\Extract
 * @author Andy Grunwald <andygrunwald@gmail.com>
 */
class TargzTest extends ConsumerTestAbstract
{

    public function setUp()
    {
        $databaseMock = $this->getDatabaseMock();
        $processFactoryMock = $this->getProcessFactoryMock();
        $loggerMock = $this->getLoggerMock();

        $this->consumer = new Targz($databaseMock, $processFactoryMock);
        $this->consumer->setLogger($loggerMock);
    }

    public function testVersionRecordDoesNotExists()
    {
        $data = [
            'versionId' => 0
        ];
        $message = $this->generateMessage($data, 1);

        $this->consumer->consume($message);
    }

    public function testRecordAlreadyExtracted()
    {
        $data = [
            'versionId' => 5
        ];
        $message = $this->generateMessage($data, 0);

        $versionRow = [
            [
                'id' => 5,
                'version' => '5.2',
                'extracted' => 1
            ]
        ];
        $databaseMock = $this->consumer->getDatabase();
        $databaseMock = $this->mockGetRecordsForDatabaseMock($databaseMock, $versionRow);
        $this->consumer->setDatabase($databaseMock);

        $this->consumer->consume($message);
    }

    public function testFilenameDoesNotExists()
    {
        $data = [
            'versionId' => 5,
            'filename' => '/this/directory/does/not/exists.tar.gz'
        ];
        $message = $this->generateMessage($data, 1);

        $versionRow = [
            [
                'id' => 5,
                'version' => '5.2',
                'extracted' => 0
            ]
        ];
        $databaseMock = $this->consumer->getDatabase();
        $databaseMock = $this->mockGetRecordsForDatabaseMock($databaseMock, $versionRow);
        $this->consumer->setDatabase($databaseMock);

        $this->consumer->consume($message);
    }
}
