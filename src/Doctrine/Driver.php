<?php

declare(strict_types=1);

namespace Policyreporter\LazyBase\Doctrine;

class Driver extends \Doctrine\DBAL\Driver\AbstractPostgreSQLDriver
{
    public function connect(array $params): \Policyreporter\LazyBase\Doctrine\Connection
    {
        if (!isset($params['pdo'])) {
            throw new \Exception('No pre-rolled connection available for Driver bake-in.');
        }
        if (!is_a($params['pdo'], \Policyreporter\LazyBase\PDO::class)) {
            throw new \Exception(
                'The pre-rolled connection does not descend from '
                    . \Policyreporter\LazyBase\PDO::class . '.'
            );
        }
        return new \Policyreporter\LazyBase\Doctrine\Connection($params['pdo']);
    }
}
