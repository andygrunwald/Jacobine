<?php
/**
 * This file is part of the Jacobine package.
 *
 * (c) Andreas Grunwald <andygrunwald@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Jacobine\Tests\Component\Database;

use PHPUnit_Extensions_Database_DataSet_IDataSet;
use PHPUnit_Extensions_Database_DB_IDatabaseConnection;
use Jacobine\Component\Database\Database;

/**
 * Class DatabaseTest
 *
 * Unit test class for \Jacobine\Component\Database\Database
 *
 * @package Jacobine\Tests\Component\Database
 * @author Andy Grunwald <andygrunwald@gmail.com>
 */
class DatabaseTest extends \PHPUnit_Extensions_Database_TestCase
{

    /**
     * @var \PDO
     */
    protected $databaseConnection;

    public function __construct()
    {
        parent::__construct();
        $this->databaseConnection = $this->createDatabaseConnectionObject();

        // This is not the same as in Database/database-scheme.sql
        // but we test on SQLLite and for the tests we do not need
        // features like unsigned or auto increment or utf8 charsets
        $query = "
            CREATE TABLE `versions` (
                `id` int(11) NOT NULL,
                `branch` varchar(4) DEFAULT NULL,
                `version` varchar(13) DEFAULT NULL,
                `date` varchar(23) DEFAULT NULL,
                `type` varchar(11) DEFAULT NULL,
                `checksum_tar_md5` varchar(32) DEFAULT NULL,
                `checksum_tar_sha1` varchar(40) DEFAULT NULL,
                `checksum_zip_md5` varchar(32) DEFAULT NULL,
                `checksum_zip_sha1` varchar(40) DEFAULT NULL,
                `url_tar` varchar(40) DEFAULT NULL,
                `url_zip` varchar(40) DEFAULT NULL,
                `downloaded` tinyint(1) DEFAULT 0,
                `extracted` tinyint(1) DEFAULT 0,
                `size_tar` int(11) DEFAULT 0,
                PRIMARY KEY (`id`)
            );";

        $this->databaseConnection->query($query);
    }

    /**
     * Method to create the database connection object
     *
     * @return \PDO
     */
    protected function createDatabaseConnectionObject()
    {
        return new \PDO('sqlite::memory:');
    }

    /**
     * Returns the test database connection.
     *
     * @return PHPUnit_Extensions_Database_DB_IDatabaseConnection
     */
    protected function getConnection()
    {
        return $this->createDefaultDBConnection($this->databaseConnection, 'sqlite');
    }

    /**
     * Returns the test dataset.
     *
     * @return PHPUnit_Extensions_Database_DataSet_IDataSet
     */
    protected function getDataSet()
    {
        $dataSetFile = dirname(__FILE__) . '/../Fixtures/DatabaseTables/Versions.xml';
        return $this->createXMLDataSet($dataSetFile);
    }

    /**
     * Returns a Database object.
     * This is used with a PDO / sqllite ::memory: connection to execute DBUnit tests
     *
     * @param \PDO $databaseConnection
     * @return Database
     */
    protected function getDatabaseObject($databaseConnection)
    {
        // Mock of \Jacobine\Component\Database\DatabaseFactory
        $factory = $this->getMock('Jacobine\Component\Database\DatabaseFactory');
        $factory->expects($this->any())
                ->method('create')
                ->will($this->returnValue($databaseConnection));

        $driver = 'mysql';
        $host = 'localhost';
        $port = 3306;
        $username = 'phpunit';
        $password = '';
        $database = 'testcase';

        return new Database($factory, $driver, $host, $port, $username, $password, $database);
    }

    /**
     * Returns an array for ->errorInfo method
     *
     * @link http://de2.php.net/manual/en/pdostatement.errorinfo.php
     * @param integer $errorInfoCode
     * @return array
     */
    public function getPDOErrorInfo($errorInfoCode)
    {
        $errorInfoCode = (int) $errorInfoCode;
        switch ($errorInfoCode) {
            case 2006:
                $errorInfoMessage = 'MySQL server has gone away';
                break;
            default:
                $errorInfoMessage = 'Driver specific error message';
        }

        $errorInfo = [
            // SQLSTATE error code (a five characters alphanumeric identifier defined in the ANSI SQL standard)
            0 => 'tests',
            // Driver specific error code
            1 => $errorInfoCode,
            // Driver specific error message
            2 => $errorInfoMessage
        ];

        return $errorInfo;
    }

