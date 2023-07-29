<?php

declare(strict_types=1);

namespace PolicyReporter\LazyBase\Lazy;

/**
 * This is an inner class for PDO only, do not use directly
 *
 * It's important to note that due to the way PDO works, any additional
 * functions designed here will be available on all PDOStatement return
 * objects generated from a registered handle, even if the run() method
 * is not used to generate them.  This is a good place for any such utility
 * methods, and this class can be extended to support relevant interfaces
 * as desired (i.e. Countable or ArrayAccess) I've refrained from such so
 * far for simplicities sake and since they go against desired usage, but
 * such extensions are available
 */
class PDOStatement extends AbstractIterator
{
    use \Policyreporter\LazyBase\Deprecated;

    protected $statement;

    /**
     * Standard PDOStatement constructor wrapper to capture
     * the live database handle
     */
    public function __construct($statement)
    {
        $this->statement = $statement;
    }

    /**
     * Pass through any method calls we haven't overridden since we're not longer extending \PDOStatement
     *
     * @param string $name The name of the function
     * @param mixed[] $arguments THe arguments to the function
     */
    public function __call(string $name, array $arguments)
    {
        return $this->statement->$name(...$arguments);
    }

    /**
     * Provide a list of columns that we allow to be duplicated
     *
     * @return string[] The columns so allowed
     */
    protected static function anonymousColumnNames(): array
    {
        return ['?column?'];
    }

    /**
     * Garner a count of how many columns exist in the result set
     *
     * Since this data is tracked by \PDOStatement already, we'll just use their value
     *
     * @return int The number of columns
     */
    public function columnCount(): int
    {
        return $this->statement->columnCount();
    }

    /**
     * Return the next result row from our query iterator, if possible
     *
     * @throws \Exception If no data remains to be viewed
     * @return mixed[] The next raw result row
     */
    protected function fetchCurrent(): array
    {
        if (false === ($nextRow = $this->statement->fetch(\PDO::FETCH_NUM, \PDO::FETCH_ORI_NEXT))) {
            throw new \Exception\System\NoData('No valid data remains');
        }
        return $nextRow;
    }

    /**
     * Throws an exception if not called while sitting on element 0
     *
     * @throws System\Exception this class of iterator cannot be rewound
     */
    public function rewind(): void
    {
        if ($this->index !== 0) {
            throw new \Exception\System('Unable to rewind read-once iterator');
        }
    }

    /**
     * Bind several parameters to this statment, this function suppresses any
     * errors relating to binding unmatched parameters (overbinding), infers
     * SQL type from PHP literal type (1 !== "1") and will append a colon to
     * any replacements missing it
     *
     * @param mixed[] $parameters The parameters to bind
     * @return this
     */
    public function bindParams(array $parameters): self
    {
        foreach ($parameters as $name => $value) {
            if ($name[0] !== ':') {
                $name = ":{$name}";
            }
            $this->statement->bindValue($name, $value, self::getPdoType($value));
        }
        return $this;
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

    /**
     * Return a list of column names contained in the result set
     *
     * @return string[] The column names
     */
    public function rawColumnNames(): array
    {
        $columnCount = $this->columnCount();
        $names = [];
        for ($i = 0; $i < $columnCount; $i++) {
            $names[] = $this->getColumnMeta($i)['name'];
        }
        return $names;
    }

    /**
     * Return the rowCount as a \Countable interface
     *
     * @return int The count of rows
     */
    public function count(): int
    {
        return $this->rowCount();
    }

    /**
     * Get the PDO type constant for a given value
     *
     * @throws Exception if the type has no PDO equivalent
     * or if we do not support binding that type
     *
     * @param mixed $variable The variable to inspect
     * @return PDO::PARAM_* constant
     */
    public static function getPdoType($variable): int
    {
        $type = \gettype($variable);
        switch ($type) {
            case 'string':
                return \PDO::PARAM_STR;
            case 'boolean':
                // MySQL doesn't have boolean types, but other dbs do support `true` and `false`
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
