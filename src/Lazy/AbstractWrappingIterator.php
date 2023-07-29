<?php

declare(strict_types=1);

namespace Policyreporter\LazyBase\Lazy;

abstract class AbstractWrappingIterator extends AbstractIterator
{
    abstract protected function iterator(): \Iterator;

    /**
     * Advance the iterator to the next result
     *
     * @return void
     */
    public function next(): void
    {
        $this->iterator()->next();
        parent::next();
    }

    /**
     * Return the next result row from our query iterator, if possible
     *
     * @throws \Exception If no data remains to be viewed
     * @return mixed[] The next raw result row
     */
    protected function fetchCurrent(): array
    {
        return $this->iterator()->current();
    }

    /**
     * Rewind the iterator
     *
     * @return void
     */
    public function rewind(): void
    {
        $this->iterator()->rewind();
    }

    /**
     * Check whether this iterator still has items to consume
     *
     * @return bool
     */
    public function valid(): bool
    {
        return $this->iterator()->valid();
    }

    public function count(): int
    {
        if (\is_subclass_of($this->iterator(), \Countable::class)) {
            return count($this->iterator());
        } else {
            throw new \Exception\System('Invalid attempt to count un-countable object');
        }
    }
}
