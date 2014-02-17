<?php
/**
 * @todo adds a description (license text, description of this class / file, etc)
 */
namespace TYPO3Analysis\Tests\Helper;

use TYPO3Analysis\Helper\DatabaseFactory;

class DatabaseFactoryTest extends \PHPUnit_Framework_TestCase
{

    /**
     * This unit test is a little bit wired.
     * We try to test the DatabaseFactory. The DatabaseFactory has to return a valid PDO object.
     * But to create a PDO object you need a database.
     * This behaviour moves a unit test to an integration test.
     * A \PDO object creates the connection in the constructor.
     * Due to this there is no way to check if the factory returns a \PDO object in a _unit_ test.
     * If you know a way how to do this, please ping me.
     *
     * Instead of checking the return value, a \PDOException is thrown if the connection cant be build.
     * Here we check of this exception.
     * This does not mean that the factory returns the object.
     * But it does mean that the factory does (something) with \PDO.
     * Not good but better than nothing.
     */
    public function testFactoryReturnsPDOObject()
    {
        $exceptionCode = 2002;
        $exceptionMessage = 'SQLSTATE[HY000] [2002] No such file or directory';
        $this->setExpectedException('\PDOException', $exceptionMessage, $exceptionCode);

        $driver = 'mysql';
        $host = 'localhost';
        $port = 3306;
        $username = 'phpunit';
        $passwort = '';
        $database = 'testcase';

        $databaseFactory = new DatabaseFactory();
        $databaseFactory->create($driver, $host, $port, $username, $passwort, $database);
    }
}