    /**
     * Returns a Database object
     * with a full mock of a PDO object to execute Unit tests
     *
     * @param integer $errorInfoCode
     * @return Database
     */
    protected function getDatabaseObjectWithMockedPDO($errorInfoCode)
    {
        // First Mock of \PDOStatement
        $pdoStatementMock = $this->getMock('\PDOStatement', ['errorInfo', 'execute']);

        $errorInfo = $this->getPDOErrorInfo($errorInfoCode);
        $pdoStatementMock->expects($this->once())
                         ->method('errorInfo')
                         ->will($this->returnValue($errorInfo));

        $pdoStatementMock->expects($this->any())
                         ->method('execute')
                         ->will($this->returnValue(false));

        // Mock of \PDO
        $pdoMock = $this->getMock('\PDOMock', ['prepare']);
        $pdoMock->expects($this->any())
                ->method('prepare')
                ->will($this->returnValue($pdoStatementMock));

        return $this->getDatabaseObject($pdoMock);
    }

    public function testInsertWithSuccess()
    {
        $dataToInsert = [
            'id' => 6,
            'branch' => '6.2',
            'version' => '6.2.0alpha2',
            'date' => '2013-07-11 13:34:39 UTC',
            'type' => 'development',
            'checksum_tar_md5' => 'd528f7abe0290bfb4302b7ef7b64a2fe',
            'checksum_tar_sha1' => 'bf50ae8180604a8232fba3f70380da650b3266a1',
            'checksum_zip_md5' => 'd71b97d09cb1719b76ba3bd86efaba19',
            'checksum_zip_sha1' => 'a6d9478fef4a9949931152bcecc82caa0cc0c8',
            'url_tar' => 'http://get.typo3.org/6.2.0alpha2',
            'url_zip' => 'http://get.typo3.org/6.2.0alpha2/zip',
            'downloaded' => 1,
            'extracted' => 1,
            'size_tar' => 20865202,
        ];

        $expectedRowCount = $this->getConnection()->getRowCount('versions') + 1;
        $database = $this->getDatabaseObject($this->databaseConnection);
        $insertedId = $database->insertRecord('versions', $dataToInsert);

        $this->assertEquals($expectedRowCount, $this->getConnection()->getRowCount('versions'));
        $this->assertEquals($expectedRowCount, $insertedId);
    }

    public function testInsertWithoutTable()
    {
        $this->setExpectedException('\UnexpectedValueException');

        $dataToInsert = [
            'id' => 7
        ];

        $database = $this->getDatabaseObject($this->databaseConnection);
        $database->insertRecord('', $dataToInsert);
    }

    public function testInsertWithoutData()
    {
        $this->setExpectedException('\UnexpectedValueException');

        $database = $this->getDatabaseObject($this->databaseConnection);
        $database->insertRecord('versions', []);
    }

    public function testUpdateWithoutTable()
    {
        $this->setExpectedException('\UnexpectedValueException');

        $dataToUpdate = [
            'downloaded' => 0
        ];

        $database = $this->getDatabaseObject($this->databaseConnection);
        $database->updateRecord('', $dataToUpdate);
    }

    public function testUpdateWithoutData()
    {
        $this->setExpectedException('\UnexpectedValueException');

        $database = $this->getDatabaseObject($this->databaseConnection);
        $database->updateRecord('versions', []);
    }

    public function testUpdateWithWhere()
    {
        $dataToUpdate = [
            'downloaded' => 0,
            'extracted' => 2
        ];
        $where = ['id' => 3];
        $rowCountBeforeUpdate = $this->getConnection()->getRowCount('versions');

        $database = $this->getDatabaseObject($this->databaseConnection);
        $database->updateRecord('versions', $dataToUpdate, $where);

        $queryTable = $this->getConnection()->createQueryTable('versions', 'SELECT * FROM versions WHERE id = 3');

        $dataSetFile = dirname(__FILE__) . '/../Fixtures/DatabaseTables/VersionsUpdateWithWhere.xml';
        $expectedTable = $this->createXMLDataSet($dataSetFile)->getTable('versions');

        $this->assertTablesEqual($expectedTable, $queryTable);
        $this->assertSame($rowCountBeforeUpdate, $this->getConnection()->getRowCount('versions'));
    }

