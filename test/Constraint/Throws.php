<?php

namespace Policyreporter\LazyBase\Constraint;

class Throws extends \PHPUnit\Framework\Constraint\Constraint
{
    private $msg = '';
    protected $value;

    public function __construct($value)
    {
        // An exception class
        $this->value = $value;
    }

    public function matches($other): bool
    {
        if (sizeof($other) != 4) {
            throw new \Exception("Saw invalid argument to " . __CLASS__);
        }
        [$callback, $args, $err, $errorRegex] = $other;

        if (!class_exists($this->value) && !interface_exists($this->value)) {
            throw new \Exception("Class/interface '{$this->value}' does not exist.");
        }

        if ($err === null) {
            $this->msg = "throws an Exception";
            return false;
        }

        $exceptionInfo = [];
        foreach (['message', 'code'] as $type) {
            $value = call_user_func([$err, 'get' . ucfirst($type)]);
            if (!empty($value)) {
                $exceptionInfo[] = "{$type}: {$value}";
            }
        }
        if (!empty($exceptionInfo)) {
            $exceptionInfo = '{' . implode(',', $exceptionInfo) . '}';
        } else {
            $exceptionInfo = '';
        }
        $exceptionInfo = get_class($err) . $exceptionInfo;

        $constraint = new \PHPUnit\Framework\Constraint\IsInstanceOf($this->value);
        if (!$constraint->evaluate($err, '', true)) {
            $this->msg = "throws the expected Exception type [{$this->value}] saw " . \Exception\AbstractException::toString($err);
            return false;
        }
        if ($errorRegex !== null) {
            $constraint = new \PHPUnit\Framework\Constraint\RegularExpression($errorRegex);
            if (!$constraint->evaluate($err->getMessage(), '', true)) {
                $this->msg = "throws an exception with a message matching regex $errorRegex (saw message [" . $err->getMessage() . "])";
                return false;
            }
        }
        return true;
    }

    public function toString(): string
    {
        return $this->msg;
    }

    protected function failureDescription($other): string
    {
        if (sizeof($other) != 4) {
            throw new \Exception("Saw invalid argument to " . __CLASS__);
        }
        [$callback, $args, $err, $errorRegex] = $other;

        if (is_array($callback)) {
            $callback = array_pop($callback);
        } elseif ($callback instanceof \ReflectionMethod) {
            $args[0] = get_class($args[0]);
        } elseif ($callback instanceof \Closure) {
            $callback = 'λ';
        }
        if (!is_array($args)) {
            throw new \Exception('Invalid argument list, $args must be an array of arguments');
        }

        $args = implode(', ', array_map(function ($v) {
            if (is_object($v)) {
                return get_class($v);
            } elseif (!is_scalar($v)) {
                return print_r($v, true);
            } else {
                return $v;
            }
        }, $args));

        return "{$callback}({$args}) {$this->toString()}";
    }
}
