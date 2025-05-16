<?php
/**
 * Interface DbInterface
 *
 * Defines the contract for database interaction classes.
 *
 * @package MySQLiLib
 * @author jonathanbak
 * @since 2017-02-01
 */

namespace MySQLiLib;

interface DbInterface
{
    /**
     * Connect to the database server.
     *
     * This method should return the native database connection resource
     * used by the implementing class. For example:
     * - `MySQLDb` returns `\mysqli`
     * - `FakeDb` may return `null` or mock object
     *
     * @param string $host     Database host
     * @param string $user     Database username
     * @param string $password Database password
     * @param string $dbName   Database name
     * @param int    $dbPort   Database port (default: 3306)
     * @return mixed Connection object (e.g., \mysqli in MySQLDb)
     */
    public function connect( $host, $user, $password, $dbName, $dbPort = 3306 );

    /**
     * Close the database connection.
     *
     * @return void
     */
    public function close();

    /**
     * Execute a SQL query.
     *
     * @param string $query   SQL query string
     * @param array $params   Optional bind parameters
     * @return mixed          Query result, may be a mysqli_result or boolean depending on query
     */
    public function query( $query, $params = array() );

    /**
     * Fetch a single row from a query result.
     *
     * @param string $query   SQL query string
     * @param array $params   Optional bind parameters
     * @return array|null     Associative array of a row or null if none
     */
    public function fetch( $query, $params = array());

}