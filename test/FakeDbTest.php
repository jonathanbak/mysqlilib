<?php
use PHPUnit\Framework\TestCase;
use MySQLiLib\Mock\FakeDb;

/**
 * Unit tests for the FakeDb mock database engine.
 *
 * This class validates FakeDb's support for:
 * - INSERT, SELECT, UPDATE, DELETE SQL-like operations
 * - WHERE 조건자 (=, >, LIKE, IN)
 * - LIMIT 절 처리
 * - fetch() 반복 호출을 통한 이터레이션
 *
 * @covers \MySQLiLib\Mock\FakeDb
 */
class FakeDbTest extends TestCase
{
    private FakeDb $db;

    /**
     * Set up the mock database with initial seed data before each test.
     */
    protected function setUp(): void
    {
        $this->db = new FakeDb();
        $this->db->connect('', '', '', '', 3306);

        $this->db->seed('users', [
            ['id' => 1, 'name' => 'John', 'email' => 'john@example.com', 'active' => 0],
            ['id' => 2, 'name' => 'Jane', 'email' => 'jane@example.com', 'active' => 0],
            ['id' => 3, 'name' => 'Joe',  'email' => 'joe@example.com',  'active' => 0],
        ]);
    }

    /**
     * Test inserting a new row and retrieving all data.
     */
    public function testInsert()
    {
        $this->db->query("INSERT INTO users SET id=?, name=?, email=?", [4, 'Jess', 'jess@example.com']);
        $all = $this->db->getAll('users');
        $this->assertCount(4, $all);
        $this->assertEquals('Jess', $all[3]['name']);
    }

    /**
     * Comprehensive test for LIKE, IN, UPDATE, DELETE, LIMIT functionality.
     */
    public function testFakeDbLikeInUpdateDelete()
    {
        // LIKE
        $result = [];
        while ($row = $this->db->fetch("SELECT * FROM users WHERE name LIKE ?", ['J%'])) {
            $result[] = $row;
        }
        $this->assertCount(3, $result);

        // IN
        $result = [];
        while ($row = $this->db->fetch("SELECT * FROM users WHERE id IN (?, ?)", [1, 3])) {
            $result[] = $row;
        }
        $this->assertCount(2, $result);
        $this->assertEquals([1, 3], array_column($result, 'id'));

        // UPDATE
        $affected = $this->db->query("UPDATE users SET name = ? WHERE id = ?", ['UpdatedName', 2]);
        $this->assertEquals(1, $affected);
        $updated = $this->db->fetch("SELECT * FROM users WHERE id = ?", [2]);
        $this->assertEquals('UpdatedName', $updated['name']);

        // DELETE
        $affected = $this->db->query("DELETE FROM users WHERE name = ?", ['UpdatedName']);
        $this->assertEquals(1, $affected);
        $deleted = $this->db->fetch("SELECT * FROM users WHERE id = ?", [2]);
        $this->assertNull($deleted);

        // LIMIT
        $result = [];
        while ($row = $this->db->fetch("SELECT * FROM users LIMIT 2")) {
            $result[] = $row;
        }
        $this->assertCount(2, $result);
    }

    /**
     * Test SELECT with greater-than (>) condition.
     */
    public function testSelectGreaterThan()
    {
        $result = [];
        while ($row = $this->db->fetch("SELECT * FROM users WHERE id > ?", [1])) {
            $result[] = $row;
        }

        $this->assertCount(2, $result);
        $this->assertEquals(2, $result[0]['id']);
    }

    /**
     * Test DELETE removes the correct row and affects count.
     */
    public function testDelete()
    {
        $this->db->query("DELETE FROM users WHERE id = ?", [2]);
        $all = $this->db->getAll('users');
        $this->assertCount(2, $all);
        $this->assertEquals([1, 3], array_column($all, 'id'));
    }

    /**
     * Test UPDATE modifies the correct row.
     */
    public function testUpdate()
    {
        $this->db->query("UPDATE users SET active=? WHERE email=?", [1, 'john@example.com']);
        $row = $this->db->fetch("SELECT * FROM users WHERE email=?", ['john@example.com']);
        $this->assertEquals(1, $row['active']);
    }

    /**
     * Test SELECT with LIKE condition matching partial strings.
     */
    public function testLike()
    {
        $rows = [];
        while ($row = $this->db->fetch("SELECT * FROM users WHERE name LIKE ?", ['Jo%'])) {
            $rows[] = $row;
        }
        $this->assertCount(2, $rows);
        $this->assertEquals(['John', 'Joe'], array_column($rows, 'name'));
    }

    /**
     * Test SELECT with IN condition and multiple parameters.
     */
    public function testIn()
    {
        $rows = [];
        while ($row = $this->db->fetch("SELECT * FROM users WHERE id IN (?, ?, ?)", [1, 3, 99])) {
            $rows[] = $row;
        }
        $this->assertCount(2, $rows);
        $this->assertEquals([1, 3], array_column($rows, 'id'));
    }

    /**
     * Test SELECT with LIMIT clause returns correct number of rows.
     */
    public function testLimit()
    {
        $rows = [];
        while ($row = $this->db->fetch("SELECT * FROM users LIMIT 2")) {
            $rows[] = $row;
        }
        $this->assertCount(2, $rows);
    }

    public function testFetchThrowsLogicExceptionOnNonSelect()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage("Only SELECT queries can be used with fetch()");

        $this->db->fetch("DELETE FROM users WHERE id = ?", [1]);
    }
}