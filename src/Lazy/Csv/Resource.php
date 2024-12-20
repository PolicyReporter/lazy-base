<?php

declare(strict_types=1);

namespace Policyreporter\LazyBase\Lazy\Csv;

class Resource extends \Policyreporter\LazyBase\Lazy\Csv
{
    protected $size;

    public function __construct(
        protected $fileHandle,
    )
    {
        $this->size = fstat($this->fileHandle)['size'];
    }

    protected function readLine(): array
    {
        return \fgetcsv($this->fileHandle);
    }

    public function valid(): bool
    {
        return !(\feof($this->fileHandle) or (\ftell($this->fileHandle) === $this->size));
    }

    public function rewind(): void
    {
        \fseek($this->fileHandle, 0);
        parent::rewind();
    }
}
