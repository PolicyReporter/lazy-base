<?php

declare(strict_types=1);

namespace Policyreporter\LazyBase\Lazy\Csv;

require_once('test/TestCase.php');

/**
 * @small
 */
class LinewiseMessageStreamTest extends \Policyreporter\LazyBase\TestCase
{
    public function testMakingOne()
    {
        $stream = new LinewiseMessageStream(
            new LinewiseMessageStream\Stream(
                \GuzzleHttp\Psr7\Utils::streamFor("foo,bar\n1,2\n3,4")
            )
        );
        $this->assertEquals(
            [
                ['foo' => 1, 'bar' => 2],
                ['foo' => 3, 'bar' => 4],
            ],
            $stream->realize()
        );
    }
}
