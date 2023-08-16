<?php

declare(strict_types=1);

namespace Policyreporter\LazyBase\Lazy;

abstract class AbstractIterator implements \Iterator, \Countable
{
    protected $currentResult = null;
    protected $index = 0;
    protected $transformations = [];
    /** @var string[]|null The list of distinct column names to reduce recompution efforts */
    protected $columnNames = null;

    /**
     * Queue a function to be applied
     *
     * When these lazy values are materialized either iteratively or in bulk
     * functions registered here will be executed
     *
     * @param callable $function The function to queue
     * @return $this
     */
    public function map(callable $function): self
    {
        $this->transformations[] = $function;
        return $this;
    }

    // Common mapped functionalities for convenience

    /**
     * Queue a json decoding of the specified columns
     *
     * @param string ...$columns The columns to be decoded
     * @return $this
     */
    public function mapJsonDecode(string ...$columns): self
    {
        return $this->map(function ($row) use ($columns) {
            foreach ($columns as $column) {
                $row[$column] = $this->safeJsonDecode($row[$column]);
            }
            return $row;
        });
    }

    private function safeJsonDecode($str)
    {
        $str = (string)$str;
        // Empty string is not valid JSON
        // http://stackoverflow.com/questions/30621802/why-does-json-parse-fail-with-the-empty-string
        if ($str === '') {
            throw new \Exception('No input');
        }

        if ($str === null || \strtolower($str) === 'null') {
            return null;
        }

        $ret = \json_decode($str, true);

        // A return value of NULL can indicate a decode error, but it can also be a valid output
        if ($ret === null && \json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception(\json_last_error_msg());
        }
        return $ret;
    }

    /**
     * Apply map function to the result set
     *
     * @return self
     */
    public function mapIndexedColumn(): self
    {
        if ($this->columnCount() !== 2) {
            // If there aren't two columns, error, even if the result set is empty
            throw new \Exception(
                'Invalid query result to channel into a map, the query must return exactly two columns'
            );
        }
        return $this->map(function ($row, $index) {
            return [$this->last($row), $this->first($row)];
        });
    }

    /**
     * Apply column function to the result set
     *
     * @return self
     */
    public function mapColumn(): self
    {
        if ($this->columnCount() > 1) {
            throw new \Exception('Multiple columns returned, exactly one column must be in result set');
        } elseif ($this->columnCount() === 0) {
            throw new \Exception('No columns returned in result, exactly one column must be in result set');
        } elseif ($this->columnCount() !== 1) {
            throw new \Exception('Bad row returned');
        }
        return $this->map(function ($row) {
            return $this->first($row);
        });
    }

    /**
     * Grab the first element off of a list
     *
     * This function is mostly identical to reset, but it won't require a referentiable
     * list and nor will it alter the index of that list, though if dealing with an
     * iterator, it will access the first element, which may consume it
     *
     * @param mixed[]|Traversable $a The list of interest
     * @return mixed The first value of that list
     */
    private function first($a)
    {
        if (is_array($a) || $a instanceof \Traversable) {
            foreach ($a as $b) {
                return $b;
            }
            throw new \Exception("No element found");
        } else {
            $type = gettype($a);
            $type = $type === 'object' ? get_class($a) : $type;
            throw new \Exception("Invalid object passed to first of type '{$type}'");
        }
    }

    /**
     * Grab the last element off of a list
     *
     * This function is mostly identical to reset(array_reverse), but it won't require
     * a referentiable list and nor will it alter the index of that list, though if
     * dealing with an iterator, it will crawl the array to the last element, which may consume it
     *
     * @param mixed[]|Traversable $a The list of interest
     * @return mixed The last value of that list
     */
    function last($a)
    {
        if (is_array($a)) {
            if (count($a)) {
                return array_pop($a);
            } else {
                throw new \Exception("No element found.");
            }
        } elseif ($a instanceof \Traversable) {
            $isEmptyList = true;
            foreach ($a as $b) {
                $isEmptyList = false;
            }
            if ($isEmptyList) {
                throw new \Exception("No element found");
            }
            return $b;
        } else {
            $type = gettype($a);
            $type = $type === 'object' ? get_class($a) : $type;
            throw new \Exception("Invalid object passed to first of type '{$type}'");
        }
    }

