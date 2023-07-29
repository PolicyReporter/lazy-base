<?php

declare(strict_types=1);

namespace PolicyReporter\LazyBase\Lazy\Csv;

class Resource extends \Policyreporter\LazyBase\Lazy\Csv
{
    protected $fileHandle;
    protected $size;

    public function __construct($fileHandle)
    {
        $this->fileHandle = $fileHandle;
        $this->size = fstat($this->fileHandle)['size'];
    }

    protected function readLine(): array
    {
        return \fgetcsv($this->fileHandle);
    }

    public function valid(): bool
    {
        return !(\feof($this->fileHandle) || (\ftell($this->fileHandle) === $this->size));
    }

    public function rewind(): void
    {
        \fseek($this->fileHandle, 0);
        parent::rewind();
    }
}
