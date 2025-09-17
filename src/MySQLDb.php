<?php
/**
 * MySQL Database wrapper implementation.
 *
 * @package MySQLiLib
 * @author jonathanbak
 * @since 2017-02-01
 */

namespace MySQLiLib;

class MySQLDb extends DbAbstract
{
    /** @var array Prepared statement cache */
    private $stmt_map = [];

    /** @var array Row count of each query */
    private $result_total_rows = [];

    /** @var array Current row index for each query */
    private $result_current_row = [];

    /** @var array Bind type and params cache */
    private $bind_map = [];

    /** @var string Bind type string (e.g., 'iss') */
    protected $bind_type = '';

    /** @var array Bind parameter values */
    protected $params = [];

    /**
     * {@inheritdoc}
     */
    public function connect($host, $user, $password, $dbName, $dbPort = '3306')
    {
        try {
            $connection = mysqli_connect($host, $user, $password, $dbName, $dbPort);

            return $connection;
        } catch (\Throwable $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Set bind type and parameters manually.
     *
     * @param string $type
     * @param array $params
     * @return $this
     */
    public function bind_param($type, $params = array())
    {
        $this->bind_type = $type;
        $this->params = $params;
        return $this;
    }

    private function countPreparedPlaceholders($sql) {
        // 문자열 리터럴('...') 제거
        $sqlWithoutStrings = preg_replace("/'([^'\\\\]*(?:\\\\.[^'\\\\]*)*)'/", '', $sql);
        return substr_count($sqlWithoutStrings, '?');
    }

    /**
     * Execute a query with optional parameters.
     * Supports caching of prepared statements.
     *
     * @param string $query
     * @param array|null $params
     * @return mixed
     * @throws Exception
     */
    public function query($query, $params = null)
    {
        try {
            if (!is_string($query)) {
                throw new Exception("Invalid query format. Expected string, got " . gettype($query));
            }

            $mdKey = md5($query);
            if ($params !== null) {
                $this->params = is_array($params) ? $params : array($params);
            } else{
                // 이전 쿼리의 파라미터를 끌고오지 않도록 초기화
                $this->params = [];
            }

            $bindParams = $this->params;

            if (!isset($this->stmt_map[$mdKey])) {
                // 초기 prepare
                if (!empty($this->bind_type) && !empty($bindParams)) {
                    $this->stmt_map[$mdKey] = mysqli_prepare($this->connection, $query);
                    $this->bindParamsToStmt($this->stmt_map[$mdKey], $this->bind_type, $bindParams);
                    $this->stmt_map[$mdKey]->execute();

                    $this->bind_map[$mdKey] = [
                        'type' => $this->bind_type,
                        'params' => $bindParams
                    ];
                    $this->resetBinding();
                } elseif (!empty($bindParams)) {
                    // Named params 또는 일반 positional
                    if (preg_match_all('/:([a-zA-Z][a-zA-Z0-9_-]*)/i', $query, $tmpMatches)) {
                        list($parsedQuery, $orderedParams) = $this->parseNamedParamsToPositional($query, $bindParams);
                        $types = $this->getBindTypes($orderedParams);
                        $this->stmt_map[$mdKey] = mysqli_prepare($this->connection, $parsedQuery);
                        $this->bindParamsToStmt($this->stmt_map[$mdKey], $types, $orderedParams);
                    } else {
                        $types = $this->getBindTypes($bindParams);
                        $this->stmt_map[$mdKey] = mysqli_prepare($this->connection, $query);
                        $this->bindParamsToStmt($this->stmt_map[$mdKey], $types, $bindParams);
                    }
                    $this->stmt_map[$mdKey]->execute();
                    $this->bind_map[$mdKey] = [
                        'type' => $types,
                        'params' => $bindParams
                    ];
                    $this->resetBinding();
                } else {
                    // 파라미터 없음 (단순 쿼리)
                    $result = mysqli_query($this->connection, $query);
                    if (mysqli_errno($this->connection)) {
                        throw new Exception(mysqli_error($this->connection), mysqli_errno($this->connection));
                    }
                    return $result;
                }
            } else {
                // 기존 stmt 재사용
                if (!empty($bindParams)) {
                    $types = !empty($this->bind_type)
                        ? $this->bind_type
                        : (isset($this->bind_map[$mdKey]['type']) ? $this->bind_map[$mdKey]['type'] : $this->getBindTypes($bindParams));
                    $this->bindParamsToStmt($this->stmt_map[$mdKey], $types, $bindParams);

                    // 초기 바인딩 캐시 없었다면 저장
                    if (!isset($this->bind_map[$mdKey])) {
                        $this->bind_map[$mdKey] = [
                            'type' => $types,
                            'params' => $bindParams
                        ];
                    }

                    $this->resetBinding();
                } else {
                    $params = !empty($this->bind_map[$mdKey]['params']) ? $this->bind_map[$mdKey]['params'] : [];
                    $types = !empty($this->bind_type)
                        ? $this->bind_type
                        : (isset($this->bind_map[$mdKey]['type']) ? $this->bind_map[$mdKey]['type'] : $this->getBindTypes($params));
                    $this->bindParamsToStmt($this->stmt_map[$mdKey], $types, $params);
                }

                $this->stmt_map[$mdKey]->execute();
            }

            if (mysqli_errno($this->connection)) {
                throw new Exception(mysqli_error($this->connection), mysqli_errno($this->connection));
            }

            if ($this->stmt_map[$mdKey]->field_count > 0) {
                return $this->stmt_map[$mdKey]->get_result();
            } else {
                $this->resetBinding();
                return true;
            }

        } catch (\mysqli_sql_exception $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e);
        } catch (\Throwable $e) {
            throw new Exception("Unexpected error in query(): " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Fetch a single row from a query result.
     *
     * @param string $query
     * @param array|null $params
     * @return array|null
     * @throws Exception
     */
    public function fetch($query, $params = NULL)
    {
        try {
            if (!is_string($query)) {
                throw new Exception("Invalid query format. Expected string, got " . gettype($query));
            }

            $mdKey = md5($query);

            if ($params !== NULL) {
                $this->params = is_array($params) ? $params : [$params];
            }

            if (!isset($this->result_query[$mdKey])) {
                $this->result_query[$mdKey] = $this->query($query, $this->params);
                $this->result_total_rows[$mdKey] = $this->result_query[$mdKey]->num_rows ?? 0;
                $this->result_current_row[$mdKey] = 0;
            } else {
                $this->result_current_row[$mdKey]++;
            }

            if ($this->result_query[$mdKey] instanceof \mysqli_result) {
                $row = $this->result_query[$mdKey]->fetch_assoc();
                if (!$row) {
                    unset($this->result_query[$mdKey], $this->result_total_rows[$mdKey], $this->result_current_row[$mdKey], $this->stmt_map[$mdKey], $this->bind_map[$mdKey]);
                    $this->resetBinding();
                }
                return $row;
            }

            unset($this->result_query[$mdKey], $this->result_total_rows[$mdKey], $this->result_current_row[$mdKey], $this->stmt_map[$mdKey], $this->bind_map[$mdKey]);
            $this->resetBinding();
            return null;

        } catch (\mysqli_sql_exception $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e);
        } catch (\Throwable $e) {
            throw new Exception("Unexpected error in fetch(): " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * PHP 5.6+ 호환용 bind_param 처리 함수
     *
     * @param \mysqli_stmt $stmt
     * @param string $bindType
     * @param array $params
     * @return void
     */
    protected function bindParamsToStmt($stmt, $bindType, array $params)
    {
        if (version_compare(PHP_VERSION, '5.6.0', '>=')) {
            $stmt->bind_param($bindType, ...$params);
        } else {
            call_user_func_array([$stmt, 'bind_param'], array_merge([$bindType], $params));
        }
    }

    /**
     * Get the last error message from the connection.
     *
     * @return string
     */
    public function error()
    {
        return mysqli_error($this->connection);
    }

    /**
     * Get the last error code from the connection.
     *
     * @return int
     */
    public function errorNo()
    {
        return mysqli_errno($this->connection);
    }

    /**
     * Fetch a single row using fetch_assoc.
     *
     * @param string $query
     * @param array $params
     * @return array|null
     * @throws Exception
     */
    public function fetchOne($query, $params = NULL)
    {
        $numPlaceholders = $this->countPreparedPlaceholders($query);
        if($numPlaceholders > 0 && $params === null){
            $params = $this->params;
        }

        $result = $this->query($query, $params);
        $mdKey = md5($query);
        unset($this->stmt_map[$mdKey]);
        $this->resetBinding();
        return $result->fetch_assoc();
    }

    /**
     * Close the database connection and reset bindings.
     *
     * @return bool
     */
    public function close()
    {
        $this->result_query = array();
        $this->resetBinding();
        return mysqli_close($this->connection);
    }

    /**
     * Fetch all rows for a query.
     *
     * @param string $query
     * @param array $params
     * @return array
     * @throws Exception
     */
    public function fetchAll($query, $params = NULL)
    {
        $rows = array();
        while ($row = $this->fetch($query, $params)) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Get the last auto-incremented ID.
     *
     * @return int|null
     * @throws Exception
     */
    public function lastInsertId()
    {
        $row = $this->fetchOne("select last_insert_id() as lastId");
        return $row ? array_pop($row) : null;
    }

    /**
     * Clear current binding state.
     *
     * @return void
     */
    protected function resetBinding()
    {
        $this->bind_type = '';
        $this->params = [];
    }

    /**
     * Return the active database connection instance.
     *
     * @return \mysqli|null
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Escape a single value safely for use in queries.
     *
     * @param mixed $value
     * @return string|int|float
     * @throws \Exception If DB connection is not available
     */
    public function realEscapeString($value)
    {
        if (!$this->connection) {
            throw new Exception("No active DB connection");
        }

        // 문자열만 escape, 숫자 등은 그대로 반환
        if (is_string($value)) {
            return mysqli_real_escape_string($this->connection, $value);
        }

        return $value;
    }
}