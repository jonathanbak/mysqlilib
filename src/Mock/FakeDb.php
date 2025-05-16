<?php
/**
 * FakeDb - A lightweight in-memory mock DB for unit testing.
 *
 * Supports basic SQL-like operations (SELECT, INSERT, UPDATE, DELETE)
 * using regex parsing. Primarily intended for PHPUnit tests of
 * DB-dependent logic without requiring a real DB connection.
 *
 * @author jonathanbak
 * @package MySQLiLib\Mock
 */

namespace MySQLiLib\Mock;

use MySQLiLib\DbInterface;


/**
 * Class FakeDb
 *
 * Implements DbInterface for testing purposes. Uses in-memory arrays to simulate
 * tables and SQL queries.
 */
class FakeDb implements DbInterface
{
    /**
     * In-memory table data storage.
     *
     * @var array<string, array<int, array<string, mixed>>>
     */
    private $tables = [];

    /**
     * Cached query results to be consumed via fetch().
     *
     * @var array<string, array<int, array<string, mixed>>>
     */
    private $fetched = [];

    /**
     * Simulate DB connection (no-op).
     *
     * @inheritDoc
     */
    public function connect($host, $user, $password, $dbName, $dbPort = 3306)
    {
        return true;
    }

    /**
     * Reset all in-memory data.
     *
     * @inheritDoc
     */
    public function close()
    {
        $this->tables = [];
        $this->fetched = [];
    }

