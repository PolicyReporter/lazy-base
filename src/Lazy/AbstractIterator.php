<?php

declare(strict_types=1);

namespace Policyreporter\LazyBase\Lazy;

abstract class AbstractIterator implements \Iterator, \Countable
{
    use IteratorBehavior;

    /**
     * Materialize the lazy iterator (not PDOStatement::fetchAll).
     */
    public function fetchAll()
    {
        return $this->realize();
    }
}
