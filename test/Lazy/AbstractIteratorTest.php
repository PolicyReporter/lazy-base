<?php

declare(strict_types=1);

namespace Policyreporter\LazyBase\Lazy;

require_once('test/TestCase.php');

/**
 * @small
 */
class AbstractIteratorTest extends \Policyreporter\LazyBase\TestCase
{
    public function test_toIndex_empty()
    {
        $testIterator = new ArrayObject([]);
        //should NOT throw
        $this->assertEquals([], $testIterator->toIndex());
    }

    public function test_toIndex_oneColumn()
    {
        $testIterator = new ArrayObject([['testColumn' => 10], ['testColumn' => 20], ['testColumn' => 30]]);
        $this->assertThrows(
            'Exception',
            [$testIterator, 'toIndex'],
            [],
            '/Invalid query result to channel into a map. The query must return at least 2 columns/'
        );
    }

    public function test_toIndex_twoColumn()
    {
        $testIterator = new ArrayObject(
            [
                ['testColumn' => 10, 'price' => -14],
                ['testColumn' => 20, 'price' => -543],
                ['testColumn' => 30, 'price' => 2345]
            ]
        );
        //should NOT throw
        $this->assertEquals(
            [10 => ['price' => -14], 20 => ['price' => -543], 30 => ['price' => 2345]],
            $testIterator->toIndex()
        );
    }

    public function test_toIndexedColumn_empty()
    {
        $testIterator = new ArrayObject([]);
        //should NOT throw
        $this->assertEquals([], $testIterator->toIndexedColumn());
    }

    public function test_toIndexedColumn_oneColumn()
    {
        $testIterator = new ArrayObject([['testColumn' => 10], ['testColumn' => 20], ['testColumn' => 30]]);
        $this->assertThrows(
            'Exception',
            [$testIterator, 'toIndexedColumn'],
            [],
            '/Invalid query result to channel into a hash, the query must return exactly two columns/'
        );
    }

    public function test_toIndexedColumn_twoColumn()
    {
        $testIterator = new ArrayObject(
            [
                ['testColumn' => 10, 'price' => -14],
                ['testColumn' => 20, 'price' => -543],
                ['testColumn' => 30, 'price' => 2345]
            ]
        );
        //should NOT throw
        $this->assertEquals([10 => -14, 20 => -543, 30 => 2345], $testIterator->toIndexedColumn());
    }

    public function test_toIndex2d_empty()
    {
        $testIterator = new ArrayObject([]);
        //should NOT throw
        $this->assertEquals([], $testIterator->toIndex2d());
    }

    public function test_toIndex2d_oneColumn()
    {
        $testIterator = new ArrayObject([['testColumn' => 10], ['testColumn' => 20], ['testColumn' => 30]]);
        $this->assertThrows(
            'Exception',
            [$testIterator, 'toIndex2d'],
            [],
            '/Invalid query result to channel into a map. The query must return at least 3 columns/'
        );
    }

    public function test_toIndex2d_twoColumns()
    {
        $testIterator = new ArrayObject(
            [
                ['testColumn' => 10, 'price' => -14],
                ['testColumn' => 20, 'price' => -543],
                ['testColumn' => 30, 'price' => 2345],
            ]
        );
        $this->assertThrows(
            'Exception',
            [$testIterator, 'toIndex2d'],
            [],
            '/Invalid query result to channel into a map. The query must return at least 3 columns/'
        );
    }

    public function test_toIndex2d_threeColumns()
    {
        $testIterator = new ArrayObject(
            [
                ['testColumn' => 10, 'index' => 5, 'count' => 99],
                ['testColumn' => 20, 'index' => -543, 'count' => 185],
                ['testColumn' => 20, 'index' => 543, 'count' => 92],
                ['testColumn' => 30, 'index' => 2344, 'count' => -265],
                ['testColumn' => 30, 'index' => 2345, 'count' => 265], //is overwritten
                ['testColumn' => 30, 'index' => 2345, 'count' => 'seventeen'],
            ]
        );

        $expected = [
            10 => [5 => ['count' => 99]],
            20 => [543 => ['count' => 92], '-543' => ['count' => 185]],
            30 => [
                2345 => ['count' => 'seventeen'],
                2344 => ['count' => '-265'],
            ],
        ];
        $this->assertEquals($expected, $testIterator->toIndex2d());
    }
}
