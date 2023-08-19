<?php

declare(strict_types=1);

namespace Policyreporter\LazyBase\Lazy;

require_once('test/TestCase.php');

/**
 * @large
 */
class PDOStatementLargeTest extends \Policyreporter\LazyBase\TestCase
{
    public function test_nameBound()
    {
        $match = 1;
        $this->assertEquals(
            [['a' => 1, 'b' => 'one']],
            $this->dbHandle()->run(
                <<<SQL
                SELECT a, b
                FROM (VALUES (1, 'one'), (2, 'two')) AS foo(a, b)
                WHERE a = :match
                SQL,
                compact('match')
            )->realize()
        );

        $testVal = 2;
        $this->assertEquals(
            [['testval' => 2, 'a' => 1, 'b' => 'one']],
            $this->dbHandle()->run(
                <<<SQL
                SELECT :testVal::INTEGER AS testVal, a, b
                FROM (VALUES (1, 'one'), (2, 'two')) AS foo(a, b)
                WHERE a = :match
                SQL,
                compact('match', 'testVal')
            )->realize()
        );

        $this->assertEquals(
            [['testval' => 2, 'a' => 1, 'b' => 'one']],
            $this->dbHandle()->run(
                <<<SQL
                SELECT :testVal::INTEGER AS testVal, a, b
                FROM (VALUES (1, 'one'), (2, 'two')) AS foo(a, b)
                WHERE a = :match
                SQL,
                compact('testVal', 'match')
            )->realize()
        );
    }

    public function test_toValue()
    {
        $this->assertEquals(
            1,
            $this->dbHandle()->run('SELECT 1')->toValue()
        );

        $this->assertEquals(
            1,
            $this->dbHandle()->run('SELECT 1')->mapColumn()->toRow()
        );

        $this->assertThrows(
            \Exception::class,
            [$this->dbHandle()->run('SELECT 1')->mapColumn(), 'toValue']
        );

        $this->assertThrows(
            \Exception::class,
            [$this->dbHandle()->run('SELECT 1, 2'), 'toValue']
        );

        $this->assertThrows(
            \Exception::class,
            [$this->dbHandle()->run('SELECT * FROM (VALUES (1), (2)) AS foo'), 'toValue']
        );
    }

    public function test_toRow()
    {
        $this->assertSame(
            [1],
            $this->dbHandle()->run('SELECT 1 "0"')->toRow()
        );
        $this->assertThrows(
            'Exception',
            [$this->dbHandle()->run('SELECT * FROM (VALUES (1), (2)) AS foo'), 'toRow']
        );
    }

    public function test_map()
    {
        $this->assertSame(
            2,
            $this->dbHandle()->run('SELECT 1 "0"')->map(function ($r) {
                $r['0']++;
                return $r;
            })->toValue()
        );
        $this->assertSame(
            [2],
            $this->dbHandle()->run('SELECT 1 "0"')->map(function ($r) {
                $r['0']++;
                return $r;
            })->toRow()
        );
        $this->assertSame(
            [[2]],
            $this->dbHandle()->run('SELECT 1 "0"')->map(function ($r) {
                $r['0']++;
                return $r;
            })->realize()
        );
    }

    public function test_mapJsonDecode()
    {
        $this->assertSame(
            [1],
            $this->dbHandle()->run("SELECT '[1]' AS json")->mapJsonDecode('json')->toValue()
        );
        $this->assertSame(
            [1, '1', null],
            $this->dbHandle()->run("SELECT '[1, \"1\", null]' AS json")->mapJsonDecode('json')->toValue()
        );
        $this->assertSame(
            1,
            $this->dbHandle()->run("SELECT 1 AS json")->mapJsonDecode('json')->toValue()
        );
    }

    public function test_mapColumn()
    {
        $result = $this->dbHandle()->run(
            'SELECT * FROM (VALUES (1)) AS foo(a)'
        )->map(function ($r) {
            $r['a']++;
            return $r;
        })->mapColumn();
        $this->assertInstanceOf(PDOStatement::class, $result);
        foreach ($result as $v) {
            $this->assertSame(2, $v);
        }
        $this->assertSame(
            [2],
            $this->dbHandle()->run(
                'SELECT * FROM (VALUES (1)) AS foo(a)'
            )->map(function ($r) {
                $r['a']++;
                return $r;
            })->mapColumn()->realize()
        );

        $this->assertThrows(
            \Exception::class,
            [$this->dbHandle()->run('SELECT 1, 2'), 'mapColumn']
        );
    }

