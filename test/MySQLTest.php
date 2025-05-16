<?php
namespace MySQLiLib\Test;

use MySQLiLib\Exception;
use MySQLiLib\MySQLDb;
use PHPUnit\Framework\TestCase;

class MySQLDbFakeNoConnection extends \MySQLiLib\MySQLDb
{
    public function __construct()
    {
        // 부모 생성자 호출 안 함 → $this->connection === null
    }

    // 실 DB 연결은 필요 없으므로 connect 오버라이드
    public function connect($host, $user, $password, $dbName, $dbPort = 3306)
    {
        return null;
    }
}

/**
 * Unit tests for the MySQLDb class which implements DbInterface.
 *
 * This test suite covers:
 * - Database connection and error handling
 * - Table creation and destruction
 * - INSERT, SELECT, UPDATE, DELETE operations
 * - Named and positional parameter binding
 * - Iterator-safe fetch() usage in loops
 * - Utility functions (escape, parse, lastInsertId)
 * - Error throwing and exception propagation
 *
 * @covers \MySQLiLib\MySQLDb
 */
class MySQLTest extends TestCase
{
    protected static $MySQL = null;

    /**
     * Test that a connection error throws an Exception.
     */
    public function testExceptionConnect()
    {
        $this->expectException(Exception::class);
        $MySQL = new MySQLDb('localhost', 'invalid_user', 'badpass', 'notexist', 3306);
    }

    /**
     * Test successful database connection.
     *
     * @return MySQLDb
     */
    public function testConnect()
    {
        $MySQL = new MySQLDb(
            $GLOBALS['DB_HOST'],
            $GLOBALS['DB_USER'],
            $GLOBALS['DB_PASSWD'],
            $GLOBALS['DB_NAME'],
            $GLOBALS['DB_PORT']
        );
        $this->assertNotNull($MySQL->getConnection());
        self::$MySQL = $MySQL;
        return $MySQL;
    }

    /**
     * Test escaping and parsing utilities from DbAbstract.
     *
     * @depends testConnect
     */
    public function testDbAbstractFullCoverage(MySQLDb $MySQL)
    {
        // realEscapeString / unEscapeString
        $escaped = $MySQL->realEscapeString("O'Reilly");
        $this->assertEquals("O\'Reilly", $escaped);

        $unescaped = $MySQL->unEscapeString("O\'Reilly");
        $this->assertEquals("O'Reilly", $unescaped);

        // arrayToRealEscape
        $result = $MySQL->arrayToRealEscape(['a"b', "x'y"]);
        $this->assertIsArray($result);

        // intArrayQuote
        $quoted = $MySQL->intArrayQuote(['val1', 'val2']);
        $this->assertEquals(["'val1'", "'val2'"], $quoted);

        // parseArrayToQuery with assoc and numeric keys
        $parsed = $MySQL->parseArrayToQuery(['name' => 'aaa', 0 => 'ignored']);
        $this->assertIsArray($parsed);
        $this->assertStringContainsString('`name` =', implode(',', $parsed));

        $c2 = $MySQL->parseCondition("email LIKE ?", ['test%']);
        $this->assertStringContainsString("email LIKE 'test%'", $c2);

        $c3 = $MySQL->parseCondition("username = '??'", ['user']);
        $this->assertStringContainsString("username = 'user'", $c3);

        return $MySQL;
    }

    /**
     * Test utility methods of MySQLDb (error, bind_param).
     *
     * @depends testDbAbstractFullCoverage
     */
    public function testMySQLDbUtilityMethods(MySQLDb $MySQL)
    {
        $this->assertIsString($MySQL->error());
        $this->assertIsInt($MySQL->errorNo());

        $MySQL->bind_param('i', [1]);
        $this->assertTrue(true); // Just making sure no error thrown

//        $this->assertTrue($MySQL->close());
    }

