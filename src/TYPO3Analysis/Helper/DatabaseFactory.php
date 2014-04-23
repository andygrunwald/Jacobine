<?php
/**
 * This file is part of the Jacobine package.
 *
 * (c) Andreas Grunwald <andygrunwald@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TYPO3Analysis\Helper;

/**
 * Class DatabaseFactory
 *
 * Factory to create a database connection.
 * This factory is injected into our database abstraction.
 * This is necessary, because we need to be able to reconnect every time.
 * During runtime this factory will be used to create new database connections.
 *
 * @package TYPO3Analysis\Helper
 * @author Andy Grunwald <andygrunwald@gmail.com>
 */
class DatabaseFactory
{

    /**
     * Factory method to create a database object
     *
     * @param string $driverName
     * @param string $host
     * @param integer $port
     * @param string $username
     * @param string $password
     * @param string $database
     * @return \TYPO3Analysis\Helper\Database
     */
    public function create($driverName, $host, $port, $username, $password, $database)
    {
        $dsn = $this->buildDsn($driverName, $host, $port, $database);
        return new \PDO($dsn, $username, $password);
    }

    /**
     * Builds the DSN for \PDO object
     *
     * @param string $driverName
     * @param string $host
     * @param integer $port
     * @param string $database
     * @return string
     */
    private function buildDsn($driverName, $host, $port, $database)
    {
        $dsn = strtolower($driverName) . ':host=' . $host . ';port=' . intval($port) . ';dbname=' . $database;
        return $dsn;
    }
}
