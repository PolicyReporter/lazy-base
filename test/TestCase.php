<?php

declare(strict_types=1);

namespace Policyreporter\LazyBase;

require_once('test/Constraint/Throws.php');
require_once('vendor/autoload.php');

class TestCase extends \PHPUnit\Framework\TestCase
{
    private static $ini = null;
    private static $pgPassFile = null;
    private static $handle = null;
    private static $isClean = false;

    /**
     * Test if a callable throws an exception.  This will fail if the callable throws a different
     * exception or if the callable executes without throwing an exception
     *
     * @param string $target The exception type to test against
     * @param callable|ReflectionMethod $response The function to test
     * @param array $args Arguments for the function (optional)
     * @param string $errorRegex A regex to match the error message against (or null to skip) (optional)
     * @param string $message A message to print (optional)
     * @return void
     */
    public static function assertThrows(string $target, $response, array $args = [], string $errorRegex = null, string $message = ''): void
    {
        $err = null;
        if ($response instanceof \ReflectionMethod) {
            try {
                $response->invoke(...$args);
            } catch (\Throwable $err) {
            }
        } elseif (is_callable($response)) {
            try {
                call_user_func_array($response, $args);
            } catch (\Throwable $err) {
            }
        } else {
            throw new \TypeError(
                'Argument 2 passed into ' . __CLASS__ . '\\' . __FUNCTION__ . ' must be a callable or ReflectionMethod'
            );
        }

        self::assertThat(
            [$response, $args, $err, $errorRegex],
            new \Policyreporter\LazyBase\Constraint\Throws($target),
            $message
        );
    }

    /**
     * @param object $object on which to invoke the method
     * @param string $methodName to be invoked
     * @param bool $makeAccessible true if the method should be "made" accessible
     * @return mixed The invokable method
     */
    protected static function getInvokableMethod($object, string $methodName, bool $makeAccessible = true)
    {
        $className = $object;
        if (!is_string($object)) {
            $className = \get_class($className);
        }
        $method = new \ReflectionMethod($className, $methodName);
        if ($makeAccessible) {
            $method->setAccessible($makeAccessible);
        }
        if ($method->isStatic()) {
            return $method->getClosure();
        } else {
            if (is_string($object)) {
                throw new \Exception("Attempt to invoke instance method '{$methodName}' in a static context, a class instance is required instead of the name of the class");
            }
            return $method->getClosure($object);
        }
    }

    private static function parseDsn(string $dsn): array
    {
        $hash = ['host' => null, 'port' => null, 'dbname' => null];
        $dsn = preg_split("/[;:]/", $dsn);
        foreach ($dsn as $param) {
            $parts = explode("=", $param);
            if (in_array($parts[0], array_keys($hash))) {
                $hash[$parts[0]] = $parts[1];
            }
        }
        foreach (array_values($hash) as $val) {
            if ($val === null) {
                throw new \Exception("Could not parse DSN");
            }
        }
        return $hash;
    }

    public static function setUpBeforeClass(): void
    {
        if (!self::$isClean) {
            self::cleanDb();
            self::$isClean = true;
        }
    }

    public static function cleanDb(): void
    {
        $mainDb = 'policyr';
        $dsn = self::parseDsn(self::iniFile()['database']['dsn']);
        $pgPassFile = self::pgPassFile();
        exec(
            <<<SH
            echo "DROP DATABASE IF EXISTS {$dsn['dbname']};" | PGPASSFILE={$pgPassFile} psql -Upostgres -dpolicyr -h{$dsn['host']} -v"ON_ERROR_STOP=1"
            SH
        );
        exec(
            <<<SH
            echo "CREATE DATABASE {$dsn['dbname']} OWNER postgres;" | PGPASSFILE={$pgPassFile} psql -Upostgres -dpolicyr -h{$dsn['host']} -v"ON_ERROR_STOP=1"
            SH
        );
    }

    /**
     * Currently hardcoded to borrow the ini file from another project
     */
    private static function iniFile(): array
    {
        if (self::$ini === null) {
            self::$ini = \parse_ini_file('../policyr/config.test.ini.php', true);
        }
        return self::$ini;
    }

    private static function pgPassFile(): string
    {
        if (self::$pgPassFile === null) {
            self::$pgPassFile = \posix_getpwuid(\posix_geteuid())['dir'] . "/.pgpass";
        }
        return self::$pgPassFile;
    }

    public function dbHandle(): PDO
    {
        if (self::$handle === null) {
            $ini = self::iniFile();
            self::$handle = new PDO(
                new \PDO(
                    $ini['database']['dsn'],
                    $ini['database']['user'],
                    $ini['database']['pass'],
                )
            );
        }
        return self::$handle;
    }
}
