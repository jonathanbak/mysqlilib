<?php
/**
 * Abstract base class for database operations using mysqli.
 *
 * Provides utility methods for escaping, quoting, and generating query conditions.
 *
 * @package MySQLiLib
 * @author jonathanbak
 * @since 2017-02-01
 */

namespace MySQLiLib;

abstract class DbAbstract implements DbInterface
{
    /**
     * Database connection instance.
     *
     * Actual type is defined by concrete subclass (e.g., \mysqli).
     *
     * @var mixed|null
     */
    protected $connection = null;

    /** @var mixed Result of the latest query */
    protected $result = null;

    /** @var array Cached results for reuse in iteration */
    protected $result_query = [];

    /**
     * Escape a value to make it safe for use in SQL queries.
     *
     * This method must be implemented by child classes to handle DB-specific
     * escaping (e.g., using mysqli_real_escape_string).
     *
     * @param mixed $value The value to escape
     * @return mixed The escaped value (usually string), or original if escaping is unnecessary
     */
    abstract protected function realEscapeString($value);

    /**
     * Constructor: initializes database connection.
     *
     * @param string $host
     * @param string $user
     * @param string $password
     * @param string $dbName
     * @param int $dbPort
     */
    public function __construct( $host, $user, $password, $dbName, $dbPort = 3306 )
    {
        $this->connection = $this->connect( $host, $user, $password, $dbName, $dbPort );
    }

    /**
     * Escape an array of values using realEscapeString().
     *
     * @param array $params
     * @return array
     * @throws \Exception
     */
    public function arrayToRealEscape( $params = array() )
    {
        foreach($params as $k=> $value){
            $params[$k] = $this->realEscapeString($value);
        }
        return $params;
    }

    /**
     * Remove slashes from a string (legacy support for PHP < 7.4).
     *
     * @param string $value
     * @return string
     * @deprecated No longer needed in PHP 7.4+
     */
    public function unEscapeString( $value )
    {
        return stripslashes( $value );
    }

    /**
     * Quote all values in an array with single quotes, escaping each.
     *
     * @param array $arrayVal
     * @return array
     * @throws \Exception
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
     * Convert key-value array into SQL SET-like expressions.
     *
     * @param array $params
     * @return array
     * @throws \Exception
     */
    public function parseArrayToQuery($params = array())
    {
        $tmpVal = array();
        foreach ($params as $k => $val) {
            if (!preg_match('/^([0-9]+)$/', $k)) {
                $tmpVal[] = " `$k` = '" . $this->realEscapeString($val) . "'";
            }
        }
        return $tmpVal;
    }

    /**
     * Replace placeholders (:param, ?, ??) in query patterns.
     *
     * - `:key` → quoted and escaped
     * - `?` or `??` → positional values
     *
     * @param string $pattern
     * @param array|string $searchValue
     * @return string
     * @throws \Exception
     */
    public function parseCondition( $pattern, $searchValue )
    {
        // Named bind :param
        if(preg_match_all('/:([a-zA-Z0-9_-]+)/i',$pattern,$tmpMatches)){
            $findKeys = array_unique($tmpMatches[1]);
            foreach($findKeys as $column){
                if(isset($searchValue[$column])) $pattern = str_replace(":".$column,"'".$searchValue[$column]."'",$pattern);
                else $pattern = str_replace(":".$column,"''",$pattern);
            }
        }
        // Optional: ::param 제거된 경우 처리 제외됨 (성능 향상 목적)
//        if(preg_match_all('/::([a-zA-Z0-9_-]+)/i',$pattern,$tmpMatches)){
//            $findKeys = array_unique($tmpMatches[1]);
//            foreach($findKeys as $column){
//                if(isset($searchValue[$column])) $pattern = str_replace("::".$column,$searchValue[$column],$pattern);
//                else $pattern = str_replace("::".$column,"''",$pattern);
//            }
//        }
        // Escape percentage & positional placeholders
        $pattern = str_replace("%","%%",$pattern);
        $pattern = str_replace("??","%s",$pattern);
        $conditions = str_replace("?","'%s'",$pattern);

        if(is_array($searchValue)) {
            $searchValue = $this->arrayToRealEscape($searchValue);
            if (empty($searchValue)) {
                $conditions = preg_replace("/'%s'/", "''", $conditions);
            } else {
                $conditions = vsprintf($conditions, $searchValue);
            }
        }else{
            $searchValue = $this->realEscapeString($searchValue);
            $conditions = sprintf($conditions, $searchValue);
        }

        return $conditions;
    }

    /**
     * Convert named parameters in a query to positional placeholders.
     *
     * @param string $query
     * @param array $params
     * @return array [query, orderedParams]
     */
    protected function parseNamedParamsToPositional($query, $params)
    {
        $ordered = [];
        $pattern = '/:([a-zA-Z_][a-zA-Z0-9_]*)/';

        $query = preg_replace_callback($pattern, function($matches) use ($params, &$ordered) {
            $key = $matches[1];
            if (!array_key_exists($key, $params)) {
                throw new \Exception("Missing parameter :$key");
            }
            $ordered[] = $params[$key];
            return '?';
        }, $query);

        return [$query, $ordered];
    }

    /**
     * Determine the bind type string from parameter values.
     *
     * @param array $params
     * @return string
     */
    protected function getBindTypes(array $params)
    {
        $types = '';
        foreach ($params as $val) {
            if (is_int($val)) $types .= 'i';
            elseif (is_float($val)) $types .= 'd';
            elseif (is_string($val)) $types .= 's';
            else $types .= 'b'; // default to blob
        }
        return $types;
    }
    
}