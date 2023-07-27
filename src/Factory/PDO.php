<?php

// We cannot declare strict types as we're leaning on type coersion to protect our DB password in any data dumps
// declare(strict_types=1);

namespace PolicyReporter\LazyBase\Factory;

use Laminas\ServiceManager\Factory\FactoryInterface;

abstract class PDO implements FactoryInterface
{
    protected $databaseConnectionTrace;

    public static $handles = [];

    public static function removeHandle($name)
    {
        self::$handles[$name] = null;
    }

    /**
     * Mechanically construct a database handle
     *
     * @param string[] $options
     *          user => The username to use for the database connection
     *          pass => The password to use for the database connection
     *          handleName => The handle name to register with \DB (null if no registration)
     * @return PDO
     */
    protected function openHandle(
        string $className,
        array $options,
    ): \PolicyReporter\LazyBase\PDO {
        if ($options['handleName'] !== null && !empty(self::$handles[$options['handleName']])) {
            $handle = self::$handles[$options['handleName']];
        } else {
            try {
                $handle = new \PDO(
                    $options['dsn'],
                    $options['user'],
                    $options['pass']
                );
            } catch (\PDOException $e) {
                \error(\Exception\AbstractException::toString($e));
                throw new \Exception\System('No connection');
            }

            $this->databaseConnectionTrace = new \PolicyReporter\LazyBase\DatabaseConnectionTrace(
                $handle,
                $options['logger'] ?? null,
                $options['handleName'] ? $options['handleName'] : get_class($this)
            );

            // Register internally to avoid recreating on duplicate request
            if ($options['handleName'] !== null) {
                self::$handles[$options['handleName']] = $handle;
            }
        }
        return new $className(
            $handle,
            $options['isProduction'] ?? true,
            $options['debugThreshold'] ?? false,
            $options['explainString'] ?? '',
            $options['debugBar'] ?? null,
            $options['enableDebugBarEmulatedQuery'] ?? false,
            $options['enableDebugBarExplain'] ?? false,
        );
    }

    //Log connection time for tracing purposes
    public function __destruct()
    {
        if ($this->databaseConnectionTrace) {
            $this->databaseConnectionTrace->endTrace();
        }
    }
}
