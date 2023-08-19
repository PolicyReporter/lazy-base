<?php

declare(strict_types=1);

namespace Policyreporter\LazyBase\Lazy\Csv\LinewiseMessageStream;

class Stream implements \Psr\Http\Message\StreamInterface
{
    private $stream;

    public function __construct(\Psr\Http\Message\StreamInterface $stream)
    {
        $this->stream = $stream;
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
            if ($byte === "\n" || ++$size === $maxLength - 1) {
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
    public function close()
    {
        return call_user_func_array([$this->stream, __FUNCTION__], func_get_args());
    }
    public function detach()
    {
        return call_user_func_array([$this->stream, __FUNCTION__], func_get_args());
    }
    public function getSize()
    {
        return call_user_func_array([$this->stream, __FUNCTION__], func_get_args());
    }
    public function tell()
    {
        return call_user_func_array([$this->stream, __FUNCTION__], func_get_args());
    }
    public function eof()
    {
        return call_user_func_array([$this->stream, __FUNCTION__], func_get_args());
    }
    public function isSeekable()
    {
        return call_user_func_array([$this->stream, __FUNCTION__], func_get_args());
    }
    public function seek($offset, $whence = SEEK_SET)
    {
        return call_user_func_array([$this->stream, __FUNCTION__], func_get_args());
    }
    public function rewind()
    {
        return call_user_func_array([$this->stream, __FUNCTION__], func_get_args());
    }
    public function isWritable()
    {
        return call_user_func_array([$this->stream, __FUNCTION__], func_get_args());
    }
    public function write($string)
    {
        return call_user_func_array([$this->stream, __FUNCTION__], func_get_args());
    }
    public function isReadable()
    {
        return call_user_func_array([$this->stream, __FUNCTION__], func_get_args());
    }
    public function read($length)
    {
        return call_user_func_array([$this->stream, __FUNCTION__], func_get_args());
    }
    public function getContents()
    {
        return call_user_func_array([$this->stream, __FUNCTION__], func_get_args());
    }
    public function getMetadata($key = null)
    {
        return call_user_func_array([$this->stream, __FUNCTION__], func_get_args());
    }
}
