<?php
/**
 * MySQL
 * User: jonathanbak
 * Date: 2017. 2. 1.
 */

namespace MySQLiLib;


class MySQLDb extends DbAbstract
{
    private $result_total_rows = 0;
    private $result_current_row = 0;

    public function connect($host, $user, $password, $dbName)
    {
        $connection = @mysqli_connect($host, $user, $password, $dbName);

        if (!$connection) {
            throw new Exception(mysqli_connect_error(), mysqli_connect_errno());
        }
        return $connection;
    }

    public function query($query, $params = array())
    {
        // TODO: Implement query() method.
        if(count($params)>0) $query = $this->parseCondition($query, $params);
        $this->result = mysqli_query($this->connection, $query);

        return $this->result;
    }

    /**
     * 한행 가져오기
     * @param $query
     * @param array $params
     * @return null
     */
    public function fetch($query, $params = array())
    {
        // TODO: Implement fetch() method.
        if(count($params)>0) $query = $this->parseCondition($query, $params);

        if($this->result_query != md5($query)){
            $this->query($query);
            $this->result_query = md5($query);
            $this->result_total_rows = $this->result->num_rows;
        }else{
            $this->result_current_row++;
            if($this->result_total_rows <= $this->result_current_row) {
                $this->result_query = null;
                $this->result_current_row = 0;
                $this->result_total_rows = 0;
            }
        }

        if( is_a($this->result, 'mysqli_result') ) return $this->result->fetch_assoc() ;

        return $this->result;
    }

    public function close()
    {
        $this->result_query = null;
        // TODO: Implement close() method.
        return mysqli_close($this->connection);
    }

    /**
     * 여러행 가져오기
     * @param $query
     * @param array $params
     * @return array
     */
    public function fetchAll($query, $params = array())
    {
        $rows = array();
        while($row = $this->fetch($query, $params)){
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * 마지막 입력 번호 가져오기 (auto increment column)
     * @return bool|int
     */
    public function lastInsertId()
    {
        $row = $this->fetch("select last_insert_id() as lastId");
        return $row ? array_pop($row) : null;
    }
}