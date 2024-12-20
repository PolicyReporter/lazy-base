<?php

declare(strict_types=1);

namespace Policyreporter\LazyBase\Lazy;

require_once('test/TestCase.php');

/**
 * @small
 */
class KeyedGeneratorTest extends \Policyreporter\LazyBase\TestCase
{
    public function test_iteration()
    {
        $iterator = new KeyedGenerator(KeyedGenerator\readCsv(__DIR__ . '/KeyedGenerator/test_input.csv', \CSV_ASSOCIATIVE));
        $this->assertEquals(
            [
                [
                    'foo' => 1,
                    'bar' => 2,
                ],
                [
                    'foo' => 3,
                    'bar' => 4,
                ],
            ],
            $iterator->realize()
        );
    }
}
