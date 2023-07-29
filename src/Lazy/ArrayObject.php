<?php

namespace Policyreporter\LazyBase\Lazy;

class ArrayObject extends AbstractWrappingIterator
{
    protected $array;
    protected $iterator;
    protected $firstResult = true;

    public function __construct($array)
    {
        $this->array = $array;
        $this->iterator = new \ArrayIterator($this->array);
    }

    public function iterator(): \Iterator
    {
        return $this->iterator;
    }

    /**
     * Return a list of column names contained in the result set
     *
     * @return string[] The column names
     */
    protected function rawColumnNames(): array
    {
        return \array_keys(\current($this->array));
    }
}
