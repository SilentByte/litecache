<?php
/**
 * SilentByte LiteCache Library
 *
 * @copyright 2017 SilentByte <https://silentbyte.com/>
 * @license   https://opensource.org/licenses/MIT MIT
 */

declare(strict_types = 1);

namespace SilentByte\LiteCache;

use DateInterval;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\visitor\vfsStreamStructureVisitor;
use PHPUnit\Framework\TestCase;
use stdClass;

class LiteCacheTest extends TestCase
{
    private $vfs;

    protected function setUp()
    {
        $this->vfs = vfsStream::setup('cache');
    }

    protected function getVfsDirectoryStructure()
    {
        /** @noinspection PhpUndefinedMethodInspection */
        return vfsStream::inspect(new vfsStreamStructureVisitor(),
                                  $this->vfs)->getStructure();
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

    public function multipleKeyObjectProvider()
    {
        $object = new stdClass();
        $object->foo = 'bar';
        $object->xyz = 1234;
        $object->array = [10, 20, 30, 40, 50];

        return [
            [
                [
                    'key-string'        => 'test',
                    'key-integer'       => 1234,
                    'key-float'         => 3.1415,
                    'key-boolean-true'  => true,
                    'key-boolean-false' => false,
                    'key-array'         => [
                        'foo'   => 'bar',
                        'xyz'   => 1234,
                        'array' => [10, 20, 30, 40, 50]
                    ],
                    'key-object'        => $object,
                    'key-array-object'  => [$object, $object, $object]
                ]
            ]
        ];
    }

    public function create($config = null)
    {
        $defaultConfig = [
            'directory' => vfsStream::url('cache/.litecache'),
            'ttl'       => LiteCache::EXPIRE_NEVER
        ];

        $config = array_replace_recursive($defaultConfig,
                                          $config !== null ? $config : []);

        return new LiteCache($config);
    }

    public function testConstructorCreatesCacheDirectoryWithPermissions()
    {
        $cache = $this->create();

        $this->assertTrue(file_exists($cache->getCacheDirectory()));
        $this->assertEquals(0766, fileperms($cache->getCacheDirectory()) & 0777);
    }

    public function testConstructorAcceptsDateInterval()
    {
        $interval = DateInterval::createFromDateString('10 seconds');
        $cache = $this->create([
                                   'ttl' => $interval
                               ]);

        $this->assertEquals(10, $cache->getDefaultTimeToLive());
    }

    public function testConstructorAcceptsDateIntervalString()
    {
        $cache = $this->create([
                                   'ttl' => '10 seconds'
                               ]);

        $this->assertEquals(10, $cache->getDefaultTimeToLive());
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
        $cache->set($key, $object);

        $this->assertEquals($object, $cache->get($key));
    }

    /**
     * @dataProvider invalidKeyProvider
     * @expectedException \Psr\SimpleCache\InvalidArgumentException
     */
    public function testGetThrowsOnInvalidKey($key)
    {
        $cache = $this->create();
        $cache->get($key);
    }

    /**
     * @dataProvider invalidKeyProvider
     * @expectedException \Psr\SimpleCache\InvalidArgumentException
     */
    public function testSetThrowsOnInvalidKey($key)
    {
        $cache = $this->create();
        $cache->set($key, 'test');
    }

    public function testSetCreatesCacheFile()
    {
        $cache = $this->create();
        $cache->set('test', 1234);

        $this->assertFileExists($cache->getCacheDirectory()
                                . DIRECTORY_SEPARATOR
                                . '098f6bcd4621d373cade4e832627b4f6.litecache.php');
    }

    /**
     * @expectedException \Psr\SimpleCache\InvalidArgumentException
     */
    public function testCacheThrowsOnEmptyKey()
    {
        $cache = $this->create();
        $cache->cache('', function () {
            return 1234;
        });
    }

    /**
     * @expectedException \TypeError
     */
    public function testCacheThrowsOnNullKey()
    {
        $cache = $this->create();
        $cache->cache(null, function () {
            return 1234;
        });
    }

    public function testCacheExecutesProducerOnUncachedObject()
    {
        $cache = $this->create();

        $executed = false;
        $cache->cache('test', function () use (&$executed) {
            $executed = true;
            return 1234;
        });

        $this->assertTrue($executed);
    }

    public function testCacheIgnoresProducerOnUncachedObject()
    {
        $cache = $this->create();
        $executed = false;

        $cache->cache('test', function () {
            return 1234;
        }, LiteCache::EXPIRE_NEVER);

        $cache->cache('test', function () use (&$executed) {
            $executed = true;
            return 5678;
        }, LiteCache::EXPIRE_NEVER);

        $this->assertFalse($executed);
    }

    /**
     * @dataProvider keyObjectProvider
     */
    public function testCacheReturnsCachedObject($key, $object)
    {
        $cache = $this->create();
        $cached = $cache->cache($key, function () use ($object) {
            return $object;
        });

        $this->assertEquals($object, $cached);
    }

    public function testCacheCreatesCacheFile()
    {
        $cache = $this->create();
        $cache->cache('test', function () {
            return 1234;
        });

        $this->assertFileExists($cache->getCacheDirectory()
                                . DIRECTORY_SEPARATOR
                                . '098f6bcd4621d373cade4e832627b4f6.litecache.php');
    }

    public function testDeleteReturnsFalseOnUncachedObject()
    {
        $cache = $this->create();
        $this->assertFalse($cache->delete('uncached'));
    }

    public function testDeleteActuallyDeletesCacheFile()
    {
        $cache = $this->create();

        $cache->set('test', 1234);
        $cache->delete('test');

        $this->assertFileNotExists($cache->getCacheDirectory()
                                   . DIRECTORY_SEPARATOR
                                   . '098f6bcd4621d373cade4e832627b4f6.litecache.php');
    }

    public function testDeleteReturnsTrueOnSuccess()
    {
        $cache = $this->create();

        $cache->set('test', 1234);
        $this->assertTrue($cache->delete('test'));
    }

    /**
     * @dataProvider invalidKeyProvider
     * @expectedException \Psr\SimpleCache\InvalidArgumentException
     */
    public function testDeleteThrowsThrowsOnInvalidKey($key)
    {
        $cache = $this->create();
        $cache->delete($key);
    }

    public function testClearReturnsTrueOnEmptyCache()
    {
        $cache = $this->create();
        $this->assertTrue($cache->clear());
    }

    public function testClearClearsAllCacheFiles()
    {
        $cache = $this->create();

        $cache->set('aaa', 1234);
        $cache->set('bbb', 'test');
        $cache->set('ccc', 3.1415);

        $this->assertNotEmpty($this->getVfsDirectoryStructure()
                              ['cache']['.litecache']);
        $cache->clear();

        $this->assertEmpty($this->getVfsDirectoryStructure()
                           ['cache']['.litecache']);
    }

    public function testGetMultipleReturnsNullForUncachedObjects()
    {
        $cache = $this->create();
        $objects = $cache->getMultiple([
                                           'uncached-object-1',
                                           'uncached-object-2',
                                           'uncached-object-3',
                                           'uncached-object-4'
                                       ]);

        $this->assertEquals([
                                'uncached-object-1' => null,
                                'uncached-object-2' => null,
                                'uncached-object-3' => null,
                                'uncached-object-4' => null
                            ],
                            $objects);
    }

    public function testGetMultipleReturnsDefaultForUncachedObjects()
    {
        $cache = $this->create();
        $objects = $cache->getMultiple([
                                           'uncached-object-1',
                                           'uncached-object-2',
                                           'uncached-object-3',
                                           'uncached-object-4'
                                       ], 1234);

        $this->assertEquals([
                                'uncached-object-1' => 1234,
                                'uncached-object-2' => 1234,
                                'uncached-object-3' => 1234,
                                'uncached-object-4' => 1234
                            ],
                            $objects);
    }

    /**
     * @dataProvider multipleKeyObjectProvider
     */
    public function testGetMultipleReturnsCachedObjects($keys)
    {
        $cache = $this->create();
        $cache->setMultiple($keys);

        $objects = $cache->getMultiple(array_keys($keys));
        $this->assertEquals($keys, $objects);
    }

    /**
     * @expectedException \Psr\SimpleCache\InvalidArgumentException
     */
    public function testGetMultipleThrowsOnNoArrayNoTraversable()
    {
        $cache = $this->create();
        $cache->getMultiple(1234);
    }

    /**
     * @expectedException \Psr\SimpleCache\InvalidArgumentException
     */
    public function testGetMultipleThrowsOnNull()
    {
        $cache = $this->create();
        $cache->getMultiple(null);
    }

    /**
     * @expectedException \Psr\SimpleCache\InvalidArgumentException
     */
    public function testGetMultipleThrowsOnInvalidKey()
    {
        $cache = $this->create();

        $invalidKeys = [];
        foreach ($this->invalidKeyProvider() as $entry) {
            $invalidKeys[] = $entry[0];
        }

        $cache->getMultiple($invalidKeys);
    }

    /**
     * @expectedException \Psr\SimpleCache\InvalidArgumentException
     */
    public function testSetMultipleThrowsOnNoArrayNoTraversable()
    {
        $cache = $this->create();
        $cache->setMultiple(1234);
    }

    /**
     * @expectedException \Psr\SimpleCache\InvalidArgumentException
     */
    public function testSetMultipleThrowsOnNull()
    {
        $cache = $this->create();
        $cache->setMultiple(null);
    }

    /**
     * @expectedException \Psr\SimpleCache\InvalidArgumentException
     */
    public function testSetMultipleThrowsOnInvalidKey()
    {
        $cache = $this->create();

        $invalidKeys = [];
        foreach ($this->invalidKeyProvider() as $entry) {
            $invalidKeys[] = $entry[0];
        }

        $cache->setMultiple($invalidKeys);
    }

    public function testSetMultipleCreatesCacheFiles()
    {
        $cache = $this->create();

        $cache->setMultiple([
                                'test-1' => 1234,
                                'test-2' => 'test',
                                'test-3' => [10, 20, 30, 40, 50]
                            ]);

        $this->assertFileExists($cache->getCacheDirectory()
                                . DIRECTORY_SEPARATOR
                                . '70a37754eb5a2e7db8cd887aaf11cda7.litecache.php');

        $this->assertFileExists($cache->getCacheDirectory()
                                . DIRECTORY_SEPARATOR
                                . '282ff2cb3d9dadeb831bb3ba0128f2f4.litecache.php');

        $this->assertFileExists($cache->getCacheDirectory()
                                . DIRECTORY_SEPARATOR
                                . '2b61ddda48445374b35a927b6ae2cd6d.litecache.php');
    }

    public function testDeleteMultipleReturnsFalseOnUncachedObject()
    {
        $cache = $this->create();

        $cache->set('cached-1', 1234);
        $this->assertFalse($cache->deleteMultiple(
            [
                'cached-1',
                'uncached-1',
                'uncached-2',
                'uncached-3',
            ]));
    }

    public function testDeleteMultipleActuallyDeletesCacheFile()
    {
        $cache = $this->create();

        $objects = [
            'test-1' => 1234,
            'test-2' => 'test',
            'test-3' => [10, 20, 30, 40, 50]
        ];

        $cache->setMultiple($objects);
        $cache->deleteMultiple(array_keys($objects));

        $this->assertFileNotExists($cache->getCacheDirectory()
                                   . DIRECTORY_SEPARATOR
                                   . '70a37754eb5a2e7db8cd887aaf11cda7.litecache.php');

        $this->assertFileNotExists($cache->getCacheDirectory()
                                   . DIRECTORY_SEPARATOR
                                   . '282ff2cb3d9dadeb831bb3ba0128f2f4.litecache.php');

        $this->assertFileNotExists($cache->getCacheDirectory()
                                   . DIRECTORY_SEPARATOR
                                   . '2b61ddda48445374b35a927b6ae2cd6d.litecache.php');
    }

    public function testDeleteMultipleReturnsTrueOnSuccess()
    {
        $cache = $this->create();

        $objects = [
            'test-1' => 1234,
            'test-2' => 'test',
            'test-3' => [10, 20, 30, 40, 50]
        ];

        $cache->setMultiple($objects);
        $this->assertTrue($cache->deleteMultiple(array_keys($objects)));
    }

    /**
     * @expectedException \Psr\SimpleCache\InvalidArgumentException
     */
    public function testDeleteMultipleThrowsThrowsOnInvalidKey()
    {
        $cache = $this->create();

        $invalidKeys = [];
        foreach ($this->invalidKeyProvider() as $entry) {
            $invalidKeys[] = $entry[0];
        }

        $cache->deleteMultiple($invalidKeys);
    }

    public function testHasReturnsTrueOnCachedObject()
    {
        $cache = $this->create();
        $cache->set('test', 1234);

        $this->assertTrue($cache->has('test'));
    }

    public function testHasReturnsFalseOnUncachedObject()
    {
        $cache = $this->create();
        $this->assertFalse($cache->has('uncached'));
    }

    /**
     * @dataProvider invalidKeyProvider
     * @expectedException \Psr\SimpleCache\InvalidArgumentException
     */
    public function testHasThrowsOnInvalidKey($key)
    {
        $cache = $this->create();
        $cache->has($key);
    }
}

