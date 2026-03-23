<?php

declare(strict_types=1);

namespace Policyreporter\LazyBase\Doctrine;

class Connection implements \Doctrine\DBAL\Driver\ServerInfoAwareConnection
{
    private $connection;

    public function __construct(\PDO $connection)
    {
        $connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->connection = $connection;
    }

    public function exec(string $sql): int
    {
        $result = $this->connection->exec($sql);

        assert($result !== false);

        return $result;
    }

    public function getServerVersion()
    {
        return $this->connection->getAttribute(\PDO::ATTR_SERVER_VERSION);
    }

    public function prepare(string $sql): \Doctrine\DBAL\Driver\Statement
    {
        $stmt = $this->connection->prepare($sql);
        return new \Policyreporter\LazyBase\Doctrine\Statement($stmt);
    }

    public function query(string $sql): \Policyreporter\LazyBase\Doctrine\Result
    {
        $stmt = $this->connection->query($sql);
        return new \Policyreporter\LazyBase\Doctrine\Result($stmt);
    }

    public function quote($value, $type = \Doctrine\DBAL\ParameterType::STRING)
    {
        return $this->connection->quote($value, $type);
    }

    public function lastInsertId($name = null)
    {
        if ($name === null) {
            return $this->connection->lastInsertId();
        }

        \Doctrine\Deprecations\Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/issues/4687',
            'The usage of Connection::lastInsertId() with a sequence name is deprecated.'
        );

        return $this->connection->lastInsertId($name);
    }

    public function beginTransaction(): bool
    {
        return $this->connection->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->connection->commit();
    }

    public function rollBack(): bool
    {
        return $this->connection->rollBack();
    }

    public function getNativeConnection(): \PDO
    {
        return $this->connection;
    }

    /**
     * @deprecated Call {@see getNativeConnection()} instead.
     */
    public function getWrappedConnection(): \PDO
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/5037',
            '%s is deprecated, call getNativeConnection() instead.',
            __METHOD__
        );

        return $this->getNativeConnection();
    }

    public function executeQuery(
        string $sql,
        array $params = [],
        $types = [],
        ?\Doctrine\DBAL\Cache\QueryCacheProfile $qcp = null
    ): \Policyreporter\LazyBase\Doctrine\Result {
        if ($qcp !== null) {
            return $this->executeCacheQuery($sql, $params, $types, $qcp);
        }

        $connection = $this->getWrappedConnection();

        $logger = $this->_config->getSQLLogger();
        if ($logger !== null) {
            $logger->startQuery($sql, $params, $types);
        }

        try {
            if (count($params) > 0) {
                if ($this->needsArrayParameterConversion($params, $types)) {
                    [$sql, $params, $types] = $this->expandArrayParameters($sql, $params, $types);
                }

                $stmt = $connection->prepare($sql);
                if (count($types) > 0) {
                    $this->_bindTypedValues($stmt, $params, $types);
                    $result = $stmt->execute();
                } else {
                    $result = $stmt->execute($params);
                }
            } else {
                $result = $connection->query($sql);
            }

            return new \Policyreporter\LazyBase\Doctrine\Result($result, $this);
        } catch (\Doctrine\Dbal\Driver\Exception $e) {
            throw $this->convertExceptionDuringQuery($e, $sql, $params, $types);
        } finally {
            if ($logger !== null) {
                $logger->stopQuery();
            }
        }
    }
}
