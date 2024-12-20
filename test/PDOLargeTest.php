<?php

declare(strict_types=1);

namespace Policyreporter\LazyBase;

require_once('test/TestCase.php');

/**
 * @large
 */
class PDOLargeTest extends \Policyreporter\LazyBase\TestCase
{
    public function tearDown(): void
    {
        $this->dbHandle()->run(
            <<<SQL
            DROP TABLE IF EXISTS lazybase
            SQL
        );
    }

    public function testRunInsertInBatches()
    {
        PDO::setBatchSize(1);

        $this->dbHandle()->run('CREATE TABLE lazybase (column1 INTEGER, column2 INTEGER)');

        $assertTestTableContents = function (array ...$expected): void {
            $this->assertEquals(
                $expected,
                $this->dbHandle()->run("SELECT * FROM lazybase ORDER BY column1")->fetchAll()
            );
        };

        $assertTestTableContents();

        $this->dbHandle()->beginTransaction();
        $this->dbHandle()->runInsertInBatches(
            "INSERT INTO lazybase (column1, column2) VALUES ",
            [[1, 2], [3, 4], [5, 6]]
        );
        $this->dbHandle()->commit();

        $assertTestTableContents(
            ['column1' => 1, 'column2' => 2],
            ['column1' => 3, 'column2' => 4],
            ['column1' => 5, 'column2' => 6]
        );

        PDO::setBatchSize(5);

        $this->dbHandle()->beginTransaction();
        $this->dbHandle()->runInsertInBatches(
            "INSERT INTO lazybase (column1, column2) VALUES ",
            [[7, 8], [9, 10]]
        );
        $this->dbHandle()->commit();

        $assertTestTableContents(
            ['column1' => 1, 'column2' => 2],
            ['column1' => 3, 'column2' => 4],
            ['column1' => 5, 'column2' => 6],
            ['column1' => 7, 'column2' => 8],
            ['column1' => 9, 'column2' => 10]
        );
    }

    public function testRunInsertInBatchesQueryAfterValues()
    {
        $this->dbHandle()->run(
            <<<SQL
            CREATE TABLE lazybase(
                id SERIAL NOT NULL PRIMARY KEY,
                column1 INTEGER,
                column2 INTEGER)
            SQL
        );

        $this->dbHandle()->run(
            <<<SQL
            CREATE UNIQUE INDEX column1and2idx
                ON lazybase
                USING BTREE
                (column1, column2)
            SQL
        );

        $this->dbHandle()->beginTransaction();
        $this->dbHandle()->runInsertInBatches(
            "INSERT INTO lazybase (column1, column2) VALUES ",
            [
                ['column1' => 1, 'column2' => 2],
                ['column1' => 3, 'column2' => 4],
                ['column1' => 1, 'column2' => 2],
                ['column1' => 5, 'column2' => 6]
            ],
            "ON CONFLICT (column1, column2) DO NOTHING"
        );
        $this->dbHandle()->commit();

        $this->assertEquals(
            [1, 2, 4],
            $this->dbHandle()->run("SELECT id FROM lazybase")->toColumn()
        );
    }
}
