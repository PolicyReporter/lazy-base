<?php

declare(strict_types=1);

namespace Policyreporter\LazyBase\PDO;

require_once('test/TestCase.php');

/**
 * @small
 */
class CompositionWrapperTest extends \Policyreporter\LazyBase\TestCase
{
    public function testMethodDefinitions()
    {
        $builtInClass = new \ReflectionClass(\PDO::class);
        $customClass = new \ReflectionClass(CompositionWrapper::class);
        foreach ($builtInClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $builtInMethod) {
            if ($builtInMethod->getName() === '__construct') {
                continue;
            }
            $this->assertTrue(
                $customClass->hasMethod($builtInMethod->getName()),
                "Unable to find function {$builtInMethod->getName()} on the custom class."
            );
            $customMethod = $customClass->getMethod($builtInMethod->getName());
            $builtInParameters = $builtInMethod->getParameters();
            $customParameters = $customMethod->getParameters();
            $this->assertEquals(
                count($builtInParameters),
                count($customParameters),
                "Different number of parameters found for function {$builtInMethod->getName()}"
            );
            for ($i = 0; $i < count($builtInParameters); $i++) {
                $this->assertEquals(
                    $builtInParameters[$i]->getName(),
                    $customParameters[$i]->getName(),
                    "Different parameter name found for parameter {$i} of function {$builtInMethod->getName()}"
                );
            }
        }
    }
}