    public function test_columnNameAmbiguity()
    {
        $this->assertSame(
            [2 => 'one', 3 => 'two'],
            $this->dbHandle()->run(
                <<<SQL
                SELECT * FROM (VALUES (1, 'one'), (2, 'two')) AS foo (a, b)
                SQL
            )->map(function ($r) {
                $r['a']++;
                return $r;
            })->mapIndexedColumn()->realize()
        );

        $this->assertSame(
            ['key1' => 1, 'key2' => 2],
            $this->dbHandle()->run(
                <<<SQL
                SELECT 'key' || a, a FROM (VALUES (1), (2)) AS foo(a)
                SQL
            )->mapIndexedColumn()->realize()
        );

        $this->assertSame(
            [1 => 'val1', 2 => 'val2'],
            $this->dbHandle()->run(
                <<<SQL
                SELECT a, 'val' || a FROM (VALUES (1), (2)) AS foo(a)
                SQL
            )->mapIndexedColumn()->realize()
        );

        $this->assertThrows(
            \Exception::class,
            [
                $this->dbHandle()->run(
                    <<<SQL
                    SELECT a, a FROM (VALUES (1), (2)) AS foo(a)
                    SQL
                ),
                'realize',
            ]
        );
        $this->assertThrows(
            \Exception::class,
            [
                $this->dbHandle()->run(
                    <<<SQL
                    SELECT 'key' || a AS blah, 'val' || a AS blah
                    FROM (VALUES (1), (2)) AS foo(a)
                    SQL
                ),
                'realize',
            ]
        );
        $this->assertSame(
            ['key1' => 'val1', 'key2' => 'val2'],
            $this->dbHandle()->run(
                <<<SQL
                SELECT 'key' || a, 'val' || a FROM (VALUES (1), (2)) AS foo(a)
                SQL
            )->mapIndexedColumn()->realize()
        );
    }

    public function test_mapIndexedColumn()
    {
        $result = $this->dbHandle()->run(
            <<<SQL
            SELECT * FROM (VALUES (1, 'one')) AS foo(a, b)
            SQL
        )->map(function ($r) {
            $r['a']++;
            return $r;
        })->mapIndexedColumn();
        $this->assertInstanceOf(PDOStatement::class, $result);
        foreach ($result as $k => $v) {
            $this->assertSame(2, $k);
            $this->assertSame('one', $v);
        }

        $this->assertSame(
            [2 => 'one'],
            $this->dbHandle()->run(
                <<<SQL
                SELECT * FROM (VALUES (1, 'one')) AS foo (a, b)
                SQL
            )->map(function ($r) {
                $r['a']++;
                return $r;
            })->mapIndexedColumn()->realize()
        );

        $this->assertSame(
            $this->dbHandle()->run(
                <<<SQL
                SELECT * FROM (VALUES (1, 'one')) AS foo (a, b)
                SQL
            )->map(function ($r) {
                $r['a']++;
                return $r;
            })->mapIndex()->toColumn(),
            $this->dbHandle()->run(
                <<<SQL
                SELECT * FROM (VALUES (1, 'one')) AS foo (a, b)
                SQL
            )->map(function ($r) {
                $r['a']++;
                return $r;
            })->mapIndexedColumn()->realize(),
            'mapIndexedColumn failed to produce the same results as mapIndex->mapColumn'
        );

        $this->assertThrows(
            \Exception::class,
            [$this->dbHandle()->run('SELECT 1'), 'mapIndexedColumn']
        );

        $this->assertThrows(
            \Exception::class,
            [$this->dbHandle()->run('SELECT 1, 2, 3'), 'mapIndexedColumn']
        );
    }