    /**
     * Create the test table.
     *
     * @depends testConnect
     */
    public function testCreateTable(MySQLDb $MySQL)
    {
        $MySQL->query("DROP TABLE IF EXISTS tmp_table");
        $query = "CREATE TABLE tmp_table (
            t_id INT PRIMARY KEY,
            t_datetime DATETIME,
            name VARCHAR(50),
            status VARCHAR(20),
            email VARCHAR(100)
        ) ENGINE=InnoDB";
        $this->assertTrue($MySQL->query($query));
        return $MySQL;
    }

    /**
     * Insert multiple rows using query() and bind_param().
     *
     * @depends testCreateTable
     */
    public function testInsertData(MySQLDb $MySQL)
    {
        $successCnt = 0;

        for ($i = 1; $i <= 5; $i++) {
            $result = $MySQL->query(
                "INSERT INTO tmp_table SET t_id=?, t_datetime=NOW(), name=?, status=?, email=?",
                [$i, "Name{$i}", 'active', "user{$i}@test.com"]
            );
            if ($result) $successCnt++;
        }

        $MySQL->bind_param('isss');
        $result = $MySQL->query(
            "INSERT INTO tmp_table SET t_id=?, t_datetime=NOW(), name=?, status=?, email=?",
            [6, "Name6", 'inactive', "user6@test.com"]
        );
        if ($result) $successCnt++;

        $this->assertEquals(6, $successCnt, "모든 INSERT 쿼리가 성공해야 합니다.");

        // 또는 fetch 로도 검증 가능
        $rows = $MySQL->fetchAll("SELECT * FROM tmp_table WHERE t_id <= ?", [6]);
        $this->assertCount(6, $rows, "6개의 레코드가 정상 삽입되어야 합니다.");

        return $MySQL;
    }

    /**
     * Run a named parameter query.
     *
     * @depends testInsertData
     */
    public function testNamedParamQuery(MySQLDb $MySQL)
    {
        $query = "SELECT * FROM tmp_table WHERE t_id > :id";
        $result = $MySQL->query($query, ['id' => 3]);
        $this->assertGreaterThan(0, $result->num_rows);
        return $MySQL;
    }

    /**
     * Test an UPDATE operation.
     *
     * @depends testInsertData
     */
    public function testUpdateRow(MySQLDb $MySQL)
    {
        $affected = $MySQL->query("UPDATE tmp_table SET status=? WHERE email=?", ['updated', 'user1@test.com']);
        $this->assertGreaterThan(0, $affected);
        return $MySQL;
    }

    /**
     * Test a DELETE operation.
     *
     * @depends testInsertData
     */
    public function testDeleteRow(MySQLDb $MySQL)
    {
        $affected = $MySQL->query("DELETE FROM tmp_table WHERE t_id=?", [2]);
        $this->assertGreaterThan(0, $affected);
        return $MySQL;
    }

    /**
     * Test SELECT with IN condition using fetch().
     *
     * @depends testInsertData
     */
    public function testFetchInCondition(MySQLDb $MySQL)
    {
        $ids = [1, 3, 5];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $query = "SELECT * FROM tmp_table WHERE t_id IN ($placeholders)";
        $result = [];
        while ($row = $MySQL->fetch($query, $ids)) {
            $result[] = $row;
        }
        $this->assertCount(3, $result);
        return $MySQL;
    }

    /**
     * Test SELECT with LIKE condition.
     *
     * @depends testInsertData
     */
    public function testFetchLikeCondition(MySQLDb $MySQL)
    {
        $query = "SELECT * FROM tmp_table WHERE name LIKE ?";
        $result = [];
        while ($row = $MySQL->fetch($query, ['Name%'])) {
            $result[] = $row;
        }
        $this->assertGreaterThan(0, count($result));
        return $MySQL;
    }

    /**
     * Test SELECT with LIMIT clause.
     *
     * @depends testInsertData
     */
    public function testFetchLimit(MySQLDb $MySQL)
    {
        $query = "SELECT * FROM tmp_table LIMIT 2";
        $rows = [];
        while ($row = $MySQL->fetch($query)) {
            $rows[] = $row;
        }
        $this->assertCount(2, $rows);
        return $MySQL;
    }

    /**
     * Test lastInsertId() works after insert.
     *
     * @depends testInsertData
     */
    public function testLastInsertId(MySQLDb $MySQL)
    {
        $MySQL->query("INSERT INTO tmp_table SET t_id=?, t_datetime=NOW(), name=?, status=?, email=?", [99, "Test", 'active', 'user99@test.com']);
        $id = $MySQL->lastInsertId();
        $this->assertNotNull($id);
        return $MySQL;
    }

    /**
     * Test fetchAll() returns all matching rows.
     *
     * @depends testInsertData
     */
    public function testFetchAll(MySQLDb $MySQL)
    {
        $query = "SELECT * FROM tmp_table WHERE t_id > ?";
        $rows = $MySQL->fetchAll($query, [0]);
        $this->assertGreaterThanOrEqual(5, count($rows));
        return $MySQL;
    }

    /**
     * Test fetch() with bind_param before loop (style 1).
     *
     * @depends testInsertData
     */
    public function testBindParamAndFetchInLoop1(MySQLDb $MySQL)
    {
        $query = "SELECT * FROM tmp_table WHERE t_id <= ? AND status = ? AND name LIKE ?";
        $params = [3, 'active', 'Name%'];

        $MySQL->bind_param('iss', $params);
        $list = [];
        while ($row = $MySQL->fetch($query)) {
            $list[] = $row;
        }
        $this->assertGreaterThan(0, count($list));
    }

    /**
     * Test fetch() with params inside the loop (style 2).
     *
     * @depends testInsertData
     */
    public function testBindParamAndFetchInLoop2(MySQLDb $MySQL)
    {
        $query = "SELECT * FROM tmp_table WHERE t_id <= ? AND status = ? AND name LIKE ?";
        $params = [3, 'active', 'Name%'];

        $MySQL->bind_param('iss');
        $list = [];
        while ($row = $MySQL->fetch($query, $params)) {
            $list[] = $row;
        }
        $this->assertGreaterThan(0, count($list));
    }

    /**
     * Test fetch() returns null for unmatched condition.
     *
     * @depends testInsertData
     */
    public function testFetchReturnsNull(MySQLDb $MySQL)
    {
        $query = "SELECT * FROM tmp_table WHERE t_id = ? AND name = ?";
        $params = [999, 'NoSuchName'];
        $row = $MySQL->fetch($query, $params);
        $this->assertNull($row);
    }

    /**
     * Test fetchOne() returns a single row.
     *
     * @depends testInsertData
     */
    public function testFetchOne(MySQLDb $MySQL)
    {
        $row = $MySQL->fetchOne("SELECT * FROM tmp_table WHERE t_id = ?", [3]);
        $this->assertNotNull($row);
        $this->assertEquals(3, $row['t_id']);
        $this->assertEquals('Name3', $row['name']);
        return $MySQL;
    }

    /**
     * Test that fetch() triggers mysqli_sql_exception handling.
     *
     * @depends testInsertData
     */
    public function testFetchCatchBlock_MysqliSqlException(MySQLDb $MySQL)
    {
        // 존재하지 않는 컬럼명을 이용해 오류 유도
        $this->expectException(Exception::class);
        $MySQL->fetch("SELECT not_exist_column FROM tmp_table");
    }

    /**
     * Test that fetch() triggers generic exception handling.
     *
     * @depends testInsertData
     */
    public function testFetchCatchBlock_GenericException(MySQLDb $MySQL)
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/Invalid query format/');

        // query() 내부에 배열 전달해서 타입 오류 유도
        $MySQL->fetch(["not a string"]);
    }

    /**
     * @depends testInsertData
     */
    public function testFetchReturnsNullForNonSelectQuery(MySQLDb $MySQL)
    {
        // DELETE 문은 result_query 에 true 들어가서 result type mismatch 발생
        $result = $MySQL->fetch("DELETE FROM tmp_table WHERE t_id = ?", [999]);
        $this->assertNull($result); // 이게 핵심
    }

    public function testRealEscapeStringThrowsWhenNoConnection()
    {
        $db = new MySQLDbFakeNoConnection();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('No active DB connection');

        $db->realEscapeString("test");
    }

    public function testRealEscapeStringWithNonStringValue()
    {
        $MySQL = new MySQLDb(
            $GLOBALS['DB_HOST'],
            $GLOBALS['DB_USER'],
            $GLOBALS['DB_PASSWD'],
            $GLOBALS['DB_NAME'],
            $GLOBALS['DB_PORT']
        );

        $result = $MySQL->realEscapeString(12345);  // int
        $this->assertSame(12345, $result);

        $result = $MySQL->realEscapeString(null);   // null
        $this->assertNull($result);

        $MySQL->close();
    }

    /**
     * Expect query() to throw exception on bad table.
     *
     * @depends testFetchAll
     */
    public function testQueryError(MySQLDb $MySQL)
    {
        $this->expectException(Exception::class);
        $MySQL->query("SELECT * FROM not_exist_table");
    }

    /**
     * Catch and assert error from invalid query.
     *
     * @depends testFetchAll
     */
    public function testCatchQueryError(MySQLDb $MySQL)
    {
        try {
            $MySQL->query("SELECT * FROM not_exist_table");
        } catch (Exception $e) {
            $this->assertEquals($MySQL->errorNo(), $e->getCode());
        }
        return $MySQL;
    }

    /**
     * Drop the test table.
     *
     * @depends testCatchQueryError
     */
    public function testDropTable(MySQLDb $MySQL)
    {
        $result = $MySQL->query("DROP TABLE IF EXISTS tmp_table");
        $this->assertTrue($result);
        return $MySQL;
    }

    /**
     * Close DB connection after all tests.
     */
    public static function tearDownAfterClass(): void
    {
        if (self::$MySQL !== null) self::$MySQL->close();
    }
}
