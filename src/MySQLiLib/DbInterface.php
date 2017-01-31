<?php
/**
 * Db Interface
 * User: jonathanbak
 * Date: 2017. 2. 1.
 */

namespace MySQLiLib;

interface DbInterface
{
    /**
     * 디비서버 연결
     * @param $host
     * @param $user
     * @param $password
     * @param $dbName
     * @return mixed
     */
    public function connect( $host, $user, $password, $dbName );

    /**
     * 연결해재
     */
    public function close();

    /**
     * 쿼리 실행
     * @param $query
     * @param array $params
     * @return mixed
     */
    public function query( $query, $params = array() );

    /**
     * 한 행 가져오기
     * @param $query
     * @return mixed
     */
    public function fetch( $query );

}