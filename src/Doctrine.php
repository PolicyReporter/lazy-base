<?php

declare(strict_types=1);

namespace Policyreporter\LazyBase;

abstract class Doctrine extends \Doctrine\DBAL\Connection
{
    private $parser;

    /**
     * Safely execute a rollBack, potentially chaining an existing exception
     *
     * @param \Exception $e The exception to be chained
     *
     * @return bool(true) Whether the rollBack was successful, according to PDO::rollBack
     *         which should always be true @see http://php.net/manual/en/pdo.rollback.php
     */
    public function rollBack(\Exception $e = null)
    {
        $rolledBack = false;
        if ($this->isTransactionActive()) {
            $rolledBack = parent::rollBack();
            if ($e !== null && $e instanceof \Exception) {
                // Yay the rollBack worked, but we still have a pending exception to throw
                throw $e;
            }
            return $rolledBack;
        }
        if (!$this->isTransactionActive()) {
            throw new \PDOException("There was no active transaction", 0, $e);
        } elseif ($e !== null) {
            throw $e;
        }
        return $rolledBack;
    }

    public function prepare(string $sql): \Doctrine\DBAL\Statement
    {
        $connection = $this->getWrappedConnection();

        try {
            $statement = $connection->prepare($sql);
        } catch (Driver\Exception $e) {
            throw $this->convertExceptionDuringQuery($e, $sql);
        }

        return new \Policyreporter\LazyBase\Doctrine\Statement($statement);
    }

    public function executeWrappedQuery(
        string $sql,
        array $params = [],
        $types = [],
        ?\Doctrine\DBAL\Cache\QueryCacheProfile $qcp = null
    ): \Policyreporter\LazyBase\Lazy\AbstractIterator {
        return $this->getNativeConnection()->run($sql, $params);
    }

    public function executeQuery(
        string $sql,
        array $params = [],
        $types = [],
        ?\Doctrine\DBAL\Cache\QueryCacheProfile $qcp = null
    ): \Doctrine\Dbal\Result {
        throw new \Exception('This method is not supported - use executeWrappedQuery()');
    }

    public function createQueryBuilder()
    {
        throw new \Exception(
            'Unable to build a QueryBuilder off the abstract class.  '
            . 'Do not use Doctrine\'s built in createQueryBuilder() function.'
        );
    }

    // Doctrine is insaely unchill - so these methods are copied out of
    // \Doctrine\DBAL\Connection just to get around obnoxious privating of methods
    protected function needsArrayParameterConversion(array $params, array $types): bool
    {
        if (is_string(key($params))) {
            return true;
        }

        foreach ($types as $type) {
            if ($type === self::PARAM_INT_ARRAY
                || $type === self::PARAM_STR_ARRAY
                || $type === self::PARAM_ASCII_STR_ARRAY
            ) {
                return true;
            }
        }

        return false;
    }

    protected function expandArrayParameters(string $sql, array $params, array $types): array
    {
        if ($this->parser === null) {
            $this->parser = $this->getDatabasePlatform()->createSQLParser();
        }

        $visitor = new \Doctrine\DBAL\ExpandArrayParameters($params, $types);

        $this->parser->parse($sql, $visitor);

        return [
            $visitor->getSQL(),
            $visitor->getParameters(),
            $visitor->getTypes(),
        ];
    }

    protected function bindTypedValues(
        \Policyreporter\LazyBase\Doctrine\Statement $stmt,
        array $params,
        array $types
    ): void {
        // Check whether parameters are positional or named. Mixing is not allowed.
        if (is_int(key($params))) {
            $bindIndex = 1;

            foreach ($params as $key => $value) {
                if (isset($types[$key])) {
                    $type                  = $types[$key];
                    [$value, $bindingType] = $this->getBindingInfo($value, $type);
                    $stmt->bindValue($bindIndex, $value, $bindingType);
                } else {
                    $stmt->bindValue($bindIndex, $value);
                }

                ++$bindIndex;
            }
        } else {
            // Named parameters
            foreach ($params as $name => $value) {
                if (isset($types[$name])) {
                    $type                  = $types[$name];
                    [$value, $bindingType] = $this->getBindingInfo($value, $type);
                    $stmt->bindValue($name, $value, $bindingType);
                } else {
                    $stmt->bindValue($name, $value);
                }
            }
        }
    }

    protected function getBindingInfo($value, $type): array
    {
        if (is_string($type)) {
            $type = Type::getType($type);
        }

        if ($type instanceof Type) {
            $value       = $type->convertToDatabaseValue($value, $this->getDatabasePlatform());
            $bindingType = $type->getBindingType();
        } else {
            $bindingType = $type ?? ParameterType::STRING;
        }

        return [$value, $bindingType];
    }
}