    public function test_mapIndex()
    {
        $result = $this->dbHandle()->run(
            <<<SQL
            SELECT a, b, 1 AS c FROM (VALUES (1, 'one')) AS foo(a, b)
            SQL
        )->map(function ($r) {
            $r['a']++;
            return $r;
        })->mapIndex();
        $this->assertInstanceOf(PDOStatement::class, $result);
        foreach ($result as $k => $v) {
            $this->assertSame(2, $k);
            $this->assertSame(['b' => 'one', 'c' => 1], $v);
        }

        $this->assertSame(
            [2 => ['b' => 'one', 'c' => 1]],
            $this->dbHandle()->run(
                <<<SQL
                SELECT a, b, 1 AS c FROM (VALUES (1, 'one')) AS foo(a, b)
                SQL
            )->map(function ($r) {
                $r['a']++;
                return $r;
            })->mapIndex()->realize()
        );

        $this->assertThrows(
            \Exception::class,
            [$this->dbHandle()->run('SELECT 1'), 'mapIndex']
        );
    }

    public function test_arrayBound()
    {
        $match = [1, 2];
        $testVal = 4;

        $this->assertEquals(
            [
                ['a' => 1, 'b' => 'one'],
                ['a' => 2, 'b' => 'two'],
            ],
            $this->dbHandle()->run(
                <<<SQL
                SELECT a, b
                FROM (VALUES (1, 'one'), (2, 'two')) AS foo(a, b)
                WHERE a IN(:match)
                SQL,
                compact('match')
            )->realize()
        );

        $this->assertEquals(
            [
                ['a' => 1, 'b' => 'one', 'testval' => 4],
                ['a' => 2, 'b' => 'two', 'testval' => 4],
            ],
            $this->dbHandle()->run(
                <<<SQL
                SELECT a, b, :testVal::INTEGER AS testVal
                FROM (VALUES (1, 'one'), (2, 'two')) AS foo(a, b)
                WHERE a IN(:match)
                SQL,
                compact('match', 'testVal')
            )->realize()
        );

        $this->assertEquals(
            [
                ['a' => 1, 'b' => 'one', 'testval' => 4],
                ['a' => 2, 'b' => 'two', 'testval' => 4],
            ],
            $this->dbHandle()->run(
                <<<SQL
                SELECT a, b, :testVal::INTEGER AS testVal
                FROM (VALUES (1, 'one'), (2, 'two')) AS foo(a, b)
                WHERE a IN(:match)
                SQL,
                compact('testVal', 'match')
            )->realize()
        );
    }

    public function test_singleRowQuery()
    {
        $query = 'SELECT 1 AS a';
        $this->assertEquals(
            [['a' => 1]],
            $this->dbHandle()->run($query)->realize()
        );
        $this->assertEquals(
            ['a' => 1],
            $this->dbHandle()->run($query)->toRow()
        );
        $this->assertEquals(
            1,
            $this->dbHandle()->run($query)->toValue()
        );
    }

    public function test_multiRowQuery()
    {
        $query = <<<SQL
        SELECT a FROM (VALUES (1, 'one'), (2, 'two')) AS foo(a, b) WHERE a = 1 OR a = 2 ORDER BY a ASC
        SQL;
        $this->assertEquals(
            [
                ['a' => 1],
                ['a' => 2]
            ],
            $this->dbHandle()->run($query)->realize()
        );
        $this->assertThrows(
            \Exception::class,
            [$this->dbHandle()->run($query), 'toRow']
        );
        $this->assertThrows(
            \Exception::class,
            [$this->dbHandle()->run($query), 'toValue']
        );
    }

    public function test_floatBinding()
    {
        $float = 3.6;
        // Weak comparison, we don't care (for this test) if things come out
        // string wrapped or not
        $this->assertSame(
            "{$float}",
            $this->dbHandle()->run("SELECT :float::DECIMAL", compact('float'))->toValue()
        );
    }

    public function test_boolBinding()
    {
        $bool = true;
        // Weak comparison, we don't care (for this test) if things come out
        // string wrapped or not
        $this->assertSame(
            true,
            $this->dbHandle()->run("SELECT :bool::BOOLEAN", compact('bool'))->toValue()
        );
    }
}
