<?php
namespace MySQLiLib\Test;

use MySQLiLib\Exception;
use MySQLiLib\MySQLDb;

class MySQLTest extends \PHPUnit_Framework_TestCase
{
    protected static $MySQL = null;

    /**
     * @expectedException Exception
     */
    public function testExceptionConnect()
    {
        list($host, $user, $password, $dbName, $dbPort) = array($GLOBALS['DB_HOST'],$GLOBALS['DB_USER'],$GLOBALS['DB_PASSWD'],'1111',$GLOBALS['DB_NAME'], null);
        $connection = new MySQLDb($host, $user, $password, $dbName, $dbPort);
    }

    public function testConnect()
    {
        try {
            list($host, $user, $password, $dbName, $dbPort) = array($GLOBALS['DB_HOST'],$GLOBALS['DB_USER'],$GLOBALS['DB_PASSWD'],$GLOBALS['DB_NAME'], isset($GLOBALS['DB_PORT'])? $GLOBALS['DB_PORT'] : '3306');
            $MySQL = new MySQLDb($host, $user, $password, $dbName, $dbPort);

            // Assert
            $this->assertAttributeNotEquals(
                null,
                'connection', $MySQL
            );

            self::$MySQL = $MySQL;
            return $MySQL;
        }catch(Exception $e){
            echo $e->getMessage();
            return null;
        }
    }

    /**
     * @depends testConnect
     */
    public function testCreateTable(MySQLDb $MySQL)
    {
        $query = "CREATE TABLE `tmp_table` (
          `t_id` int(11) NOT NULL DEFAULT '0',
          `t_datetime` datetime DEFAULT NULL,
          PRIMARY KEY (`t_id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8; ";

        $result = $MySQL->query($query);

        $this->assertEquals(true, $result);

        if(!$result){
            $this->testDropTable($MySQL);
        }

        return $MySQL;
    }

    /**
     * @depends testCreateTable
     */
    public function testQueryInsert(MySQLDb $MySQL)
    {
        $successCnt = 0;
        for($i=1; $i<=10; $i++){
            $query = "INSERT INTO `tmp_table` SET `t_id`=?, `t_datetime`= now();";
            $result = $MySQL->query($query, array($i));
            if($result) $successCnt++;
        }

        $this->assertEquals(10, $successCnt);
        return $MySQL;
    }

    /**
     * @depends testQueryInsert
     */
    public function testFetchWhile(MySQLDb $MySQL)
    {
        $query = "SELECT * FROM `tmp_table` WHERE t_id > ? LIMIT 2";
        $list = array();
        while($row = $MySQL->fetch($query, array(4))){
//            var_dump($row);
            $list[] = $row;
        }

        $this->assertEquals(2, count($list));
        return $MySQL;
    }

    /**
     * 다중 반복문에서 fetch 를 사용하더라도 정상적으로 다음 쿼리를 가져옴
     * @depends testQueryInsert
     */
    public function testFetchWhile2(MySQLDb $MySQL)
    {
        $query = "SELECT * FROM `tmp_table` WHERE t_id > ? LIMIT 2";
        $list = array();
        while($row = $MySQL->fetch($query, array(5))){
//            var_dump($row);
            $list_1 = $row; $list_2 = array();
            while($row = $MySQL->fetch($query, array(1))){
//            var_dump($row);
                $list_2[] = $row;
            }
            $list[] = array($list_1, $list_2);
        }

        $this->assertEquals(2, count($list));
        return $MySQL;
    }

    /**
     * @depends testQueryInsert
     */
    public function testFetchForeach(MySQLDb $MySQL)
    {
        $query = "SELECT * FROM `tmp_table` WHERE t_id > ? LIMIT 2";
        $list = array();
        $row = $MySQL->fetch($query, array(5));
        $list[] = $row;
        $row = $MySQL->fetch($query, array(5));
        $list[] = $row;

        $this->assertEquals(2, count($list));
        return $MySQL;
    }

    /**
     * @depends testFetchForeach
     */
    public function testDropTable(MySQLDb $MySQL)
    {
        $query = "DROP TABLE `tmp_table`; ";
        $result = $MySQL->query($query);

        $this->assertEquals(true, $result);

        return $MySQL;
    }

    /**
     * @depends testDropTable
     * @expectedException Exception
     */
    public function testQueryError(MySQLDb $MySQL)
    {
        $query = "SELECT * FROM `tmp_table`; ";
        $result = $MySQL->query($query);

    }

    /**
     * @depends testDropTable
     */
    public function testCatchQueryError(MySQLDb $MySQL)
    {
        try{
            $query = "SELECT * FROM `tmp_table`; ";
            $result = $MySQL->query($query);
        }catch (Exception $e){
            $this->assertEquals($MySQL->errorNo(), $e->getCode());
        }

        return $MySQL;
    }


    public static function tearDownAfterClass()
    {

        if(self::$MySQL !== null) self::$MySQL->close();
    }

}