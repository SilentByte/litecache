<?php
/**
 * SilentByte LiteCache Library
 *
 * @copyright 2017 SilentByte <https://silentbyte.com/>
 * @license   https://opensource.org/licenses/MIT MIT
 */

declare(strict_types = 1);

namespace SilentByte\LiteCache;

use PHPUnit\Framework\TestCase;
use SilentByte\LiteCache\ObjectComplexityAnalyzer as OCA;
use stdClass;

class ObjectComplexityAnalyzerTest extends TestCase
{
    use VirtualFileSystemTrait;

    private $resource;

    public function setUp()
    {
        $this->vfs();
        $this->resource = fopen($this->url('root/test.txt'), 'w');
    }

    public function tearDown()
    {
        fclose($this->resource);
    }

    public function objectProvider()
    {
        $object = new stdClass();
        $object->foo = 'bar';
        $object->xyz = 1234;
        $object->array = [10, 20, 30, 40, 50];

        return [
            'null'                     => [OCA::SIMPLE, null],
            'int'                      => [OCA::SIMPLE, 1234],
            'string'                   => [OCA::SIMPLE, 'test'],
            'float'                    => [OCA::SIMPLE, 3.1415],
            'true'                     => [OCA::SIMPLE, true],
            'false'                    => [OCA::SIMPLE, false],
            'resource'                 => [OCA::SIMPLE, $this->resource],
            'empty-array'              => [OCA::SIMPLE, []],
            'mixed-array'              => [OCA::SIMPLE, [1234, 'test', true, 3.1515, false, null]],
            'assoc-array'              => [OCA::SIMPLE, [
                'aaa' => 1234,
                'bbb' => 3.1415,
                'ccc' => 'test'
            ]],
            'nested-array'             => [OCA::SIMPLE, [
                'aaa' => 1234,
                'bbb' => [
                    'xxx'    => 1234,
                    'yyy'    => 3.1415,
                    'zzz'    => 'test',
                    'nested' => [
                        'nested-1' => 'first',
                        'nested-2' => 'second'
                    ]
                ]
            ]],
            'object'                   => [OCA::COMPLEX, $object],
            'nested-array-with-object' => [OCA::COMPLEX, [
                'aaa' => 1234,
                'bbb' => [
                    'xxx'    => 1234,
                    'yyy'    => 3.1415,
                    'zzz'    => 'test',
                    'nested' => [
                        'nested-1' => 'first',
                        'object'   => $object,
                        'nested-2' => 'second'
                    ]
                ]
            ]]
        ];
    }

    /**
     * @dataProvider objectProvider
     */
    public function testAnalyze($complexity, $object)
    {
        $oca = new ObjectComplexityAnalyzer(PHP_INT_MAX, PHP_INT_MAX);
        $this->assertEquals($complexity, $oca->analyze($object));
    }

    public function testAnalyzeReportsComplexOnHighEntryCount()
    {
        $object = [
            'aaa' => 1234,
            'bbb' => [
                'xxx'    => 1234,
                'yyy'    => 3.1415,
                'zzz'    => 'test',
                'nested' => [
                    'nested-1' => 'first',
                    'nested-2' => 'second'
                ]
            ]
        ];

        $oca = new ObjectComplexityAnalyzer(4, PHP_INT_MAX);
        $this->assertEquals(OCA::COMPLEX, $oca->analyze($object));
    }

    public function testAnalyzeReportsComplexOnDeepHierarchy()
    {
        $object = [
            'aaa' => 1234,
            'bbb' => [
                'xxx'    => 1234,
                'yyy'    => 3.1415,
                'zzz'    => 'test',
                'nested' => [
                    'nested-1' => 'first',
                    'nested-2' => 'second'
                ]
            ]
        ];

        $oca = new ObjectComplexityAnalyzer(PHP_INT_MAX, 2);
        $this->assertEquals(OCA::COMPLEX, $oca->analyze($object));
    }
}