    public function testUpdateWithoutWhere()
    {
        $dataToUpdate = [
            'type' => 'Unittest',
            'url_zip' => 'http://example.org/this/is/a/test.zip',
            'extracted' => 0
        ];
        $rowCountBeforeUpdate = $this->getConnection()->getRowCount('versions');

        $database = $this->getDatabaseObject($this->databaseConnection);
        $database->updateRecord('versions', $dataToUpdate);

        $queryTable = $this->getConnection()->createQueryTable('versions', 'SELECT * FROM versions');

        $dataSetFile = dirname(__FILE__) . '/../Fixtures/DatabaseTables/VersionsUpdateWithoutWhere.xml';
        $expectedTable = $this->createXMLDataSet($dataSetFile)->getTable('versions');

        $this->assertTablesEqual($expectedTable, $queryTable);
        $this->assertSame($rowCountBeforeUpdate, $this->getConnection()->getRowCount('versions'));
    }

    public function testDeleteWithoutTable()
    {
        $this->setExpectedException('\UnexpectedValueException');

        $where = [
            'downloaded' => 0
        ];

        $database = $this->getDatabaseObject($this->databaseConnection);
        $database->deleteRecords('', $where);
    }

    public function testDeleteWithoutWhere()
    {
        $this->setExpectedException('\UnexpectedValueException');

        $database = $this->getDatabaseObject($this->databaseConnection);
        $database->deleteRecords('versions', []);
    }

    public function testDeleteOfASingleRow()
    {
        $where = [
            'id' => 2
        ];
        $expectedRowCount = $this->getConnection()->getRowCount('versions') - 1;

        $database = $this->getDatabaseObject($this->databaseConnection);
        $database->deleteRecords('versions', $where);

        $queryTable = $this->getConnection()->createQueryTable('versions', 'SELECT * FROM versions');

        $dataSetFile = dirname(__FILE__) . '/../Fixtures/DatabaseTables/VersionsDeleteSingleRow.xml';
        $expectedTable = $this->createXMLDataSet($dataSetFile)->getTable('versions');

        $this->assertTablesEqual($expectedTable, $queryTable);
        $this->assertSame($expectedRowCount, $this->getConnection()->getRowCount('versions'));
    }

    public function testGetRecordsWithMultipleRows()
    {
        $database = $this->getDatabaseObject($this->databaseConnection);

        $fields = ['id'];
        $table = 'versions';
        $where = ['type' => 'development'];
        $result = $database->getRecords($fields, $table, $where);

        $this->assertInternalType('array', $result);
        $this->assertSame(5, count($result));
        $this->assertArrayHasKey('id', $result[0]);
        $this->assertSame('3', $result[2]['id']);
    }

    public function testGetRecordsWithGroupBy()
    {
        $database = $this->getDatabaseObject($this->databaseConnection);

        $fields = ['id'];
        $table = 'versions';
        $where = ['type' => 'development'];
        $groupBy = 'branch';
        $result = $database->getRecords($fields, $table, $where, $groupBy);

        $this->assertInternalType('array', $result);
        $this->assertSame(1, count($result));
        $this->assertArrayHasKey('id', $result[0]);
        $this->assertSame('5', $result[0]['id']);
    }

    public function testGetRecordsWithOrderBy()
    {
        $database = $this->getDatabaseObject($this->databaseConnection);

        $fields = ['id'];
        $table = 'versions';
        $where = ['type' => 'development'];
        $orderBy = 'size_tar';
        $result = $database->getRecords($fields, $table, $where, '', $orderBy);

        $this->assertInternalType('array', $result);
        $this->assertSame(5, count($result));
        $this->assertArrayHasKey('id', $result[0]);
        $this->assertSame('3', $result[2]['id']);
    }

    public function testGetRecordsWithLimit()
    {
        $database = $this->getDatabaseObject($this->databaseConnection);

        $fields = ['id'];
        $table = 'versions';
        $where = ['type' => 'development'];
        $limit = '4';
        $result = $database->getRecords($fields, $table, $where, '', '', $limit);

        $this->assertInternalType('array', $result);
        $this->assertSame(4, count($result));
        $this->assertArrayHasKey('id', $result[0]);
        $this->assertSame('2', $result[1]['id']);
    }

    public function testDatabaseReconnectWithUndefinedDatabaseError()
    {
        $this->setExpectedException('\Exception');

        $database = $this->getDatabaseObjectWithMockedPDO(9999);

        $table = 'versions';
        $where = ['type' => 'development'];
        $database->deleteRecords($table, $where);
    }
}
