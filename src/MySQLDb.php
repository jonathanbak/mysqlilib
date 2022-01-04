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
    protected $bind_type = '';
    protected $params = array();

    public function connect($host, $user, $password, $dbName, $dbPort = '3306')
    {
        $connection = @mysqli_connect($host, $user, $password, $dbName, $dbPort);
        if (!$connection) {
            throw new Exception(mysqli_connect_error(), mysqli_connect_errno());
        }
        return $connection;
    }

    public function bind_param($type, $params = array())
    {
        $this->bind_type = $type;
        if(!empty($params)) $this->params = $params;
        return $this;
    }

    public function query($query, $params = array())
    {
        if(count($params)>0) {
            $this->params = $params;
        }
        if(count($this->params)>0){
            if(!empty($this->bind_type)) {
                $stmt = mysqli_prepare($this->connection,$query);
                $bindType = array();
                if(is_string($this->bind_type)) {
                    $bindType = str_split($this->bind_type);
                }else {
                    $bindType = $this->bind_type;
                }
                foreach($bindType as $k => $bindKey){
                    $stmt->bind_param($bindKey, $this->params[$k]);
                }
                $stmt->execute();
                $this->result = true;
            }else {
                $query = $this->parseCondition($query, $params);
                $this->result = mysqli_query($this->connection, $query);
            }
        }else {
            $this->result = mysqli_query($this->connection, $query);
        }

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
        if(count($params)>0) {
            $this->params = $params;
        }
        $queryOrigin = $query;
        if(count($this->params)>0){
            $query = $this->parseCondition($query, $this->params);
        }

        $mdKey = md5($query);
        if(!isset($this->result_query[$mdKey])){
            if(!empty($this->bind_type)) {
                $stmt = mysqli_prepare($this->connection,$queryOrigin);
                $bindType = array();
                if(is_string($this->bind_type)) {
                    $bindType = str_split($this->bind_type);
                }else {
                    $bindType = $this->bind_type;
                }
                foreach($bindType as $k => $bindKey){
                    $stmt->bind_param($bindKey, $this->params[$k]);
                }
                $stmt->execute();
                $this->result_query[$mdKey] = $stmt->get_result();
                $this->result_total_rows[$mdKey] = $this->result_query[$mdKey]->num_rows;
                $this->result_current_row[$mdKey] = 0;
                $this->bind_type = '';
            } else {
                $query = $this->parseCondition($query, $this->params);
                $this->result_query[$mdKey] = $this->query($query);
                $this->result_total_rows[$mdKey] = $this->result_query[$mdKey]->num_rows;
                $this->result_current_row[$mdKey] = 0;
            }
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
        $this->bind_type = '';
        return $result->fetch_assoc();
    }

    public function close()
    {
        $this->result_query = array();
        $this->params = array();
        $this->bind_type = '';

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