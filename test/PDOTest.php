<?php

namespace Policyreporter\LazyBase;

require_once('test/TestCase.php');

/**
 * @small
 */
class PDOTest extends \Policyreporter\LazyBase\TestCase
{
    public function test_whereClause_Empty()
    {
        $this->assertEquals('', PDO::whereClause([]));
        $this->assertEquals('', PDO::whereClause(['']));
        $this->assertEquals('', PDO::whereClause([null]));
        $this->assertEquals('', PDO::whereClause([], '    '));
    }

    public function test_whereClause_Indent()
    {
        $this->assertEquals(
            <<<SQL
    WHERE
    (
        1 = 1
    )
SQL
            ,
            PDO::whereClause(['1 = 1'], '    ')
        );
    }

    public function test_whereClause_Args()
    {
        $this->assertEquals(
            <<<SQL
WHERE
(
    1 = 1
)
AND
(
    2 = 2
)
AND
(
    2 = 3 OR 3 = 3
)
SQL
            ,
            PDO::whereClause(['1 = 1', '2 = 2', '2 = 3 OR 3 = 3'])
        );
    }

    public function test_explodeParams()
    {
        $func = (new \ReflectionClass(PDO::class))->getMethod('explodeParams');
        $func->setAccessible(true);
        $testCases = [
            'No-op query, no args' => [
                'query'          => ['query', 'query'],
                'args'           => [[], []],
                'extraneousArgs' => [],
            ],
            'No-op query, extraneous args' => [
                'query'          => ['query', 'query'],
                'args'           => [['moo' => 'cow'], []],
                'extraneousArgs' => [':moo'],
            ],
            'Simple query, found args' => [
                'query'          => ['query:moo', 'query:moo'],
                'args'           => [['moo' => 'cow'], [':moo' => 'cow']],
                'extraneousArgs' => [],
            ],
            'Simple query, mixed args' => [
                'query'          => ['query:moo', 'query:moo'],
                'args'           => [['moo' => 'cow', 'meow' => 'cat'], [':moo' => 'cow']],
                'extraneousArgs' => [':meow'],
            ],
            'Explosion query, found args' => [
                'query'          => ['query(:moo)', 'query(:0moo__0, :0moo__1, :0moo__2, :0moo__3)'],
                'args'           => [['moo' => ['holstein', 'jersey', 'braunvieh', 'pinzgauer']],
                                     [
                                         ':0moo__0' => 'holstein',
                                         ':0moo__1' => 'jersey',
                                         ':0moo__2' => 'braunvieh',
                                         ':0moo__3' => 'pinzgauer'
                                     ]
                ],
                'extraneousArgs' => [],
            ],
            'Explosion query, mixed args' => [
                'query'          => ['query(:moo)', 'query(:0moo__0, :0moo__1, :0moo__2, :0moo__3)'],
                'args'           => [['moo' => ['holstein', 'jersey', 'braunvieh', 'pinzgauer'], 'meow' => 'cat'],
                                     [
                                         ':0moo__0' => 'holstein',
                                         ':0moo__1' => 'jersey',
                                         ':0moo__2' => 'braunvieh',
                                         ':0moo__3' => 'pinzgauer'
                                     ]
                ],
                'extraneousArgs' => [':meow'],
            ],
            'Type-like query, extraneous args' => [
                'query'          => ['query::moo', 'query::moo'],
                'args'           => [['moo' => 'cow'], []],
                'extraneousArgs' => [':moo'],
            ],
            'Type-like explosion query, extraneous args' => [
                'query'          => ['query::moo', 'query::moo'],
                'args'           => [['moo' => ['holstein', 'jersey', 'braunvieh', 'pinzgauer'], 'meow' => 'cat'], []],
                'extraneousArgs' => [':moo', ':meow'],
            ],
            'Nearly type-like query, found args' => [
                'query'          => ['query:moo::text', 'query:moo::text'],
                'args'           => [['moo' => 'cow'], [':moo' => 'cow']],
                'extraneousArgs' => [],
            ],
        ];
        foreach ($testCases as $descriptor => $parameters) {
            [$inQuery, $outQuery] = $parameters['query'];
            [$inArgs, $outArgs] = $parameters['args'];
            $outExtraneousArgs = $parameters['extraneousArgs'];
            [$resultQuery, $resultArgs, $resultExtraneousArgs] = $func->invoke(null, $inQuery, $inArgs);
            foreach (['query', 'args', 'extraneousArgs'] as $var) {
                $this->assertEquals(
                    ${'out' . ucfirst($var)},
                    ${'result' . ucfirst($var)},
                    "{$descriptor} testing {$var}"
                );
            }
        }
    }

    public function test_insertClauseForData_1()
    {
        //array of cases, where cases are [input, expected output]
        $case = [
            [
                [102, 10,],
                [102, 12,],
                [103, 10,],
                [103, 12,],
            ],
            "(:0),(:1),(:2),(:3)"
        ];
        $this->assertEquals($case[1], PDO::insertClauseForData($case[0]));
    }

