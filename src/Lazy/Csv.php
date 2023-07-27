<?php

declare(strict_types=1);

namespace PolicyReporter\LazyBase\Lazy;

abstract class Csv extends AbstractIterator
{
    protected $rawColumnNames = null;

    abstract protected function readLine(): array;

    protected function fetchCurrent(): array
    {
        static $i = 0;
        // If we haven't yet been asked to capture the headers do so before we advance beyond them
        if ($this->columnNames === null) {
            $this->columnNames();
        }
        return $this->readLine();
    }

    protected function rawColumnNames(): array
    {
        if ($this->rawColumnNames === null) {
            $this->rawColumnNames = $this->readLine();
        }
        return $this->rawColumnNames;
    }

    public function count(): int
    {
        throw new \Exception\System('Invalid attempt to count un-countable object');
    }

    public function rewind(): void
    {
        // Since the column data is embeded in the file whenever we rewind we
        // should be careful to re-consume the column data and not read it as a row
        $this->rawColumnNames = null;
        $this->rawColumnNames();
    }
}
