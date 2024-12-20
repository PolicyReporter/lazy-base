<?php

declare(strict_types=1);

namespace Policyreporter\LazyBase\Lazy\Csv\LinewiseMessageStream;

class Stream implements \Psr\Http\Message\StreamInterface
{
    public function __construct(
        protected \Psr\Http\Message\StreamInterface $stream,
    )
    {
    }

    public function readLine(int $maxLength = null): string
    {
        return \rtrim(self::readLineFrom($this, $maxLength), "\n");
    }

    /**
     * @see \GuzzleHttp\Psr7\readline
     * Recorded here to avoid a dependency on an internal guzzle function
     */
    private static function readLineFrom(\Psr\Http\Message\StreamInterface $stream, $maxLength = null): string
    {
        $buffer = '';
        $size = 0;

        while (!$stream->eof()) {
            // Using a loose equality here to match on '' and false.
            if (null == ($byte = $stream->read(1))) {
                return $buffer;
            }
            $buffer .= $byte;
            // Break when a new line is found or the max length - 1 is reached
            if ($byte === "\n" or ++$size === $maxLength - 1) {
                break;
            }
        }

        return $buffer;
    }

    // Concrete implementations as `__call` can't serve as implementation for passthru's
    public function __toString()
    {
        return call_user_func_array([$this->stream, __FUNCTION__], func_get_args());
    }
    public function close(): void
    {
        call_user_func_array([$this->stream, __FUNCTION__], func_get_args());
    }
    public function detach()
    {
        return call_user_func_array([$this->stream, __FUNCTION__], func_get_args());
    }
    public function getSize(): ?int
    {
        return call_user_func_array([$this->stream, __FUNCTION__], func_get_args());
    }
    public function tell(): int
    {
        return call_user_func_array([$this->stream, __FUNCTION__], func_get_args());
    }
    public function eof(): bool
    {
        return call_user_func_array([$this->stream, __FUNCTION__], func_get_args());
    }
    public function isSeekable(): bool
    {
        return call_user_func_array([$this->stream, __FUNCTION__], func_get_args());
    }
    public function seek($offset, $whence = SEEK_SET): void
    {
        call_user_func_array([$this->stream, __FUNCTION__], func_get_args());
    }
    public function rewind(): void
    {
        call_user_func_array([$this->stream, __FUNCTION__], func_get_args());
    }
    public function isWritable(): bool
    {
        return call_user_func_array([$this->stream, __FUNCTION__], func_get_args());
    }
    public function write($string): int
    {
        return call_user_func_array([$this->stream, __FUNCTION__], func_get_args());
    }
    public function isReadable(): bool
    {
        return call_user_func_array([$this->stream, __FUNCTION__], func_get_args());
    }
    public function read($length): string
    {
        return call_user_func_array([$this->stream, __FUNCTION__], func_get_args());
    }
    public function getContents(): string
    {
        return call_user_func_array([$this->stream, __FUNCTION__], func_get_args());
    }
    public function getMetadata($key = null)
    {
        return call_user_func_array([$this->stream, __FUNCTION__], func_get_args());
    }
}
