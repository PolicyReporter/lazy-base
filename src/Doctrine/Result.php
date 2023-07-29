<?php

declare(strict_types=1);

namespace Policyreporter\LazyBase\Doctrine;

class Result implements \Doctrine\DBAL\Driver\Result
{
    private $statement;

    public function __construct(\Policyreporter\LazyBase\Lazy\PDOStatement $statement)
    {
        $this->statement = $statement;
    }

    public function statement(): \Policyreporter\LazyBase\Lazy\PDOStatement
    {
        return $this->statement;
    }

    public function __call(string $name, array $args)
    {
        $this->statement->$name(...$args);
    }

    public function fetchNumeric()
    {
        return $this->fetch(\PDO::FETCH_NUM);
    }

    public function fetchAssociative()
    {
        return $this->fetch(\PDO::FETCH_ASSOC);
    }

    public function fetchOne()
    {
        return $this->fetch(\PDO::FETCH_COLUMN);
    }

    public function fetchAllNumeric(): array
    {
        return $this->fetchAll(\PDO::FETCH_NUM);
    }

    public function fetchAllAssociative(): array
    {
        return $this->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function fetchFirstColumn(): array
    {
        return $this->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function rowCount(): int
    {
        return $this->statement->rowCount();
    }

    public function columnCount(): int
    {
        return $this->statement->columnCount();
    }

    public function free(): void
    {
        $this->statement->closeCursor();
    }

    private function fetch(int $mode)
    {
        return $this->statement->fetch($mode);
    }

    private function fetchAll(int $mode): array
    {
        $data = $this->statement->fetchAll($mode);

        assert(is_array($data));

        return $data;
    }
}
