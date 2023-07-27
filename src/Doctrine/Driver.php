<?php

declare(strict_types=1);

namespace PolicyReporter\LazyBase\Doctrine;

class Driver extends \Doctrine\DBAL\Driver\AbstractPostgreSQLDriver
{
    public function connect(array $params): \PolicyReporter\LazyBase\Doctrine\Connection
    {
        if (!isset($params['pdo'])) {
            throw new \Exception('No pre-rolled connection available for Driver bake-in.');
        }
        if (!is_a($params['pdo'], \PolicyReporter\LazyBase\PDO::class)) {
            throw new \Exception('The pre-rolled connection does not descend from ' . \PolicyReporter\LazyBase\PDO::class . '.');
        }
        return new \PolicyReporter\LazyBase\Doctrine\Connection($params['pdo']);
    }
}
