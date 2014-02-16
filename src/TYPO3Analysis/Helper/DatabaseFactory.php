<?php
/**
 * @todo adds a description (license text, description of this class / file, etc)
 */
namespace TYPO3Analysis\Helper;

class DatabaseFactory {

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
    public function create($driverName, $host, $port, $username, $password, $database) {
        $dsn = $this->buildDsn($driverName, $host, $port, $database );
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
    private function buildDsn($driverName, $host, $port, $database) {
        $dsn = strtolower($driverName) . ':host=' . $host . ';port=' . intval($port) . ';dbname=' . $database;
        return $dsn;
    }
}