<?php

declare(strict_types=1);

namespace Policyreporter\LazyBase\Lazy;

require_once('test/TestCase.php');

/**
 * @small
 */
class ArrayObjectTest extends \Policyreporter\LazyBase\TestCase
{
    public function test_toValue()
    {
        $this->assertEquals(
            1,
            (new ArrayObject([['a' => 1]]))->toValue()
        );
        $this->assertThrows(
            'Exception',
            [new ArrayObject([['a' => 1, 'b' => 2]]), 'toValue']
        );
        $this->assertThrows(
            'Exception',
            [new ArrayObject([['a' => 1], ['a' => 2]]), 'toValue']
        );
    }

    public function test_toRow()
    {
        $this->assertSame(
            [1],
            (new ArrayObject([[0 => 1]]))->toRow()
        );
        $this->assertSame(
            ['0' => 1],
            (new ArrayObject([['0' => 1]]))->toRow()
        );
        $this->assertThrows(
            'Exception',
            [new ArrayObject([['a' => 1], ['a' => 2]]), 'toRow']
        );
    }

    public function test_map()
    {
        $this->assertSame(
            2,
            (new ArrayObject([['0' => 1]]))->map(function ($r) {
                $r['0']++;
                return $r;
            })->toValue()
        );
        $this->assertSame(
            [2],
            (new ArrayObject([['0' => 1]]))->map(function ($r) {
                $r['0']++;
                return $r;
            })->toRow()
        );
        $this->assertSame(
            [[2]],
            (new ArrayObject([['0' => 1]]))->map(function ($r) {
                $r['0']++;
                return $r;
            })->realize()
        );
    }

    public function test_mapJsonDecode()
    {
        $this->assertSame(
            [1],
            (new ArrayObject([['json' => '[1]']]))->mapJsonDecode('json')->toValue()
        );
        $this->assertSame(
            [1, '1', null],
            (new ArrayObject([['json' => '[1, "1", null]']]))->mapJsonDecode('json')->toValue()
        );
        $this->assertSame(
            1,
            (new ArrayObject([['json' => '1']]))->mapJsonDecode('json')->toValue()
        );
    }

    public function test_mapColumn()
    {

        $result = (new ArrayObject([['a' => 1]]))->map(function ($r) {
            $r['a']++;
            return $r;
        })->mapColumn();
        $this->assertInstanceOf(ArrayObject::class, $result);
        foreach ($result as $v) {
            $this->assertSame(2, $v);
        }
        $this->assertSame(
            [2],
            (new ArrayObject([['a' => 1]]))->map(function ($r) {
                $r['a']++;
                return $r;
            })->mapColumn()->realize()
        );
        $this->assertThrows('Exception', [(new ArrayObject([['a' => 1, 'b' => 2]])), 'mapColumn']);
    }

    public function test_columnNameAmbiguity()
    {
        $this->assertSame(
            [2 => 'one', 3 => 'two'],
            (new ArrayObject([['a' => 1, 'b' => 'one'], ['a' => 2, 'b' => 'two']]))->map(function ($r) {
                $r['a']++;
                return $r;
            })->mapIndexedColumn()->realize()
        );
        $this->markTestIncomplete(
            "Due to language constraints we're prevented from testing Lazy\\ArrayObject instances " .
            "that wrap arrays with repeated keys."
        );
        // $this->assertThrows(
        //     \Exception::class,
        //     [(new Lazy\ArrayObject([['a' => 1, 'a' => 1], ['a' => 1, 'a' => 1]])), 'realize']
        // );
        // $this->assertThrows(
        //     \Exception::class,
        //     [(new Lazy\ArrayObject([['a' => 'key1', 'a' => 'val1'], ['a' => 'key1', 'a' => 'val1']])), 'realize']
        // );
        $this->assertSame(
            ['key1' => 'val1', 'key2' => 'val2'],
            (new ArrayObject([['key1', 'val1'], ['key2', 'val2']]))->mapIndexedColumn()->realize()
        );
    }

