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
use PHPUnit\Framework\TestCase;
use stdClass;

class LiteCacheTest extends TestCase
{
    use VirtualFileSystemTrait;

    protected function setUp()
    {
        $this->vfs();
    }

    public function configProvider()
    {
        return [
            [['subdivision' => false]],
            [['subdivision' => true]],
            [[
                'subdivision' => false,
                'pool'        => 'test-pool'
            ]],
            [[
                'subdivision' => true,
                'pool'        => 'test-pool'
            ]]
        ];
    }

    public function invalidKeyProvider()
    {
        $invalidKeys = [
            null,
            '',
            1234,
            3.14,
            'foo{bar',
            'foo}bar',
            'foo(bar',
            'foo)bar',
            'foo/bar',
            'foo\\bar',
            'foo@bar',
            'foo:bar',
            '{}()/\@:'
        ];

        $nested = [];
        foreach ($invalidKeys as $key) {
            $nested[] = [$key];
        }

        return $nested;
    }

    public function keyObjectProvider()
    {
        $object = new stdClass();
        $object->foo = 'bar';
        $object->xyz = 1234;
        $object->array = [10, 20, 30, 40, 50];

        $data = [
            ['ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789_.', 'psr16-minimum-requirement'],
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

        $permutations = [];
        foreach ($this->configProvider()[0] as $config) {
            foreach ($data as $entry) {
                $permutations[] = array_merge([$config], $entry);
            }
        }

        return $permutations;
    }

    public function multipleKeyObjectProvider()
    {
        $object = new stdClass();
        $object->foo = 'bar';
        $object->xyz = 1234;
        $object->array = [10, 20, 30, 40, 50];

        $data = [
            'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789_.'
                                => 'psr16-minimum-requirement',
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
        ];

        $permutations = [];
        foreach ($this->configProvider()[0] as $config) {
            $permutations[] = [$config, $data];
        }

        return $permutations;
    }

    public function create(array $config = null)
    {
        $defaultConfig = [
            'directory' => $this->url('root/.litecache'),
            'pool'      => 'test-pool',
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

    public function testConstructorCreatesCachePoolDirectoryWithPermissions()
    {
        $cache = $this->create();

        $poolDirectory = $cache->getCacheDirectory()
            . DIRECTORY_SEPARATOR
            . 'af2bc12271d88af325dd44d9029b197d';

        $this->assertTrue(file_exists($poolDirectory));
        $this->assertEquals(0766, fileperms($poolDirectory) & 0777);
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

    /**
     * @expectedException \Psr\SimpleCache\InvalidArgumentException
     */
    public function testConstructorThrowsOnInvalidLogger()
    {
        $this->create([
                          'logger' => 1234
                      ]);
    }

    /**
     * @expectedException \Psr\SimpleCache\InvalidArgumentException
     */
    public function testConstructorThrowsOnInvalidTimeToLive()
    {
        $this->create([
                          'ttl' => 'invalid'
                      ]);
    }

    /**
     * @expectedException \Psr\SimpleCache\InvalidArgumentException
     */
    public function testConstructorThrowsOnInvalidCacheDirectory()
    {
        $this->create([
                          'directory' => null
                      ]);
    }

    /**
     * @dataProvider configProvider
     */
    public function testGetReturnsNullForUncachedObjects(array $config)
    {
        $cache = $this->create($config);
        $this->assertNull($cache->get('uncached-object'));
    }

    /**
     * @dataProvider configProvider
     */
    public function testGetReturnsDefaultForUncachedObjects(array $config)
    {
        $cache = $this->create($config);

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
    public function testGetReturnsCachedObject(array $config, $key, $object)
    {
        $cache = $this->create($config);
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
                                . 'af2bc12271d88af325dd44d9029b197d' // "test-pool".
                                . DIRECTORY_SEPARATOR
                                . '098f6bcd4621d373cade4e832627b4f6.litecache.php'); // "test".
    }

    public function testSetCreatesSubdivisionCacheFile()
    {
        $cache = $this->create(['subdivision' => true]);
        $cache->set('test', 1234);

        $this->assertFileExists($cache->getCacheDirectory()
                                . DIRECTORY_SEPARATOR
                                . 'af2bc12271d88af325dd44d9029b197d' // "test-pool".
                                . DIRECTORY_SEPARATOR
                                . '09'
                                . DIRECTORY_SEPARATOR
                                . '098f6bcd4621d373cade4e832627b4f6.litecache.php'); // "test".
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
     * @dataProvider configProvider
     */
    public function testCacheExecutesProducerOnUncachedObject(array $config)
    {
        $cache = $this->create($config);

        $executed = false;
        $cache->cache('test', function () use (&$executed) {
            $executed = true;
            return 1234;
        });

        $this->assertTrue($executed);
    }

    /**
     * @dataProvider configProvider
     */
    public function testCacheIgnoresProducerOnUncachedObject(array $config)
    {
        $cache = $this->create($config);
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
    public function testCacheReturnsCachedObject(array $config, $key, $object)
    {
        $cache = $this->create($config);
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
                                . 'af2bc12271d88af325dd44d9029b197d' // "test-pool".
                                . DIRECTORY_SEPARATOR
                                . '098f6bcd4621d373cade4e832627b4f6.litecache.php'); // "test".
    }

    public function testCacheCreatesSubdivisionCacheFile()
    {
        $cache = $this->create(['subdivision' => true]);
        $cache->cache('test', function () {
            return 1234;
        });

        $this->assertFileExists($cache->getCacheDirectory()
                                . DIRECTORY_SEPARATOR
                                . 'af2bc12271d88af325dd44d9029b197d' // "test-pool".
                                . DIRECTORY_SEPARATOR
                                . '09'
                                . DIRECTORY_SEPARATOR
                                . '098f6bcd4621d373cade4e832627b4f6.litecache.php'); // "test".
    }

    /**
     * @dataProvider configProvider
     */
    public function testDeleteReturnsFalseOnUncachedObject(array $config)
    {
        $cache = $this->create($config);
        $this->assertFalse($cache->delete('uncached'));
    }

    public function testDeleteActuallyDeletesCacheFile()
    {
        $cache = $this->create();

        $cache->set('test', 1234);
        $this->assertFileExists($cache->getCacheDirectory()
                                . DIRECTORY_SEPARATOR
                                . 'af2bc12271d88af325dd44d9029b197d' // "test-pool".
                                . DIRECTORY_SEPARATOR
                                . '098f6bcd4621d373cade4e832627b4f6.litecache.php'); // "test".

        $cache->delete('test');

        $this->assertFileNotExists($cache->getCacheDirectory()
                                   . DIRECTORY_SEPARATOR
                                   . 'af2bc12271d88af325dd44d9029b197d' // "test-pool".
                                   . DIRECTORY_SEPARATOR
                                   . '098f6bcd4621d373cade4e832627b4f6.litecache.php'); // "test".
    }

    public function testDeleteActuallyDeletesSubdivisionCacheFile()
    {
        $cache = $this->create(['subdivision' => true]);

        $cache->set('test', 1234);
        $this->assertFileExists($cache->getCacheDirectory()
                                . DIRECTORY_SEPARATOR
                                . 'af2bc12271d88af325dd44d9029b197d' // "test-pool".
                                . DIRECTORY_SEPARATOR
                                . '09'
                                . DIRECTORY_SEPARATOR
                                . '098f6bcd4621d373cade4e832627b4f6.litecache.php'); // "test".

        $cache->delete('test');

        $this->assertFileNotExists($cache->getCacheDirectory()
                                   . DIRECTORY_SEPARATOR
                                   . 'af2bc12271d88af325dd44d9029b197d' // "test-pool".
                                   . DIRECTORY_SEPARATOR
                                   . '09'
                                   . DIRECTORY_SEPARATOR
                                   . '098f6bcd4621d373cade4e832627b4f6.litecache.php'); // "test".
    }

    /**
     * @dataProvider configProvider
     */
    public function testDeleteReturnsTrueOnSuccess(array $config)
    {
        $cache = $this->create($config);

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

    /**
     * @dataProvider configProvider
     */
    public function testClearReturnsTrueOnEmptyCache(array $config)
    {
        $cache = $this->create($config);
        $this->assertTrue($cache->clear());
    }

    public function testClearClearsAllCacheFiles()
    {
        $cache = $this->create();

        $cache->set('aaa', 1234);
        $cache->set('bbb', 'test');
        $cache->set('ccc', 3.1415);

        $this->assertNotEmpty($this->tree()
                              ['root']['.litecache']['af2bc12271d88af325dd44d9029b197d']);

        $this->assertTrue($cache->clear());

        $this->assertEmpty($this->tree()
                           ['root']['.litecache']['af2bc12271d88af325dd44d9029b197d']);
    }

    public function testClearClearsAllSubdivisionCacheFiles()
    {
        $cache = $this->create(['subdivision' => true]);

        $cache->set('aaa', 1234);
        $cache->set('bbb', 'test');
        $cache->set('ccc', 3.1415);

        $this->assertNotEmpty($this->tree()
                              ['root']['.litecache']['af2bc12271d88af325dd44d9029b197d']);

        $this->assertTrue($cache->clear());

        $this->assertEmpty($this->tree()
                           ['root']['.litecache']['af2bc12271d88af325dd44d9029b197d']);
    }

    /**
     * @dataProvider configProvider
     */
    public function testGetMultipleReturnsNullForUncachedObjects(array $config)
    {
        $cache = $this->create($config);
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

    /**
     * @dataProvider configProvider
     */
    public function testGetMultipleReturnsDefaultForUncachedObjects(array $config)
    {
        $cache = $this->create($config);
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
    public function testGetMultipleReturnsCachedObjects(array $config, array $data)
    {
        $cache = $this->create($config);
        $cache->setMultiple($data);

        $objects = $cache->getMultiple(array_keys($data));
        $this->assertEquals($data, $objects);
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
                                . 'af2bc12271d88af325dd44d9029b197d' // "test-pool".
                                . DIRECTORY_SEPARATOR
                                . '70a37754eb5a2e7db8cd887aaf11cda7.litecache.php'); // "test-1".

        $this->assertFileExists($cache->getCacheDirectory()
                                . DIRECTORY_SEPARATOR
                                . 'af2bc12271d88af325dd44d9029b197d' // "test-pool".
                                . DIRECTORY_SEPARATOR
                                . '282ff2cb3d9dadeb831bb3ba0128f2f4.litecache.php'); // "test-2".

        $this->assertFileExists($cache->getCacheDirectory()
                                . DIRECTORY_SEPARATOR
                                . 'af2bc12271d88af325dd44d9029b197d' // "test-pool".
                                . DIRECTORY_SEPARATOR
                                . '2b61ddda48445374b35a927b6ae2cd6d.litecache.php'); // "test-3".
    }

    public function testSetMultipleCreatesSubdivisionCacheFiles()
    {
        $cache = $this->create(['subdivision' => true]);

        $cache->setMultiple([
                                'test-1' => 1234,
                                'test-2' => 'test',
                                'test-3' => [10, 20, 30, 40, 50]
                            ]);

        $this->assertFileExists($cache->getCacheDirectory()
                                . DIRECTORY_SEPARATOR
                                . 'af2bc12271d88af325dd44d9029b197d' // "test-pool".
                                . DIRECTORY_SEPARATOR
                                . '70'
                                . DIRECTORY_SEPARATOR
                                . '70a37754eb5a2e7db8cd887aaf11cda7.litecache.php'); // "test-1".

        $this->assertFileExists($cache->getCacheDirectory()
                                . DIRECTORY_SEPARATOR
                                . 'af2bc12271d88af325dd44d9029b197d' // "test-pool".
                                . DIRECTORY_SEPARATOR
                                . '28'
                                . DIRECTORY_SEPARATOR
                                . '282ff2cb3d9dadeb831bb3ba0128f2f4.litecache.php'); // "test-2".

        $this->assertFileExists($cache->getCacheDirectory()
                                . DIRECTORY_SEPARATOR
                                . 'af2bc12271d88af325dd44d9029b197d' // "test-pool".
                                . DIRECTORY_SEPARATOR
                                . '2b'
                                . DIRECTORY_SEPARATOR
                                . '2b61ddda48445374b35a927b6ae2cd6d.litecache.php'); // "test-3".
    }

    /**
     * @dataProvider configProvider
     */
    public function testDeleteMultipleReturnsFalseOnUncachedObject(array $config)
    {
        $cache = $this->create($config);

        $cache->set('cached-1', 1234);
        $this->assertFalse($cache->deleteMultiple(
            [
                'cached-1',
                'uncached-1',
                'uncached-2',
                'uncached-3',
            ]));
    }

    public function testDeleteMultipleActuallyDeletesCacheFiles()
    {
        $cache = $this->create();

        $objects = [
            'test-1' => 1234,
            'test-2' => 'test',
        ];

        $cache->setMultiple($objects);

        $this->assertFileExists($cache->getCacheDirectory()
                                . DIRECTORY_SEPARATOR
                                . 'af2bc12271d88af325dd44d9029b197d' // "test-pool".
                                . DIRECTORY_SEPARATOR
                                . '70a37754eb5a2e7db8cd887aaf11cda7.litecache.php');

        $this->assertFileExists($cache->getCacheDirectory()
                                . DIRECTORY_SEPARATOR
                                . 'af2bc12271d88af325dd44d9029b197d' // "test-pool".
                                . DIRECTORY_SEPARATOR
                                . '282ff2cb3d9dadeb831bb3ba0128f2f4.litecache.php');

        $this->assertTrue($cache->deleteMultiple(array_keys($objects)));

        $this->assertFileNotExists($cache->getCacheDirectory()
                                   . DIRECTORY_SEPARATOR
                                   . 'af2bc12271d88af325dd44d9029b197d' // "test-pool".
                                   . DIRECTORY_SEPARATOR
                                   . '70a37754eb5a2e7db8cd887aaf11cda7.litecache.php');

        $this->assertFileNotExists($cache->getCacheDirectory()
                                   . DIRECTORY_SEPARATOR
                                   . 'af2bc12271d88af325dd44d9029b197d' // "test-pool".
                                   . DIRECTORY_SEPARATOR
                                   . '282ff2cb3d9dadeb831bb3ba0128f2f4.litecache.php');
    }

    public function testDeleteMultipleActuallyDeletesSubdivisionCacheFiles()
    {
        $cache = $this->create(['subdivision' => true]);

        $objects = [
            'test-1' => 1234,
            'test-2' => 'test',
        ];

        $cache->setMultiple($objects);

        $this->assertFileExists($cache->getCacheDirectory()
                                . DIRECTORY_SEPARATOR
                                . 'af2bc12271d88af325dd44d9029b197d' // "test-pool".
                                . DIRECTORY_SEPARATOR
                                . '70'
                                . DIRECTORY_SEPARATOR
                                . '70a37754eb5a2e7db8cd887aaf11cda7.litecache.php');

        $this->assertFileExists($cache->getCacheDirectory()
                                . DIRECTORY_SEPARATOR
                                . 'af2bc12271d88af325dd44d9029b197d' // "test-pool".
                                . DIRECTORY_SEPARATOR
                                . '28'
                                . DIRECTORY_SEPARATOR
                                . '282ff2cb3d9dadeb831bb3ba0128f2f4.litecache.php');

        $this->assertTrue($cache->deleteMultiple(array_keys($objects)));

        $this->assertFileNotExists($cache->getCacheDirectory()
                                   . DIRECTORY_SEPARATOR
                                   . 'af2bc12271d88af325dd44d9029b197d' // "test-pool".
                                   . DIRECTORY_SEPARATOR
                                   . '70'
                                   . DIRECTORY_SEPARATOR
                                   . '70a37754eb5a2e7db8cd887aaf11cda7.litecache.php');

        $this->assertFileNotExists($cache->getCacheDirectory()
                                   . DIRECTORY_SEPARATOR
                                   . 'af2bc12271d88af325dd44d9029b197d' // "test-pool".
                                   . DIRECTORY_SEPARATOR
                                   . '28'
                                   . DIRECTORY_SEPARATOR
                                   . '282ff2cb3d9dadeb831bb3ba0128f2f4.litecache.php');
    }

    /**
     * @dataProvider configProvider
     */
    public function testDeleteMultipleReturnsTrueOnSuccess(array $config)
    {
        $cache = $this->create($config);

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

    /**
     * @dataProvider configProvider
     */
    public function testHasReturnsTrueOnCachedObject(array $config)
    {
        $cache = $this->create($config);
        $cache->set('test', 1234);

        $this->assertTrue($cache->has('test'));
    }

    /**
     * @dataProvider configProvider
     */
    public function testHasReturnsFalseOnUncachedObject(array $config)
    {
        $cache = $this->create($config);
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

