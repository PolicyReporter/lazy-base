<?php

declare(strict_types=1);

namespace Policyreporter\LazyBase;

abstract class QueryBuilder extends \Doctrine\DBAL\Query\QueryBuilder
{
    public function execute(): Lazy\AbstractIterator
    {
        return $this->getConnection()->executeWrappedQuery(
            $this->getSQL(),
            $this->getParameters(),
            $this->getParameterTypes()
        );
    }

    public function run(
        array $parameters = [],
        array $parameterTypes = []
    ): Lazy\AbstractIterator {
        return $this->getConnection()->executeWrappedQuery(
            $this->getSQL(),
            array_merge($this->getParameters(), $parameters),
            array_merge($this->getParameterTypes(), $parameterTypes)
        );
    }

    public function setParameter($key, $value, $type = null): self
    {
        if (is_int($key)) {
            // NB: This is a temporary measure we're taking to make parameter expansion easier
            // it's also an apparently rarely used feature so it shouldn't impact devs much.
            // To re-allow positional parameters we'll just need to make PDO\run() compatible
            // with positional paramter binding which *mostly* just breaks right now when it comes
            // to array explosion.
            throw new \Exception\System(
                'Positional parameters are prohibited in constructed'
                . ' queries, please use named parameters.'
            );
        }
        return parent::setParameter($key, $value, $type);
    }
}
