<?php
/**
 * User: jonathanbak
 * Date: 2017. 2. 1.
 */

namespace MySQLiLib;


abstract class DbAbstract implements DbInterface
{
    protected $connection = null;	//DB connect
    protected $result = null;	//query 결과
    protected $result_query = array(); //query 결과 의 쿼리

    /**
     * DbAbstract constructor.
     * @param $host
     * @param $user
     * @param $password
     * @param $dbName
     * @param int $dbPort
     */
    public function __construct( $host, $user, $password, $dbName, $dbPort = 3306 )
    {
        $this->connection = $this->connect( $host, $user, $password, $dbName, $dbPort );
    }


    /**
     * SQL Injection 방어 mysql_real_escape_string 실행
     * @param array $params
     * @return array
     */
    public function arrayToRealEscape( $params = array() )
    {
        foreach($params as $k=> $value){
            $params[$k] = $this->realEscapeString($value);
        }
        return $params;
    }

    /**
     * get_magic_quotes_gpc 체크하여 addslashes
     * @param $value
     * @return string
     */
    public function realEscapeString( $value )
    {
        return function_exists("get_magic_quotes_gpc") && get_magic_quotes_gpc()? $value : addslashes( $value );
    }

    /**
     * get_magic_quotes_gpc 체크하여 stripslashes
     * @param $value
     * @return string
     */
    public function unEscapeString( $value )
    {
        return function_exists("get_magic_quotes_gpc") && get_magic_quotes_gpc()? $value : stripslashes( $value );
    }

    /**
     * 배열의 각 값을 홑따옴표로 묶어서 돌려준다
     * @param array $arrayVal
     * @return array
     */
    public function intArrayQuote( $arrayVal = array() )
    {
        $tmpVal = array();
        foreach($arrayVal as $val){
            $tmpVal[] = "'".$this->realEscapeString($val)."'";
        }

        return $tmpVal;
    }

    /**
     * 키:값 배열을 쿼리문에 넣기좋게 만들어준다
     * @param array $params
     * @return array
     */
    public function parseArrayToQuery( $params = array() )
    {
        $tmpVal = array();
        foreach($params as $k => $val){
            if(preg_match('/^([0-9]+)$/i',$k,$tmpMatch)==false) $tmpVal[] = " `$k` = "." '".$this->realEscapeString($val)."'";
        }
        return $tmpVal;
    }

    /**
     * 쿼리에 ? 로 파라미터 넣기
     * @param $pattern
     * @param $searchValue
     * @return mixed|string
     */
    public function parseCondition( $pattern, $searchValue )
    {
        if(preg_match_all('/:([a-zA-Z0-9_-]+)/i',$pattern,$tmpMatches)){
            $findKeys = array_unique($tmpMatches[1]);
            foreach($findKeys as $column){
                if(isset($searchValue[$column])) $pattern = str_replace(":".$column,"'".$searchValue[$column]."'",$pattern);
                else $pattern = str_replace(":".$column,"''",$pattern);
            }
        }
        if(preg_match_all('/::([a-zA-Z0-9_-]+)/i',$pattern,$tmpMatches)){
            $findKeys = array_unique($tmpMatches[1]);
            foreach($findKeys as $column){
                if(isset($searchValue[$column])) $pattern = str_replace("::".$column,$searchValue[$column],$pattern);
                else $pattern = str_replace("::".$column,"''",$pattern);
            }
        }
        $pattern = str_replace("%","%%",$pattern);
        $pattern = str_replace("??","%s",$pattern);
        $conditions = str_replace("?","'%s'",$pattern);

        if(is_array($searchValue)) {
            $searchValue = $this->arrayToRealEscape($searchValue);
            $conditions = vsprintf($conditions, $searchValue);
        }else{
            $searchValue = $this->realEscapeString($searchValue);
            $conditions = sprintf($conditions, $searchValue);
        }

        return $conditions;
    }
    
}