    /**
     * Apply multiMap function to the result set
     *
     * @return self
     */
    public function mapIndex(): self
    {
        if ($this->columnCount() < 2) {
            // If there aren't two columns, error, even if the result set is empty
            throw new \Exception(
                'Invalid query result to channel into a map. The query must return at least 2 columns'
            );
        }
        return $this->map(function ($row, $index) {
            $index = array_shift($row);
            return [$row, $index];
        });
    }

    /**
     * Apply non-consuming multiMap function to the result set
     *
     * For instance:
     *      $this->dbRo()->run('SELECT id, name FROM states')->mapReindex()->fetchAll();
     * will result in
     *      [
     *          'AK' => ['id' => 'AK', 'name' => 'Alaska'],
     *          'AL' => ['id' => 'AL', 'name' => 'Alabama'],
     *          ...
     *      ]
     *
     * @return self
     */
    public function mapReindex(): self
    {
        if ($this->columnCount() < 2) {
            // If there aren't two columns, error, even if the result set is empty
            throw new \Exception(
                'Invalid query result to channel into a map. The query must return at least 2 columns'
            );
        }
        return $this->map(function ($row, $index) {
            $index = $this->first($row);
            return [$row, $index];
        });
    }

    /**
     * Returns a list of any anonymous column names
     *
     * Normally column names are expected to be distinct, but this allows
     * a whitelist of column names to not expect distinctness on
     *
     * @return string[] The columns so allowed
     */
    protected static function anonymousColumnNames(): array
    {
        return [];
    }

    /**
     * This function will compute the correct list of distinct column names
     *
     * @throws \Exception If the columns can't be cleanly disambiguiated
     * @return string[] The list of column names
     */
    public function columnNames()
    {
        if ($this->columnNames === null) {
            $rawColumnNames = $this->rawColumnNames();
            $nonAnonymusColumnNames = array_diff($rawColumnNames, static::anonymousColumnNames());
            if (count($nonAnonymusColumnNames) !== count(array_unique($nonAnonymusColumnNames))) {
                throw new \Exception(
                    'The query resulted in two non-anonymous columns with the same alias ' .
                    'and can not be represented as an assoc. array'
                );
            }
            if (count($rawColumnNames) !== count(array_unique($rawColumnNames))) {
                $i = 0;
                $rawColumnNames = array_map(function ($v) use (&$i) {
                    if (in_array($v, static::anonymousColumnNames())) {
                        $v .= $i++;
                    }
                    return $v;
                }, $rawColumnNames);
            }
            $this->columnNames = $rawColumnNames;
        }
        return $this->columnNames;
    }

    /**
     * Garner a count of how many columns exist in the result set
     *
     * @return int The number of columns
     */
    public function columnCount(): int
    {
        return count($this->columnNames());
    }

    /**
     * Return the next result row from our internal iterator, if possible
     *
     * @throws \Exception if there is no row to return
     * @return mixed[] The next raw result row
     */
    abstract protected function fetchCurrent(): array;

    public function fetchAll()
    {
        return $this->realize();
    }

    // BEGIN ITERABLE HANDLES

