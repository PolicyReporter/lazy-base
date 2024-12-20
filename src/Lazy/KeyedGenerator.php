<?php

declare(strict_types=1);

namespace Policyreporter\LazyBase\Lazy;

require_once('KeyedGenerator/readCsv.php');

/**
 * @see parent
 */
class KeyedGenerator extends AbstractWrappingIterator
{
    protected $headerRow = null;
    protected $rawColumnNames = [];

    public function __construct(
        protected \Generator $generator,
    )
    {
    }

    public function iterator(): \Iterator
    {
        return $this->generator;
    }

    public function columnCount(): int
    {
        return \count($this->current());
    }

    protected function fetchCurrent(): array
    {
        return $this->iterator()->current();
    }

    protected function rawColumnNames(): array
    {
        if ($this->headerRow === null) {
            $this->headerRow = array_keys($this->iterator()->current());
        }
        return $this->headerRow;
    }

    public function count(): int
    {
        throw new \Exception\System('Invalid attempt to count un-countable object');
    }
}