    public function test_insertClauseForData_2()
    {
        //array of cases, where cases are [input, expected output]
        $case = [
            [
                [5, 'Category2', null],
                [6, 'Subcategory2', 5],
                [7, 'Ssubcategory2', 6],
                [8, 'Sssubcategory2', 7],
            ],
            "(:0),(:1),(:2),(:3)"
        ];
        $this->assertEquals($case[1], PDO::insertClauseForData($case[0]));
    }

    public function test_insertClauseForData_3()
    {
        //array of cases, where cases are [input, expected output]
        $case = [
            [
                [102, 'calcium polycarbophil', 0, null,],
            ],
            "(:0)"
        ];
        $this->assertEquals($case[1], PDO::insertClauseForData($case[0]));
    }

    public function test_insertClauseForData_4()
    {
        //array of cases, where cases are [input, expected output]
        $case = [
            [1,],
            "(:0)"
        ];
        $this->assertEquals($case[1], PDO::insertClauseForData($case[0]));
    }

    public function test_insertClauseForData_nonintkey()
    {
        $input = [
            "test" => [5, 'Category2', null],
            [6, 'Subcategory2', 5],
            [7, 'Ssubcategory2', 6],
            [8, 'Sssubcategory2', 7],
        ];
        $this->assertThrows(
            "Exception",
            $this->getInvokableMethod(PDO::class, 'insertClauseForData'),
            [$input],
            '/Key test is not an int!/'
        );
    }

    public function test_insertClauseForData_unnaturalkey()
    {
        $input = [
            0 => [5, 'Category2', null],
            1 => [6, 'Subcategory2', 5],
            6 => [7, 'Ssubcategory2', 6],
            3 => [8, 'Sssubcategory2', 7],
        ];
        $this->assertThrows(
            "Exception",
            $this->getInvokableMethod(PDO::class, 'insertClauseForData'),
            [$input],
            '/Expected key to be 2, got 6/'
        );
    }

    public function testInsertClauseForData()
    {
        $data = ['tom', 'dick', 'harry'];
        $actual = PDO::insertClauseForData($data);
        $expected = '(:0),(:1),(:2)';
        $this->assertEquals($expected, $actual);

        $data = [4 => 'tom', 8 => 'dick', 3 => 'harry'];
        $this->assertThrows(
            \Exception::class,
            $this->getInvokableMethod(PDO::class, 'insertClauseForData'),
            [$data],
            "/Expected key to be 5, got 8/"
        );

        $data = [4 => 'tom', 'guardian' => 'bob', 3 => 'harry'];
        $this->assertThrows(
            \Exception::class,
            $this->getInvokableMethod(PDO::class, 'insertClauseForData'),
            [$data],
            "/Key guardian is not an int/"
        );

        $data = [ 'guardian' => 'bob', 4 => 'tom', 'guardian' => 'bob', 3 => 'harry'];
        $this->assertThrows(
            \Exception::class,
            $this->getInvokableMethod(PDO::class, 'insertClauseForData'),
            [$data],
            "/Key guardian is not an int/"
        );

        $data = [4 => 'tom', 5 => 'dick', 6 => 'harry'];
        $actual = PDO::insertClauseForData($data);
        $expected = '(:4),(:5),(:6)';
        $this->assertEquals($expected, $actual);

        $data = 'thingy';
        $this->assertThrows(
            \Exception::class,
            $this->getInvokableMethod(PDO::class, 'insertClauseForData'),
            [$data],
            "/expects data to be an array/"
        );

        $data = 1;
        $this->assertThrows(
            \Exception::class,
            $this->getInvokableMethod(PDO::class, 'insertClauseForData'),
            [$data],
            "/expects data to be an array/"
        );
    }

    public function test_havingClause_Empty()
    {
        $this->assertEquals('', PDO::havingClause([]));
        $this->assertEquals('', PDO::havingClause(['']));
        $this->assertEquals('', PDO::havingClause([null]));
        $this->assertEquals('', PDO::havingClause([], '    '));
    }

    public function test_havingClause_Indent()
    {
        $this->assertEquals(
            <<<SQL
    HAVING
    (
        1 = 1
    )
SQL
            ,
            PDO::havingClause(['1 = 1'], '    ')
        );
    }

    public function test_havingClause_Args()
    {
        $this->assertEquals(
            <<<SQL
HAVING
(
    1 = 1
)
AND
(
    2 = 2
)
AND
(
    2 = 3 OR 3 = 3
)
SQL
            ,
            PDO::havingClause(['1 = 1', '2 = 2', '2 = 3 OR 3 = 3'])
        );
    }

    public function test_buildEscapedColumnNameString()
    {
        $expected = '"string1", "string2", "STRING3", "string\'4"';
        $actual = PDO::buildEscapedColumnNameString(["string1", "string2", "STRING3", "string'4"]);
        $this->assertEquals($expected, $actual);

        $this->assertThrows(
            'Exception',
            $this->getInvokableMethod(PDO::class, 'buildEscapedColumnNameString'),
            [['string1', 'string"2']],
            '/Database column names cannot include the " character/'
        );
    }
}
