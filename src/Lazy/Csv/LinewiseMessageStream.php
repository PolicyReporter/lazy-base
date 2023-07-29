<?php

declare(strict_types=1);

namespace PolicyReporter\LazyBase\Lazy\Csv;

class LinewiseMessageStream extends \Policyreporter\LazyBase\Lazy\Csv
{
    protected $stream;

    public function __construct($stream)
    {
        $this->stream = $stream;
    }

    protected function readLine(): array
    {
        return \str_getcsv($this->stream->readLine());
    }

    public function valid(): bool
    {
        return !$this->stream->eof();
    }

    public function rewind(): void
    {
        $this->stream->seek(0);
        parent::rewind();
    }
}
