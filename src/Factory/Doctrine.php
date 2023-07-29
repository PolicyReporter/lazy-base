<?php

declare(strict_types=1);

namespace PolicyReporter\LazyBase\Factory;

require_once('lib/generalfunctions.php');

abstract class Doctrine implements \Laminas\ServiceManager\Factory\FactoryInterface
{
    protected function openHandle(
        \Policyreporter\LazyBase\PDO $handle,
        string $wrapperClass
    ): \Policyreporter\LazyBase\Doctrine {
        return \Doctrine\DBAL\DriverManager::getConnection(
            [
                'pdo' => $handle,
                'wrapperClass' => $wrapperClass,
                'driverClass' => \Policyreporter\LazyBase\Doctrine\Driver::class,
            ]
        );
    }
}
