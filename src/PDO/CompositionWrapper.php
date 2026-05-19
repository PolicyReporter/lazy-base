<?php

declare(strict_types=1);

namespace Policyreporter\LazyBase\PDO;

/**
 * This class exists to serve as a dummy ancestor for classes that must extend
 * \PDO but prefer to compose in a handle
 */
abstract class CompositionWrapper extends \PDO
{
    protected $handle;

    public function __construct(\PDO $handle)
    {
        $this->handle = $handle;
    }

    public function getPdo(): \PDO
    {
        return $this->handle;
    }

    protected function redirectToHandle($functionName, ...$arguments)
    {
        return $this->handle->$functionName(...$arguments);
    }

    public function beginTransaction(): bool
    {
        return $this->redirectToHandle(__FUNCTION__, ...func_get_args());
    }
    public function commit(): bool
    {
        return $this->redirectToHandle(__FUNCTION__, ...func_get_args());
    }
    public function errorCode(): ?string
    {
        return $this->redirectToHandle(__FUNCTION__, ...func_get_args());
    }
    public function errorInfo(): array
    {
        return $this->redirectToHandle(__FUNCTION__, ...func_get_args());
    }
    public function exec(string $statement): int|false
    {
        return $this->redirectToHandle(__FUNCTION__, ...func_get_args());
    }
    public function getAttribute(int $attribute): mixed
    {
        return $this->redirectToHandle(__FUNCTION__, ...func_get_args());
    }
    public static function getAvailableDrivers(): array
    {
        return parent::getAvailableDrivers();
    }
    public function inTransaction(): bool
    {
        return $this->redirectToHandle(__FUNCTION__, ...func_get_args());
    }
    public function lastInsertId(?string $name = null): string|false
    {
        return $this->redirectToHandle(__FUNCTION__, ...func_get_args());
    }
    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        return $this->redirectToHandle(__FUNCTION__, ...func_get_args());
    }
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): \PDOStatement|false
    {
        return $this->redirectToHandle(__FUNCTION__, ...func_get_args());
    }
    public function quote(string $string, int $type = \PDO::PARAM_STR): string|false
    {
        return $this->redirectToHandle(__FUNCTION__, ...func_get_args());
    }
    public function rollBack(): bool
    {
        return $this->redirectToHandle(__FUNCTION__, ...func_get_args());
    }
    public function rowCount()
    {
        return $this->redirectToHandle(__FUNCTION__, ...func_get_args());
    }
    public function setAttribute(int $attribute, mixed $value): bool
    {
        return $this->redirectToHandle(__FUNCTION__, ...func_get_args());
    }
}