    /**
     * Fetch the current result, do not modify the internal pointer
     *
     * @throws \Exception if we've iterated beyond end
     * @return mixed
     */
    public function current()
    {
        if ($this->currentResult === null) {
            // Some iterators may need to peek at a result to grab these,
            // make sure we invoke this prior to fetching the row
            $columnNames = $this->columnNames();
            $current = $this->fetchCurrent();
            if (count($columnNames) !== count($current)) {
                throw new \Exception\System(
                    'Inconsistent column count in iterator source',
                    0,
                    null,
                    ['columnCount' => count($columnNames), 'rowCount' => count($current)]
                );
            }
            $this->currentResult = \array_combine($columnNames, $current);
            foreach ($this->transformations as $func) {
                if (\argument_count($func) === 1) {
                    $this->currentResult = $func($this->currentResult);
                } else {
                    [$this->currentResult, $this->index] = $func($this->currentResult, $this->index);
                }
            }
        }
        return $this->currentResult;
    }

    /**
     * Fetch the key of the current result, do not modify the internal pointer
     *
     * @return mixed
     */
    public function key()
    {
        return $this->index;
    }

    /**
     * Advance the iterator to the next result
     *
     * @return void
     */
    public function next(): void
    {
        $this->currentResult = null;
        $this->index++;
    }

    /**
     * Rewind the result to run through the rows again (called prior to initial iteration)
     *
     * @throws System\Exception if the iterator cannot be rewound and isn't at the beginning
     * @return void
     */
    abstract public function rewind(): void;

    /**
     * Check whether this iterator still has items to consume
     *
     * @return bool
     */
    public function valid(): bool
    {
        try {
            $this->current();
        } catch (\Exception\System\NoData $e) {
            return false;
        }
        return true;
    }

    // END ITERABLE HANDLES

    /**
     * Immediately wind through the remainder of the data set
     *
     * @return mixed[]
     */
    public function realize()
    {
        return iterator_to_array($this);
    }

    /**
     * Return a single query result row
     *
     * This function guarantees a non-empty response array
     * (or error), if the query could return an empty set under valid
     * conditions, please use find
     *
     * @return mixed[] The first row of the query fetched by this function
     *
     * @throws Exception If there is not exactly one row in the result set
     */
    public function toRow()
    {
        if ($this->count() > 1) {
            throw new \Exception('Too many rows returned, exactly one row must be returned');
        } elseif ($this->count() === 0) {
            throw new \Exception('No rows returned, exactly one row must be returned');
        } elseif ($this->count() !== 1) {
            throw new \Exception('Bad result set');
        }
        return $this->first($this);
    }

    /**
     * Return a singleton query result element
     *
     * This function requires that the query being called will return a single
     * column, in a single row, it will then return the value contained in that
     * column.
     *
     * @return mixed The value contained in the column fetched by this function
     *
     * @throws Exception If there is not exactly one column in the precisely one row in the result set
     */
    public function toValue()
    {
        if ($this->columnCount() > 1) {
            throw new \Exception('Multiple columns returned, exactly one column must be in result set');
        } elseif ($this->columnCount() === 0) {
            throw new \Exception('No columns returned in result, exactly one column must be in result set');
        } elseif ($this->columnCount() !== 1) {
            throw new \Exception('Bad row returned');
        }
        // Pop off the only column
        return $this->first($this->toRow());
    }

    /**
     * Return one column from the dataset indexed by another column
     *
     * This function creates a associative of the first column in the result
     * serving as an index to the second column, it will error if there are
     * more or less than two columns
     *
     * @return mixed[] The indexed column of data
     */
    public function toIndexedColumn()
    {
        if (!$this->hasRows()) {
            // If we have no rows we have no result to generate a proper
            // array_column call, the result we'd want then is `[]` anyways
            return [];
        }
        if ($this->columnCount() !== 2) {
            // If there aren't two columns, error, even if the result set is empty
            throw new \Exception(
                'Invalid query result to channel into a hash, the query must return exactly two columns'
            );
        }
        // Buffer full data set
        $data = $this->realize();
        // Grab the column names of the set, and swap them so that we read
        // columns as `key, value`, reverse of what `array_column` expects
        $columnNames = array_reverse(array_keys($data[0]));
        // Call `array_column` with the splatted out column names
        // (on syntax, see http://php.net/manual/en/migration56.new-features.php#migration56.new-features.splat )
        return array_column($data, ...$columnNames);
    }

