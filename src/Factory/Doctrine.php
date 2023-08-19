<?php

declare(strict_types=1);

namespace Policyreporter\LazyBase\Factory;

class Doctrine implements \Laminas\ServiceManager\Factory\FactoryInterface
{
    public function openHandle(
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

    public function __invoke(
        \Interop\Container\ContainerInterface $container,
        $requestedName,
        ?array $options = null
    ) { }
}
