<?php

declare(strict_types=1);

namespace Policyreporter\LazyBase\Lazy;

require_once('../policyr/module/Exception/src/Exception/AbstractException.php');
require_once('../policyr/module/Exception/src/Exception/System.php');
require_once('../policyr/module/Exception/src/Exception/System/NoData.php');

/**
 * PDO statement class instantiated by PDO via ATTR_STATEMENT_CLASS.
 *
 * Do not construct with `new`; use Policyreporter\LazyBase\PDO::prepare() or run().
 */
class PDOStatement extends \PDOStatement implements \Countable
{
    use \Policyreporter\LazyBase\Deprecated;
    use IteratorBehavior;

    protected function __construct()
    {
    }

    /**
     * Lazy iteration (parent \PDOStatement is IteratorAggregate; cannot also implement Iterator).
     */
    public function getIterator(): \Generator
    {
        for ($this->rewind(); $this->valid(); $this->next()) {
            yield $this->key() => $this->current();
        }
    }

    protected static function anonymousColumnNames(): array
    {
        return ['?column?'];
    }

    public function columnCount(): int
    {
        return parent::columnCount();
    }

    protected function fetchCurrent(): array
    {
        if (false === ($nextRow = parent::fetch(\PDO::FETCH_NUM, \PDO::FETCH_ORI_NEXT))) {
            throw new \Exception\System\NoData('No valid data remains');
        }
        return $nextRow;
    }

    public function rewind(): void
    {
        if ($this->index !== 0) {
            throw new \Exception\System('Unable to rewind read-once iterator');
        }
    }

    public function bindParams(array $parameters): self
    {
        foreach ($parameters as $name => $value) {
            if ($name[0] !== ':') {
                $name = ":{$name}";
            }
            parent::bindValue($name, $value, self::getPdoType($value));
        }
        return $this;
    }

    protected function rawColumnNames(): array
    {
        $columnCount = parent::columnCount();
        $names = [];
        for ($i = 0; $i < $columnCount; $i++) {
            $names[] = $this->getColumnMeta($i)['name'];
        }
        return $names;
    }

    public function count(): int
    {
        return $this->rowCount();
    }

    /**
     * Default mode matches lazy realize(); other modes delegate to PDO.
     */
    public function fetchAll(int $mode = \PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        if ($args === [] && ($mode === \PDO::FETCH_DEFAULT || $mode === \PDO::FETCH_ASSOC)) {
            return $this->realize();
        }
        return parent::fetchAll($mode, ...$args);
    }

    public function one()
    {
        static::deprecated();
        return call_user_func_array([$this, 'toRow'], func_get_args());
    }

    public function toMap()
    {
        static::deprecated();
        return call_user_func_array([$this, 'toIndexedColumn'], func_get_args());
    }

    public function toMultiMap()
    {
        static::deprecated();
        return call_user_func_array([$this, 'toIndex'], func_get_args());
    }

    public function column()
    {
        static::deprecated();
        return call_user_func_array([$this, 'toColumn'], func_get_args());
    }

    public static function getPdoType($variable): int
    {
        $type = \gettype($variable);
        switch ($type) {
            case 'string':
                return \PDO::PARAM_STR;
            case 'boolean':
                return \PDO::PARAM_INT;
            case 'integer':
            case 'double':
            case 'float':
                return \PDO::PARAM_INT;
            case 'NULL':
                return \PDO::PARAM_NULL;
            default:
                throw new \Exception("Invalid typed variable passed to PDO of type '{$type}'");
        }
    }
}
