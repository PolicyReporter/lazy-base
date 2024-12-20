<?php

declare(strict_types=1);

namespace Policyreporter\LazyBase\PDO;

/**
 * This class exists to serve as a dummy ancestor for classes that must extend
 * \PDO but prefer to compose in a handle
 */
abstract class CompositionWrapper extends \PDO
{
    public function __construct(
        protected \PDO $handle,
    )
    {
    }

    public function getPdo(): \PDO
    {
        return $this->handle;
    }

    protected function redirectToHandle($functionName, ...$arguments)
    {
        return $this->handle->$functionName(...$arguments);
    }

    public function beginTransaction()
    {
        return $this->redirectToHandle(__FUNCTION__, ...func_get_args());
    }
    public function commit()
    {
        return $this->redirectToHandle(__FUNCTION__, ...func_get_args());
    }
    public function errorCode()
    {
        return $this->redirectToHandle(__FUNCTION__, ...func_get_args());
    }
    public function errorInfo()
    {
        return $this->redirectToHandle(__FUNCTION__, ...func_get_args());
    }
    public function exec($statement)
    {
        return $this->redirectToHandle(__FUNCTION__, ...func_get_args());
    }
    public function getAttribute($attribute)
    {
        return $this->redirectToHandle(__FUNCTION__, ...func_get_args());
    }
    public static function getAvailableDrivers()
    {
        return $this->redirectToHandle(__FUNCTION__, ...func_get_args());
    }
    public function inTransaction()
    {
        return $this->redirectToHandle(__FUNCTION__, ...func_get_args());
    }
    public function lastInsertId($name = null)
    {
        return $this->redirectToHandle(__FUNCTION__, ...func_get_args());
    }
    public function prepare(string $query, $options = [])
    {
        return $this->redirectToHandle(__FUNCTION__, ...func_get_args());
    }
    public function query(string $query, ?int $fetchMode = null, ...$fetchModeArgs)
    {
        return $this->redirectToHandle(__FUNCTION__, ...func_get_args());
    }
    public function quote($string, $type = \PDO::PARAM_STR)
    {
        return $this->redirectToHandle(__FUNCTION__, ...func_get_args());
    }
    public function rollBack()
    {
        return $this->redirectToHandle(__FUNCTION__, ...func_get_args());
    }
    public function rowCount()
    {
        return $this->redirectToHandle(__FUNCTION__, ...func_get_args());
    }
    public function setAttribute($attribute, $value)
    {
        return $this->redirectToHandle(__FUNCTION__, ...func_get_args());
    }
}
