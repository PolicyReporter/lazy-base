<?php

declare(strict_types=1);

namespace Policyreporter\LazyBase\Doctrine;

use Doctrine\DBAL\ParameterType;

class Statement implements \Doctrine\DBAL\Driver\Statement
{
    protected const PARAM_TYPE_MAP = [
        ParameterType::NULL => \PDO::PARAM_NULL,
        ParameterType::INTEGER => \PDO::PARAM_INT,
        ParameterType::STRING => \PDO::PARAM_STR,
        ParameterType::ASCII => \PDO::PARAM_STR,
        ParameterType::BINARY => \PDO::PARAM_LOB,
        ParameterType::LARGE_OBJECT => \PDO::PARAM_LOB,
        ParameterType::BOOLEAN => \PDO::PARAM_BOOL,
    ];

    public function __construct(
        protected \Policyreporter\LazyBase\Lazy\PDOStatement $stmt,
    )
    {
    }

    public function statement(): \Policyreporter\LazyBase\Lazy\PDOStatement
    {
        return $this->stmt;
    }

    public function bindValue($param, $value, $type = ParameterType::STRING)
    {
        $type = $this->convertParamType($type);

        return $this->stmt->bindValue($param, $value, $type);
    }

    public function bindParam(
        $param,
        &$variable,
        $type = ParameterType::STRING,
        $length = null,
        $driverOptions = null
    ): bool
    {
        if (func_num_args() > 4) {
            \Doctrine\Deprecations\Deprecation::triggerIfCalledFromOutside(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/issues/4533',
                'The $driverOptions argument of Statement::bindParam() is deprecated.'
            );
        }

        $type = $this->convertParamType($type);

        return $this->stmt->bindParam(
            $param,
            $variable,
            $type,
            $length ?? 0,
            ...array_slice(func_get_args(), 4)
        );
    }

    public function execute($params = null): \Policyreporter\LazyBase\Doctrine\Result
    {
        $this->stmt->execute($params);
        return new \Policyreporter\LazyBase\Doctrine\Result($this->stmt);
    }

    private function convertParamType(int $type): int
    {
        if (! isset(self::PARAM_TYPE_MAP[$type])) {
            throw \Doctrine\DBAL\Driver\Exception\UnknownParameterType::new($type);
        }

        return self::PARAM_TYPE_MAP[$type];
    }
}
