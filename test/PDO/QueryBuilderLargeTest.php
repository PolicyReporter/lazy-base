<?php

declare(strict_types=1);

namespace Policyreporter\LazyBase;

require_once('test/TestCase.php');
require_once('test/Doctrine.php');

/**
 * @large
 */
class QueryBuilderLargeTest extends \Policyreporter\LazyBase\TestCase
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
            DROP TABLE IF EXISTS lazybase;
            SQL
        );
        $this->dbHandle()->run(
            <<<SQL
            DROP TABLE IF EXISTS lazybasea;
            SQL
        );
        $this->dbHandle()->run(
            <<<SQL
            DROP TABLE IF EXISTS lazybaseb;
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
            Lazy\PDOStatement::class,
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

    public function test_joinStatement()
    {
        $id = 1;
        $name = 'foo';
        $this->dbHandle()->run(
            <<<SQL
            CREATE TABLE lazybase (id, name) AS (VALUES ({$id}, '{$name}'), (2, 'bar'));
            SQL
        );
        $this->dbHandle()->run(
            <<<SQL
            CREATE TABLE lazybasea (id, name) AS (VALUES ({$id}, '{$name}'));
            SQL
        );
        $this->dbHandle()->run(
            <<<SQL
            CREATE TABLE lazybaseb (id, name) AS (VALUES (2, 'bop'));
            SQL
        );

        $queryBuilder = self::$queryBuilder;

        $result = $queryBuilder->select(
            'l.name AS lname',
            'a.name AS aname',
            'b.name AS bname'
        )->from(
            'lazybase',
            'l'
        )->join(
            'l',
            'lazybasea',
            'a',
            'l.id = a.id'
        )->leftJoin(
            'l',
            'lazybaseb',
            'b',
            'l.id = b.id'
        )->where(
            'l.id = :id'
        )->setParameter(
            'id',
            $id
        )->execute();

        $this->assertEquals(
            [
                [
                    'lname' => $name,
                    'aname' => $name,
                    'bname' => null,
                ],
                [
                    'lname' => $name,
                    'aname' => $name,
                    'bname' => null,
                ]
            ],
            $result->realize()
        );
    }
}