    /**
     * Return an array of multiple columns from the dataset indexed by another column
     *
     * This function creates a array of the first column in the result serving as an
     * index to the array containing the remaining columns.  It will error if there
     * are less than two columns
     *
     * @return mixed[] The desired data map
     */
    public function toIndex()
    {
        if (!$this->hasRows()) {
            // If we have no rows we have no hash keys to generate a proper
            // array_column call, the result we'd want then is `[]` anyways
            return [];
        }
        if ($this->columnCount() < 2) {
            // If there aren't two columns, error, even if the result set is empty
            throw new \Exception(
                'Invalid query result to channel into a map. The query must return at least 2 columns'
            );
        }
        // Buffer full data set
        $data = $this->realize();
        $indexColumn = array_keys($data[0])[0];
        return array_map(
            function ($elem) use ($indexColumn) {
                unset($elem[$indexColumn]);
                return $elem;
            },
            array_column($data, null, $indexColumn)
        );
    }

    /**
     * Return an array of multiple columns from the dataset indexed by another column
     *
     * This function creates a array of the first column in the result serving as an
     * index to the array containing the remaining columns.  the result has a 2nd row of arrays
     *
     * For instance the results 1, 2, 5
     *                          1, 3, 6
     *                          2, 2, 10
     * will give an embedded array of
     * [1 => [2 => 5, 3 => 6], 2 => [2 => 10]
     *
     * @return mixed[] The desired data map
     */
    public function toIndex2d()
    {
        if (!$this->hasRows()) {
            // If we have no rows we have no hash keys to generate a proper
            // array_column call, the result we'd want then is `[]` anyways
            return [];
        }
        if ($this->columnCount() < 3) {
            // If there aren't two columns, error, even if the result set is empty
            throw new \Exception(
                'Invalid query result to channel into a map. The query must return at least 3 columns'
            );
        }
        // Buffer full data set
        $data = $this->realize();
        $indexColumn = array_keys($data[0])[0];
        $index2Column = array_keys($data[0])[1];
        $array = [];

        foreach ($data as $elem) {
            $strippedElem = $elem;
            unset($strippedElem[$indexColumn]);
            unset($strippedElem[$index2Column]);

            $array[$elem[$indexColumn]][$elem[$index2Column]] = $strippedElem;
        }

        return $array;
    }

    /**
     * Return the single column from the dataset
     *
     * This function asserts that only a single column was returned from the database
     * and will error if more columns were returned
     *
     * @return mixed[] The column values
     * @throws \Exception
     */
    public function toColumn()
    {
        $rows = $this->realize();
        $result = [];
        foreach ($rows as $key => $row) {
            if (count($row) > 1) {
                throw new \Exception('Multiple columns returned, exactly one column must be in result set');
            } elseif (count($row) === 0) {
                throw new \Exception('No columns returned in result, exactly one column must be in result set');
            } elseif (count($row) !== 1) {
                throw new \Exception('Bad row returned');
            }
            $result[$key] = \reset($row);
        }
        return $result;
    }

    public function toCsvHandle(?array $columnNames = null)
    {
        $handle = \fopen('php://memory', 'r+');
        if ($columnNames === null) {
            $columnNames = $this->columnNames();
        }
        \fputcsv($handle, $columnNames);
        foreach ($this as $row) {
            \fputcsv($handle, $row);
        }
        \ftruncate($handle, \ftell($handle) - 1);
        \rewind($handle);
        return $handle;
    }

    /**
     * Whether this iterator is wrapping any rows or if it is empty
     *
     * @return bool If the result set is non-empty
     */
    public function hasRows(): bool
    {
        return $this->count() > 0;
    }

    /**
     * Return a list of column names contained in the result set
     *
     * @return string[] The column names
     */
    abstract protected function rawColumnNames(): array;

    /**
     * Return the number of rows available
     *
     * @return int The count of rows
     */
    abstract public function count(): int;
}
