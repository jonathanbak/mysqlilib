<?php
namespace MySQLiLib\Test;

use MySQLiLib\DbAbstract;
use PHPUnit\Framework\TestCase;

/**
 * DummyDb 클래스는 DbAbstract를 확장한 테스트용 더미 구현체입니다.
 * 실제 DB 연결 없이 유틸 메서드의 동작을 테스트할 수 있도록 합니다.
 */
class DummyDb extends DbAbstract
{
    /**
     * 더미 connect 메서드
     *
     * @param string $host
     * @param string $user
     * @param string $password
     * @param string $dbName
     * @param int $dbPort
     * @return bool Always true (no actual connection)
     */
    public function connect($host, $user, $password, $dbName, $dbPort = 3306)
    {
        return true;
    }

    /**
     * 더미 realEscapeString 메서드 (addslashes 대체)
     *
     * @param mixed $value
     * @return mixed
     */
    protected function realEscapeString($value)
    {
        return is_string($value) ? addslashes($value) : $value;
    }

    /**
     * Dummy 쿼리 실행 (구현 필요 없음)
     *
     * @param string $query
     * @param array $params
     * @return false
     */
    public function query($query, $params = array())
    {
        return false;
    }

    /**
     * Dummy fetch 메서드
     *
     * @param string $query
     * @param array $params
     * @return null
     */
    public function fetch($query, $params = array())
    {
        return null;
    }

    /**
     * Dummy close 메서드
     *
     * @return void
     */
    public function close()
    {
        // 아무 동작 없음
    }
}

class DbAbstractTest extends TestCase
{
    public function testArrayToRealEscape()
    {
        $db = new DummyDb('', '', '', '');
        $result = $db->arrayToRealEscape(["O'Reilly"]);
        $this->assertEquals(["O\'Reilly"], $result);
    }

    public function testIntArrayQuote()
    {
        $db = new DummyDb('', '', '', '');
        $quoted = $db->intArrayQuote(['foo', 'bar']);
        $this->assertEquals(["'foo'", "'bar'"], $quoted);
    }

    public function testParseArrayToQuery()
    {
        $db = new DummyDb('', '', '', '');
        $result = $db->parseArrayToQuery(['col1' => 'abc']);
        $this->assertEquals([" `col1` = 'abc'"], $result);
    }

    public function testParseConditionNamed()
    {
        $db = new DummyDb('', '', '', '');
        $result = $db->parseCondition("SELECT * FROM test WHERE name = :name", ['name' => 'kim']);
        $this->assertStringContainsString("name = 'kim'", $result);
    }

    public function testParseConditionPositional()
    {
        $db = new DummyDb('', '', '', '');
        $result = $db->parseCondition("SELECT * FROM test WHERE id = ?", [123]);
        $this->assertStringContainsString("id = '123'", $result);
    }
}