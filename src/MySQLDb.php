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
        $connection = @mysqli_connect($host, $user, $password, $dbName, $dbPort);
        if (!$connection) {
            throw new Exception(mysqli_connect_error(), mysqli_connect_errno());
        }
        return $connection;
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

    /**
     * Execute a query with optional parameters.
     * Supports caching of prepared statements.
     *
     * @param string $query
     * @param array|null $params
     * @return mixed
     * @throws Exception
     */
    public function query($query, $params = NULL)
    {
        try {
            $mdKey = md5($query);
            if ($params !== NULL) {
                //파라미터를 직접 입력한 경우 해시값 변경
                $this->params = is_array($params) ? $params : array($params);
                $mdKey = md5(implode('|', [$query, implode('', $this->params)]));
            } else {
                // 문자열 상수 제거 후 바인딩 여부 확인
                $cleaned = preg_replace("/'([^'\\\\]*(?:\\\\.[^'\\\\]*)*)'/", "''", $query);
                $hasBinding = preg_match('/\\?|:\\w+/', $cleaned);

                if (!$hasBinding && !isset($this->stmt_map[$mdKey])) {
                    $this->resetBinding();
                }
            }

            // 초기 실행
            if (!isset($this->stmt_map[$mdKey])) {
                //초기 실행시 bind_type 있을때
                if (!empty($this->bind_type) && !empty($this->params)) {
                    $this->stmt_map[$mdKey] = mysqli_prepare($this->connection, $query);
                    $this->stmt_map[$mdKey]->bind_param($this->bind_type, ...$this->params);
                    $this->stmt_map[$mdKey]->execute();

                    // bind_map 저장
                    $this->bind_map[$mdKey] = [
                        'type' => $this->bind_type,
                        'params' => $this->params
                    ];
                    $this->resetBinding();
                } else {
                    //BIND TYPE 이 없을떄
                    if (!empty($this->params)) {
                        if (preg_match_all('/:([a-zA-Z0-9_-]+)/i', $query, $tmpMatches)) {
                            //:param 형태
                            [$parsedQuery, $orderedParams] = $this->parseNamedParamsToPositional($query, $this->params);
                            $this->stmt_map[$mdKey] = mysqli_prepare($this->connection, $parsedQuery);
                            $types = $this->getBindTypes($orderedParams);
                            $this->stmt_map[$mdKey]->bind_param($types, ...$orderedParams);
                        } else {
                            $this->stmt_map[$mdKey] = mysqli_prepare($this->connection, $query);
                            $types = $this->getBindTypes($this->params);
                            $this->stmt_map[$mdKey]->bind_param($types, ...$this->params);
                        }
                        $this->stmt_map[$mdKey]->execute();
                    } else {
                        //파라미터가 없다면 1회성
                        $result = mysqli_query($this->connection, $query);
                        if (mysqli_errno($this->connection)) {
                            throw new Exception(mysqli_error($this->connection), mysqli_errno($this->connection));
                        }
                        return $result;
                    }
                }
            } else {
                //실행한 적이 있다면.. 그래도 실행해야지?
                if (!empty($this->stmt_map[$mdKey]) && !empty($this->params)) {
                    $this->stmt_map[$mdKey]->bind_param($this->bind_map[$mdKey]['type'], ...$this->params);
                }
                $this->stmt_map[$mdKey]->execute();
            }

            if (mysqli_errno($this->connection)) {
                throw new Exception(mysqli_error($this->connection), mysqli_errno($this->connection));
            }

            if ($this->stmt_map[$mdKey]->field_count > 0) {
                return $this->stmt_map[$mdKey]->get_result(); // 결과셋 반환
            } else {
                $this->resetBinding();
                return true; // INSERT, UPDATE, DELETE 등은 성공 여부만
            }
        } catch (\mysqli_sql_exception $e) {
            // 모든 MySQL 예외는 MySQLiLib\Exception으로 감싸서 던짐
            throw new Exception($e->getMessage(), $e->getCode(), $e);
        } catch (\Exception $e) {
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
        try{
            $mdKey = md5($query);
            if ($params !== NULL) {
                //파라미터를 직접 입력한 경우 해시값 변경
                $this->params = is_array($params) ? $params : array($params);
                $mdKey = md5(implode('|', [$query, implode('', $params)]));
            } else {
                // 문자열 상수 제거 후 바인딩 여부 확인
                $cleaned = preg_replace("/'([^'\\\\]*(?:\\\\.[^'\\\\]*)*)'/", "''", $query);
                $hasBinding = preg_match('/\\?|:\\w+/', $cleaned);

                if (!$hasBinding && !isset($this->result_query[$mdKey])) {
                    $this->resetBinding();
                }
            }

            // 초기 실행
            if (!isset($this->result_query[$mdKey])) {
                //초기 실행시 bind_type 있을때
                if (!empty($this->bind_type) && !empty($this->params)) {
                    $this->result_query[$mdKey] = $this->query($query, $this->params);
                    $this->result_total_rows[$mdKey] = $this->result_query[$mdKey]->num_rows ?? 0;
                    $this->result_current_row[$mdKey] = 0;
                } else {
                    //BIND TYPE 이 없을떄
                    $this->result_query[$mdKey] = $this->query($query, $this->params);
                    $this->result_total_rows[$mdKey] = $this->result_query[$mdKey]->num_rows ?? 0;
                    $this->result_current_row[$mdKey] = 0;
                }
            } else {
                $this->result_current_row[$mdKey]++;
            }

            // fetch row
            if (isset($this->result_query[$mdKey]) && $this->result_query[$mdKey] instanceof \mysqli_result) {
                $row = $this->result_query[$mdKey]->fetch_assoc();
                // 더 이상 데이터 없으면 캐시 제거
                if (!$row) {
                    unset($this->result_query[$mdKey]);
                    unset($this->result_total_rows[$mdKey]);
                    unset($this->result_current_row[$mdKey]);
                    unset($this->bind_map[$mdKey]);
                    unset($this->stmt_map[$mdKey]);
                    $this->resetBinding();
                }

                return $row;
            }

            unset($this->result_query[$mdKey]);
            unset($this->result_total_rows[$mdKey]);
            unset($this->result_current_row[$mdKey]);
            unset($this->bind_map[$mdKey]);
            unset($this->stmt_map[$mdKey]);
            $this->resetBinding();

            return null;
        } catch (\mysqli_sql_exception $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e);
        } catch (\Exception $e) {
            throw new Exception("Unexpected error in fetch(): " . $e->getMessage(), $e->getCode(), $e);
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
        $result = $this->query($query, $params);

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