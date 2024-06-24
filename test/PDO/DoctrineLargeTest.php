<?php

declare(strict_types=1);

namespace Policyreporter\LazyBase\PDO;

use Db\Lazy;

require_once('test/TestCase.php');
require_once('test/Doctrine.php');

/**
 * @large
 */
class DoctrineLargeTest extends \Policyreporter\LazyBase\TestCase
{
    private static $queryBuilder = null;

    public function setUp(): void
    {
        parent::setUp();
        if (self::$queryBuilder === null) {
            self::$queryBuilder = new \Policyreporter\LazyBase\QueryBuilder(
                (new \Policyreporter\LazyBase\Factory\Doctrine())->openHandle(
                    $this->dbHandle(),
                    \Policyreporter\LazyBase\test\Doctrine::class
                )
            );
        }
    }

    public function tearDown(): void
    {
        $this->dbHandle()->run(
            <<<SQL
            DROP TABLE IF EXISTS lazybase
            SQL
        );
    }

    public function test_responseClass()
    {
        $this->dbHandle()->run(
            <<<SQL
            CREATE TABLE lazybase (id, name) AS (VALUES (1, 'foo'));
            SQL
        );
        $queryBuilder = self::$queryBuilder;
        $this->assertInstanceOf(
            \Policyreporter\LazyBase\Lazy\PDOStatement::class,
            $queryBuilder
                ->select('id', 'name')
                ->from('lazybase')
                ->where(
                    $queryBuilder->expr()->lt('id', 2)
                )
                ->execute()
        );
    }

    public function test_valueExtraction()
    {
        $id = 1;
        $name = 'foo';
        $this->dbHandle()->run(
            <<<SQL
            CREATE TABLE lazybase (id, name) AS (VALUES ({$id}, '{$name}'), (2, 'bar'));
            SQL
        );

        $queryBuilder = self::$queryBuilder;
        $this->assertEquals(
            $name,
            $queryBuilder
                ->select('name')
                ->from('lazybase')
                ->where(
                    $queryBuilder->expr()->eq('id', $id)
                )
                ->execute()
                ->toValue()
        );
    }

    public function test_functionMapping()
    {
        $id = 1;
        $name = 'foo';
        $this->dbHandle()->run(
            <<<SQL
            CREATE TABLE lazybase (id, name) AS (VALUES ({$id}, '{$name}'), (2, 'bar'));
            SQL
        );

        $queryBuilder = self::$queryBuilder;
        $GLOBALS['listen'] = true;
        $this->assertEquals(
            ucfirst($name),
            $queryBuilder
                ->select('name')
                ->from('lazybase')
                ->where(
                    $queryBuilder->expr()->eq('id', $id)
                )
                ->execute()
                ->map(function ($v) {
                    $v['name'] = ucfirst($v['name']);
                    return $v;
                })
                ->toValue()
        );
    }
}