    /**
     * Execute a simplified SQL-like query on in-memory data.
     *
     * Supported operations:
     * - INSERT INTO ... SET ...
     * - SELECT ... WHERE ... (>, <, =, !=, LIKE, IN, LIMIT)
     * - UPDATE ... SET ... WHERE ...
     * - DELETE ... WHERE ...
     *
     * @param string $query  SQL-like query
     * @param array  $params Parameters for placeholders
     * @return bool|int True on success, or affected row count
     */
    public function query($query, $params = [])
    {
        $mdKey = md5($query . json_encode($params));
        $query = trim($query);

        // INSERT INTO `table` SET `col1`=?, `col2`=now()
        if (preg_match('/insert\s+into\s+`?(\w+)`?\s+set\s+(.+)/i', $query, $matches)) {
            $table = $matches[1];
            $setClause = $matches[2];

            preg_match_all('/`?(\w+)`?\s*=\s*(now$begin:math:text$$end:math:text$|\?)/i', $setClause, $cols);
            $row = [];

            foreach ($cols[1] as $i => $colName) {
                $value = strtolower($cols[2][$i]) === 'now()'
                    ? date('Y-m-d H:i:s')
                    : array_shift($params);
                $row[$colName] = $value;
            }

            $this->tables[$table][] = $row;
            return true;
        }

        // SELECT * FROM `table` WHERE `col` {operator} ?
        if (preg_match('/select\s+\*\s+from\s+`?(\w+)`?\s+where\s+`?(\w+)`?\s*(=|!=|<>|<=|>=|<|>)\s*\?/i', $query, $matches)) {
            $table = $matches[1];
            $col = $matches[2];
            $operator = $matches[3];
            $val = $params[0] ?? null;

            $this->fetched[$mdKey] = array_values(array_filter(
                $this->tables[$table] ?? [],
                function ($row) use ($col, $operator, $val) {
                    if (!isset($row[$col])) return false;

                    switch ($operator) {
                        case '=':  return $row[$col] == $val;
                        case '!=':
                        case '<>': return $row[$col] != $val;
                        case '>':  return $row[$col] >  $val;
                        case '<':  return $row[$col] <  $val;
                        case '>=': return $row[$col] >= $val;
                        case '<=': return $row[$col] <= $val;
                        default:   return false;
                    }
                }
            ));
            return true;
        }

        // DELETE FROM `table` WHERE `col` = ?
        if (preg_match('/delete\s+from\s+`?(\w+)`?\s+where\s+`?(\w+)`?\s*=\s*\?/i', $query, $matches)) {
            $table = $matches[1];
            $col = $matches[2];
            $val = isset($params[0]) ? $params[0] : null;

            $before = isset($this->tables[$table]) ? count($this->tables[$table]) : 0;
            $filtered = array();
            foreach (isset($this->tables[$table]) ? $this->tables[$table] : array() as $row) {
                if (!isset($row[$col]) || $row[$col] === $val) {
                    continue;
                }
                $filtered[] = $row;
            }
            $this->tables[$table] = $filtered;
            return count($this->tables[$table]) < $before;
        }

        // UPDATE `table` SET `col`=? WHERE `col2` = ?
        if (preg_match('/update\s+`?(\w+)`?\s+set\s+`?(\w+)`?\s*=\s*\?\s+where\s+`?(\w+)`?\s*=\s*\?/i', $query, $matches)) {
            $table = $matches[1];
            $targetCol = $matches[2];
            $condCol = $matches[3];

            [$newVal, $condVal] = $params;

            $count = 0;
            foreach ($this->tables[$table] ?? [] as $i => $row) {
                if (isset($row[$condCol]) && $row[$condCol] == $condVal) {
                    $this->tables[$table][$i][$targetCol] = $newVal;
                    $count++;
                }
            }
            return $count; // 영향을 받은 행 수 반환
        }

        // SELECT ... LIKE ?
        if (preg_match('/select\s+\*\s+from\s+`?(\w+)`?\s+where\s+`?(\w+)`?\s+like\s+\?/i', $query, $matches)) {
            $table = $matches[1];
            $col = $matches[2];
            $pattern = isset($params[0]) ? $params[0] : '';
            $pattern = str_replace('%', '*', $pattern); // for fnmatch

            $filtered = array();
            $source = isset($this->tables[$table]) ? $this->tables[$table] : array();
            foreach ($source as $row) {
                if (isset($row[$col]) && fnmatch($pattern, $row[$col])) {
                    $filtered[] = $row;
                }
            }

            $this->fetched[$mdKey] = array_values($filtered);
            return true;
        }

        // SELECT ... IN (?, ?, ?)
        if (preg_match('/select\s+\*\s+from\s+`?(\w+)`?\s+where\s+`?(\w+)`?\s+in\s*\((\s*\?,?)+\)/i', $query, $matches)) {
            $table = $matches[1];
            $col = $matches[2];

            $filtered = array();
            $source = isset($this->tables[$table]) ? $this->tables[$table] : array();
            foreach ($source as $row) {
                if (isset($row[$col]) && in_array($row[$col], $params)) {
                    $filtered[] = $row;
                }
            }

            $this->fetched[$mdKey] = array_values($filtered);
            return true;
        }

        // SELECT ... LIMIT N
        if (preg_match('/select\s+\*\s+from\s+`?(\w+)`?(?:\s+where\s+.+?)?\s+limit\s+(\d+)/i', $query, $matches)) {
            $table = $matches[1];
            $limit = (int)$matches[2];
            if (!isset($this->fetched[$mdKey])) {
                // fallback: select all
                $this->fetched[$mdKey] = array_slice($this->tables[$table] ?? [], 0, $limit);
            } else {
                $this->fetched[$mdKey] = array_slice($this->fetched[$table], 0, $limit);
            }
            return true;
        }

        return false;
    }

    /**
     * Fetch a single row from the previously executed SELECT query.
     *
     * @param string $query
     * @param array  $params
     * @return array|null One row as associative array or null if no more rows
     */
    public function fetch($query, $params = [])
    {
        if (!preg_match('/^\s*select/i', $query)) {
            throw new \LogicException("Only SELECT queries can be used with fetch()");
        }

        $mdKey = md5($query . json_encode($params));

        if (!isset($this->fetched[$mdKey])) {
            $this->query($query, $params);  // 이 내부에서 $this->fetched[$mdKey] 가 채워져야 함
        }

        return array_shift($this->fetched[$mdKey]) ?: null;
    }

    /**
     * Seed a table with initial data (used for testing).
     *
     * @param string $table Table name
     * @param array  $rows  Array of associative rows
     * @return void
     */
    public function seed(string $table, array $rows)
    {
        $this->tables[$table] = array_merge($this->tables[$table] ?? [], $rows);
    }

    /**
     * Get all current rows from a table.
     *
     * @param string $table
     * @return array List of rows as associative arrays
     */
    public function getAll(string $table): array
    {
        return $this->tables[$table] ?? [];
    }
}