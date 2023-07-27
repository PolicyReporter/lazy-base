<?php

declare(strict_types=1);

namespace PolicyReporter\LazyBase\Factory;

require_once('lib/generalfunctions.php');

abstract class Doctrine implements \Laminas\ServiceManager\Factory\FactoryInterface
{
    protected function openHandle(
        \PolicyReporter\LazyBase\PDO $handle,
        string $wrapperClass
    ): \PolicyReporter\LazyBase\Doctrine {
        return \Doctrine\DBAL\DriverManager::getConnection(
            [
                'pdo' => $handle,
                'wrapperClass' => $wrapperClass,
                'driverClass' => \PolicyReporter\LazyBase\Doctrine\Driver::class,
            ]
        );
    }
}
