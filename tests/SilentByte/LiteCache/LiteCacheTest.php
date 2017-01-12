<?php
/**
 * SilentByte LiteCache Library
 *
 * @copyright 2017 SilentByte <https://silentbyte.com/>
 * @license   https://opensource.org/licenses/MIT MIT
 */

declare(strict_types = 1);

namespace SilentByte\LiteCache;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use stdClass;

class LiteCacheTest extends TestCase
{
    private $vfs;

    protected function setUp()
    {
        $this->vfs = vfsStream::setup('cache');
    }

    public function invalidKeyProvider()
    {
        return [[null], ['']];
    }

    public function keyObjectProvider()
    {
        $object = new stdClass();
        $object->foo = 'bar';
        $object->xyz = 1234;
        $object->array = [10, 20, 30, 40, 50];

        return [
            ['key-string', 'test'],
            ['key-integer', 1234],
            ['key-float', 3.1415],
            ['key-boolean-true', true],
            ['key-boolean-false', false],
            ['key-array', [
                'foo'   => 'bar',
                'xyz'   => 1234,
                'array' => [10, 20, 30, 40, 50]
            ]],
            ['key-object', $object],
            ['key-array-object', [$object, $object, $object]]
        ];
    }

    public function create($config = null)
    {
        $defaultConfig = [
            'directory' => vfsStream::url('cache/.litecache')
        ];

        $config = array_merge($defaultConfig,
                              $config !== null ? $config : []);

        return new LiteCache($config);
    }

    public function testConstructorCreatesCacheDirectoryWithPermissions()
    {
        $cache = $this->create();

        $this->assertTrue(file_exists($cache->getCacheDirectory()));
        $this->assertEquals(0766, fileperms($cache->getCacheDirectory()) & 0777);
    }

    public function testGetReturnsNullForUncachedObjects()
    {
        $cache = $this->create();
        $this->assertNull($cache->get('uncached-object'));
    }

    public function testGetReturnsDefaultForUncachedObjects()
    {
        $cache = $this->create();

        $this->assertEquals(10, $cache->get('uncached-object', 10));
        $this->assertEquals('string', $cache->get('uncached-object', 'string'));
        $this->assertEquals([
                                'first'  => 'one',
                                'second' => 2
                            ],
                            $cache->get('uncached-object', [
                                'first'  => 'one',
                                'second' => 2
                            ]));
    }

    /**
     * @dataProvider keyObjectProvider
     */
    public function testGetReturnsCachedObject($key, $object)
    {
        $cache = $this->create();
        $cache->set($key, $object, LiteCache::EXPIRE_NEVER);

        $this->assertEquals($object, $cache->get($key));
    }

    /**
     * @dataProvider invalidKeyProvider
     * @expectedException \SilentByte\LiteCache\CacheArgumentException
     */
    public function testGetThrowsOnInvalidKey($key)
    {
        $cache = $this->create();
        $cache->get($key);
    }

    /**
     * @dataProvider invalidKeyProvider
     * @expectedException \SilentByte\LiteCache\CacheArgumentException
     */
    public function testSetThrowsOnInvalidKey($key)
    {
        $cache = $this->create();
        $cache->set($key, 'test');
    }
}

