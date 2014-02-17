<?php
/**
 * @todo adds a description (license text, description of this class / file, etc)
 */
namespace TYPO3Analysis\Tests\Helper;

use PHPUnit_Extensions_Database_DataSet_IDataSet;
use PHPUnit_Extensions_Database_DB_IDatabaseConnection;
use TYPO3Analysis\Helper\Database;

class DatabaseTest extends \PHPUnit_Extensions_Database_TestCase
{

    /**
     * @var \PDO
     */
    protected $databaseConnection;

    public function __construct() {
        $this->databaseConnection = new \PDO('sqlite::memory:');

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
        $dataSetFile = dirname(__FILE__) . '/../Fixtures/Helper/Versions.xml';
        return $this->createXMLDataSet($dataSetFile);
    }

    protected function getDatabaseObject() {
        // Create a DatabaseFactory mock object
        $factory = $this->getMock('TYPO3Analysis\Helper\DatabaseFactory');
        $factory->expects($this->once())
                ->method('create')
                ->will($this->returnValue($this->databaseConnection));

        $host = 'localhost';
        $port = 3306;
        $username = 'phpunit';
        $passwort = '';
        $database = 'testcase';

        return new Database($factory, $host, $port, $username, $passwort, $database);
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
        $database = $this->getDatabaseObject();
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

        $database = $this->getDatabaseObject();
        $database->insertRecord('', $dataToInsert);
    }

    public function testInsertWithoutData()
    {
        $this->setExpectedException('\UnexpectedValueException');

        $database = $this->getDatabaseObject();
        $database->insertRecord('versions', []);
    }

    public function testUpdateWithoutTable()
    {
        $this->setExpectedException('\UnexpectedValueException');

        $dataToUpdate = [
            'downloaded' => 0
        ];

        $database = $this->getDatabaseObject();
        $database->updateRecord('', $dataToUpdate);
    }

    public function testUpdateWithoutData()
    {
        $this->setExpectedException('\UnexpectedValueException');

        $database = $this->getDatabaseObject();
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

        $database = $this->getDatabaseObject();
        $database->updateRecord('versions', $dataToUpdate, $where);

        $queryTable = $this->getConnection()->createQueryTable('versions', 'SELECT * FROM versions WHERE id = 3');

        $dataSetFile = dirname(__FILE__) . '/../Fixtures/Helper/VersionsUpdateWithWhere.xml';
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

        $database = $this->getDatabaseObject();
        $database->updateRecord('versions', $dataToUpdate);

        $queryTable = $this->getConnection()->createQueryTable('versions', 'SELECT * FROM versions');

        $dataSetFile = dirname(__FILE__) . '/../Fixtures/Helper/VersionsUpdateWithoutWhere.xml';
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

        $database = $this->getDatabaseObject();
        $database->deleteRecords('', $where);
    }

    public function testDeleteWithoutWhere()
    {
        $this->setExpectedException('\UnexpectedValueException');

        $database = $this->getDatabaseObject();
        $database->deleteRecords('versions', []);
    }

    public function testDeleteOfASingleRow()
    {
        $where = [
            'id' => 2
        ];
        $expectedRowCount = $this->getConnection()->getRowCount('versions') - 1;

        $database = $this->getDatabaseObject();
        $database->deleteRecords('versions', $where);

        $queryTable = $this->getConnection()->createQueryTable('versions', 'SELECT * FROM versions');

        $dataSetFile = dirname(__FILE__) . '/../Fixtures/Helper/VersionsDeleteSingleRow.xml';
        $expectedTable = $this->createXMLDataSet($dataSetFile)->getTable('versions');

        $this->assertTablesEqual($expectedTable, $queryTable);
        $this->assertSame($expectedRowCount, $this->getConnection()->getRowCount('versions'));
    }

    public function testGetRecordsWithMultipleRows() {
        $database = $this->getDatabaseObject();

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
        $database = $this->getDatabaseObject();

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
        $database = $this->getDatabaseObject();

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
        $database = $this->getDatabaseObject();

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
}
