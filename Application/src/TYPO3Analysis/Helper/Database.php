<?php
/**
 * @todo adds a description (license text, description of this class / file, etc)
 */
namespace TYPO3Analysis\Helper;

class Database {

    /**
     * Database handle
     *
     * @var \PDO
     */
    protected $handle = null;

    public function __construct($host, $port, $username, $password, $database) {
        $dsn = 'mysql:host=' . $host . ';port=' . intval($port) . ';dbname=' . $database;
        $this->handle = new \PDO($dsn, $username, $password);
    }

    protected function getHandle() {
        return $this->handle;
    }

    protected function buildPreparedParts(array $parts, $implodeGlue) {
        $queryParts = array();
        $prepareParts = array();

        foreach ($parts as $field => $value) {
            $queryParts[] = $field . ' = :' . $field;
            $prepareParts[':' . $field] = $value;
        }

        return array(implode($implodeGlue, $queryParts), $prepareParts);
    }

    public function getRecords(array $fields = array('*'), $table, array $where = array(), $groupBy = '', $orderBy = '', $limit = '') {
        list($where, $prepareParts) = $this->buildPreparedParts($where, ' AND ');
        $query = '
            SELECT ' . implode(',', $fields) . '
            FROM ' . $table . '
            WHERE ' . $where;

        if ($groupBy) {
            $query .= ' GROUP BY ' . $groupBy;
        }

        if ($orderBy) {
            $query .= ' ORDER BY ' . $orderBy;
        }

        if ($limit) {
            $query .= ' LIMIT ' . $limit;
        }

        $statement = $this->getHandle()->prepare($query);
        $statement->execute($prepareParts);
        $result = $statement->fetchAll(\PDO::FETCH_ASSOC);

        return $result;
    }

    public function insertRecord($table, array $data) {
        $preparedValues = array();
        foreach($data as $key => $value) {
            $preparedValues[':' . $key] = $value;
        }

        $query = 'INSERT INTO ' . $table . ' (' . implode(',', array_keys($data)) . ') VALUES (' . implode(',', array_keys($preparedValues)) . ')';
        $statement = $this->getHandle()->prepare($query);
        $statement->execute($preparedValues);

        return $this->getHandle()->lastInsertId();
    }

    public function updateRecord($table, array $data, array $where = array()) {
        list($where, $prepareWhereParts) = $this->buildPreparedParts($where, ' AND ');
        list($update, $prepareUpdateParts) = $this->buildPreparedParts($data, ', ');
        $prepareParts = $prepareWhereParts + $prepareUpdateParts;

        $query = '
            UPDATE ' . $table . '
            SET ' . $update . '
            WHERE ' . $where;

        $statement = $this->getHandle()->prepare($query);
        $result = $statement->execute($prepareParts);

        return $result;
    }
}