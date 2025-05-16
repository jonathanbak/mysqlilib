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
    public function realEscapeString($value)
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

    // 테스트용으로 보호된 메서드 노출
    public function exposedParseNamedParamsToPositional($query, $params)
    {
        return $this->parseNamedParamsToPositional($query, $params);
    }

    public function exposedGetBindTypes(array $params)
    {
        return $this->getBindTypes($params);
    }
}

/**
 * Class DbAbstractTest
 * Tests utility functions in DbAbstract
 */
class DbAbstractTest extends TestCase
{
    private $db;

    protected function setUp(): void
    {
        $this->db = new DummyDb('','','','');
    }

    public function testRealEscapeString()
    {
        $escaped = $this->db->realEscapeString("O'Reilly");
        $this->assertEquals("O\'Reilly", $escaped);
    }

    public function testUnEscapeString()
    {
        $unescaped = $this->db->unEscapeString("O\'Reilly");
        $this->assertEquals("O'Reilly", $unescaped);
    }

    public function testArrayToRealEscape()
    {
        $escaped = $this->db->arrayToRealEscape(['a"b', "x'y"]);
        $this->assertIsArray($escaped);
        $this->assertEquals(2, count($escaped));
    }

    public function testIntArrayQuote()
    {
        $quoted = $this->db->intArrayQuote(['val1', 'val2']);
        $this->assertEquals(["'val1'", "'val2'"], $quoted);
    }

    public function testParseArrayToQuerySkipsNumericKeys()
    {
        $parsed = $this->db->parseArrayToQuery(['name' => 'aaa', 0 => 'ignored']);
        $this->assertIsArray($parsed);
        $this->assertStringContainsString("`name` =", implode(',', $parsed));
    }

    public function testParseConditionQuestionMark()
    {
        $result = $this->db->parseCondition("email LIKE ?", ['test%']);
        $this->assertStringContainsString("email LIKE 'test%'", $result);
    }

    public function testParseConditionDoubleQuestionMark()
    {
        $result = $this->db->parseCondition("username = '??'", ['user']);
        $this->assertStringContainsString("username = 'user'", $result);
    }

    public function testParseConditionNamedParam()
    {
        $result = $this->db->parseCondition("email = :email", ['email' => 'x@test.com']);
        $this->assertEquals("email = 'x@test.com'", $result);
    }

    public function testParseConditionWithEmptyArray()
    {
        $result = $this->db->parseCondition("id > ?", []);
        $this->assertStringContainsString("id > ''", $result);
    }

    public function testParseNamedParamsToPositional()
    {
        $sql = "SELECT * FROM table WHERE id = :id AND status = :status";
        $params = ['id' => 10, 'status' => 'active'];
        list($parsedQuery, $ordered) = $this->db->exposedParseNamedParamsToPositional($sql, $params);

        $this->assertEquals("SELECT * FROM table WHERE id = ? AND status = ?", $parsedQuery);
        $this->assertEquals([10, 'active'], $ordered);
    }

    public function testGetBindTypes()
    {
        $params = [1, 2.3, 'hello', null];
        $types = $this->db->exposedGetBindTypes($params);

        // null은 blob('b')로 처리됨
        $this->assertEquals('idsb', $types);
    }
}