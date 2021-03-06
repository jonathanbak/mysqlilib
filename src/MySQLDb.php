<?php
/**
 * MySQL
 * User: jonathanbak
 * Date: 2017. 2. 1.
 */

namespace MySQLiLib;


class MySQLDb extends DbAbstract
{
    private $result_total_rows = array();
    private $result_current_row = array();

    public function connect($host, $user, $password, $dbName, $dbPort = '3306')
    {
        $connection = @mysqli_connect($host, $user, $password, $dbName, $dbPort);
        if (!$connection) {
            throw new Exception(mysqli_connect_error(), mysqli_connect_errno());
        }
        return $connection;
    }

    public function query($query, $params = array())
    {
        if(count($params)>0) $query = $this->parseCondition($query, $params);
        $this->result = mysqli_query($this->connection, $query);

        if(mysqli_errno($this->connection)){
            throw new Exception(mysqli_error($this->connection), mysqli_errno($this->connection));
        }

        return $this->result;
    }

    public function error()
    {
        return mysqli_error($this->connection);
    }

    public function errorNo()
    {
        return mysqli_errno($this->connection);
    }

    /**
     * 한행 가져오기
     * @param $query
     * @param array $params
     * @return bool|mixed|null
     * @throws Exception
     */
    public function fetch($query, $params = array())
    {
        if(count($params)>0) $query = $this->parseCondition($query, $params);
        $mdKey = md5($query);
        if(!isset($this->result_query[$mdKey])){
            $this->result_query[$mdKey] = $this->query($query);
            $this->result_total_rows[$mdKey] = $this->result_query[$mdKey]->num_rows;
            $this->result_current_row[$mdKey] = 0;
        }else{
            $this->result_current_row[$mdKey]++;
            if($this->result_total_rows[$mdKey] <= $this->result_current_row[$mdKey]) {
                unset($this->result_query[$mdKey]);
                unset($this->result_current_row[$mdKey]);
                unset($this->result_total_rows[$mdKey]);
            }
        }

        if( isset($this->result_query[$mdKey]) && is_a($this->result_query[$mdKey], 'mysqli_result') ) return $this->result_query[$mdKey]->fetch_assoc() ;

        return isset($this->result_query[$mdKey])? isset($this->result_query[$mdKey]) : null;
    }

    public function fetchOne($query, $params = array())
    {
        if(count($params)>0) $query = $this->parseCondition($query, $params);
        $result = $this->query($query);
        return $result->fetch_assoc();
    }

    public function close()
    {
        $this->result_query = array();

        return mysqli_close($this->connection);
    }

    /**
     * 여러행 가져오기
     * @param $query
     * @param array $params
     * @return array
     * @throws Exception
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
     * @return mixed|null
     * @throws Exception
     */
    public function lastInsertId()
    {
        $row = $this->fetchOne("select last_insert_id() as lastId");
        return $row ? array_pop($row) : null;
    }
}