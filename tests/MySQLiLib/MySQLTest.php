<?php
namespace MySQLiLibTests;

use MySQLiLib\Exception;
use MySQLiLib\MySQLDb;

class MySQLTest extends \PHPUnit_Framework_TestCase
{
    const DB_HOST = '127.0.0.1';
    const DB_USER = 'db_user';
    const DB_PASS = 'db_password';
    const DB_NAME = 'db_name';

    protected static $MySQL = null;

    public static function setUpBeforeClass()
    {
        list($host, $user, $password, $dbName) = array(self::DB_HOST,self::DB_USER,self::DB_PASS,self::DB_NAME);
        self::$MySQL = new MySQLDb($host, $user, $password, $dbName);

        $query = "CREATE TABLE `tmp_table` (
              `t_id` int(11) NOT NULL DEFAULT '0',
              `t_datetime` datetime DEFAULT NULL,
              PRIMARY KEY (`t_id`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8; ";

        $result = self::$MySQL->query($query);

    }

    public static function tearDownAfterClass()
    {
        $query = "DROP TABLE `tmp_table`; ";
        $result = self::$MySQL->query($query);

        self::$MySQL->close();
    }

    public function testConnect()
    {

        list($host, $user, $password, $dbName) = array(self::DB_HOST,self::DB_USER,self::DB_PASS,self::DB_NAME);
        $MySQL = new MySQLDb($host, $user, $password, $dbName);

        // Assert
        $this->assertAttributeNotEquals(
            null,
            'connection', $MySQL
        );
    }

    /**
     * @expectedException Exception
     */
    public function testExceptionConnect()
    {
        list($host, $user, $password, $dbName) = array(self::DB_HOST,self::DB_USER,self::DB_PASS.'123',self::DB_NAME);
        $connection = new MySQLDb($host, $user, $password, $dbName);
    }

    public function testQueryInsert()
    {
        $successCnt = 0;
        for($i=1; $i<=10; $i++){
            $query = "INSERT INTO `tmp_table` SET `t_id`=?, `t_datetime`= now();";
            $result = self::$MySQL->query($query, array($i));
            if($result) $successCnt++;
        }

        $this->assertEquals(10, $successCnt);
    }

    public function testFetchWhile()
    {
        $query = "SELECT * FROM `tmp_table` WHERE t_id > ? LIMIT 2";
        $list = array();
        while($row = self::$MySQL->fetch($query, array(4))){
//            var_dump($row);
            $list[] = $row;
        }

        $this->assertEquals(2, count($list));
    }

    public function testFetchWhile2()
    {
        $query = "SELECT * FROM `tmp_table` WHERE t_id > ? LIMIT 2";
        $list = array();
        while($row = self::$MySQL->fetch($query, array(5))){
//            var_dump($row);
            $list[] = $row;
        }

        $this->assertEquals(2, count($list));
    }

    public function testFetchForeach()
    {
        $query = "SELECT * FROM `tmp_table` WHERE t_id > ? LIMIT 2";
        $list = array();
        $row = self::$MySQL->fetch($query, array(5));
        $list[] = $row;
        $row = self::$MySQL->fetch($query, array(5));
        $list[] = $row;

        $this->assertEquals(2, count($list));
    }


}