    public function test_mapIndexedColumn()
    {
        $result = (new ArrayObject([['a' => 1, 'b' => 'one']]))->map(function ($r) {
            $r['a']++;
            return $r;
        })->mapIndexedColumn();
        $this->assertInstanceOf(ArrayObject::class, $result);
        foreach ($result as $k => $v) {
            $this->assertSame(2, $k);
            $this->assertSame('one', $v);
        }
        $this->assertSame(
            [2 => 'one'],
            (new ArrayObject([['a' => 1, 'b' => 'one']]))->map(function ($r) {
                $r['a']++;
                return $r;
            })->mapIndexedColumn()->realize()
        );
        $this->assertSame(
            (new ArrayObject([['a' => 1, 'b' => 'one']]))->map(function ($r) {
                $r['a']++;
                return $r;
            })->mapIndex()->toColumn(),
            (new ArrayObject([['a' => 1, 'b' => 'one']]))->map(function ($r) {
                $r['a']++;
                return $r;
            })->mapIndexedColumn()->realize(),
            'mapIndexedColumn failed to produce the same results as mapIndex->mapColumn'
        );
        $this->assertThrows('Exception', [(new ArrayObject([[1]])), 'mapIndexedColumn']);
        $this->assertThrows('Exception', [(new ArrayObject([[1, 2, 3]])), 'mapIndexedColumn']);
    }

    public function test_mapIndex()
    {
        $result = (new ArrayObject([['a' => 1, 'b' => 'one', 'c' => 1]]))->map(function ($r) {
            $r['a']++;
            return $r;
        })->mapIndex();
        $this->assertInstanceOf(ArrayObject::class, $result);
        foreach ($result as $k => $v) {
            $this->assertSame(2, $k);
            $this->assertSame(['b' => 'one', 'c' => 1], $v);
        }
        $this->assertSame(
            [2 => ['b' => 'one', 'c' => 1]],
            (new ArrayObject([['a' => 1, 'b' => 'one', 'c' => 1]]))->map(function ($r) {
                $r['a']++;
                return $r;
            })->mapIndex()->realize()
        );
        $this->assertThrows('Exception', [(new ArrayObject([['a' => 1]])), 'mapIndex']);
    }

    public function test_singleRowQuery()
    {
        $this->assertEquals([['a' => 1]], (new ArrayObject([['a' => 1]]))->realize());
        $this->assertEquals(['a' => 1], (new ArrayObject([['a' => 1]]))->toRow());
        $this->assertEquals(1, (new ArrayObject([['a' => 1]]))->toValue());
    }

    public function test_multiRowQuery()
    {
        $this->assertEquals([['a' => 1], ['a' => 2]], (new ArrayObject([['a' => 1], ['a' => 2]]))->realize());
        $this->assertThrows('Exception', [(new ArrayObject([['a' => 1], ['a' => 2]])), 'toRow']);
        $this->assertThrows('Exception', [(new ArrayObject([['a' => 1], ['a' => 2]])), 'toValue']);
    }

    public function test_toCsvHandle()
    {
        $this->assertEquals(
            <<<CSV
Foo,Bar
1,2
3,4
CSV
            ,
            \stream_get_contents((
                new ArrayObject([['Foo' => 1, 'Bar' => 2], ['Foo' => 3, 'Bar' => 4]])
            )->toCsvHandle()),
            'A simple toCsvHandle should result in a primitive stream'
        );

        $this->assertEquals(
            <<<CSV
Foo,Bar
1,2,3
3,4,7
CSV
            ,
            \stream_get_contents(
                (
                    new ArrayObject([['Foo' => 1, 'Bar' => 2], ['Foo' => 3, 'Bar' => 4]])
                )->map(function ($row) {
                    $row['Baz'] = $row['Foo'] + $row['Bar'];
                    return $row;
                })->toCsvHandle()
            ),
            'A toCsvHandle stream that doesn\'t bind columns appropriately ' .
            'when renaming/adding will have incorrect titles'
        );

        $this->assertEquals(
            <<<CSV
Foo,Bar,Baz
1,2,3
3,4,7
CSV
            ,
            \stream_get_contents(
                (
                    new ArrayObject([['Foo' => 1, 'Bar' => 2], ['Foo' => 3, 'Bar' => 4]])
                )->map(function ($row) {
                    $row['Baz'] = $row['Foo'] + $row['Bar'];
                    return $row;
                })->toCsvHandle(['Foo', 'Bar', 'Baz'])
            ),
            'When properly bound with correct column names this toCsvHandle stream should have the third column name'
        );
    }
}
