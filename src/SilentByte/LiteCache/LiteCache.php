<?php
/**
 * SilentByte LiteCache Library
 *
 * @copyright 2017 SilentByte <https://silentbyte.com/>
 * @license   https://opensource.org/licenses/MIT MIT
 */

declare(strict_types = 1);

namespace SilentByte\LiteCache;

use DirectoryIterator;
use SplFileInfo;
use UnexpectedValueException;

/**
 * Main class of the LiteCache library that allows the user to cache and load objects
 * into and from PHP cache files which can be optimized by the execution environment.
 *
 * @package SilentByte\LiteCache
 */
class LiteCache
{
    /**
     * Indicates that objects cached with this setting will never expire
     * and must thus be deleted manually to trigger an update.
     */
    const EXPIRE_NEVER = -1;

    /**
     * Indicates that objects cached with this setting expire immediately.
     * This setting can also be used to manually trigger an update.
     */
    const EXPIRE_IMMEDIATELY = 0;

    /**
     * Specifies the default configuration.
     */
    const DEFAULT_CONFIG = [
        'directory'  => '.litecache',
        'expiration' => -1
    ];

    /**
     * User defined path to the cache directory.
     * @var string
     */
    private $directory;

    /**
     * User defined default expiration (TTL, time to live).
     * @var int
     */
    private $expiration;

    /**
     * Escapes the given text so that it can be placed inside a PHP multi-line comment.
     *
     * @param string $text Text to be escaped.
     *
     * @return string Escaped text.
     */
    private static function escapeComment(string $text) : string
    {
        return str_replace(['*/', "\r", "\n"],
                           ['* /', ' ', ' '],
                           $text);
    }

    /**
     * Creates the object based on the specified configuration.
     *
     * @param array $config Passes in the user's cache configuration.
     *                      * directory: Defines the location where cache files are to be stored.
     *                      * expiration: Default TTL (time to live) for cache files in seconds.
     */
    public function __construct(array $config)
    {
        $config = array_merge(self::DEFAULT_CONFIG, $config);

        $this->directory = PathHelper::directory($config['directory']);
        $this->expiration = (int)$config['expiration'];

        PathHelper::makeDirectory($this->directory, 0766);
    }

    /**
     * Computes the name of the cache file for the specified object.
     *
     * @param string $name Unique name of the object.
     *
     * @return string The filename of the cache file including *.php extension.
     */
    private function getCacheFileName(string $name) : string
    {
        $hash = md5($name);
        $cacheFileName = PathHelper::combine($this->directory,
                                             $hash . '.php');

        return $cacheFileName;
    }

    /**
     * Gets a boolean value indicating whether the specified
     * expiration time is past the current time.
     *
     * @param int      $timestamp  Timestamp when the object has been created.
     * @param int|null $expiration The object's time to live. If null is specified,
     *                             the cache's default expiration time will be used.
     *
     * @return bool
     */
    private function hasExpired(int $timestamp, $expiration) : bool
    {
        if ($expiration === null) {
            $expiration = $this->expiration;
        }

        if ($expiration < 0) {
            return false;
        }

        return time() > $timestamp + $expiration;
    }

    /**
     * Caches (i.e. persists) the specified object under the given name.
     *
     * @param string $name   Unique name of the object.
     * @param mixed  $object Actual value to be cached.
     *
     * @throws CacheException If the cache file could not be created.
     */
    private function cacheObject(string $name, $object)
    {
        $cacheFileName = $this->getCacheFileName($name);

        $data = '<?php /* ' . self::escapeComment($name) . ' ' . date('c') . ' */' . PHP_EOL
            . 'use SilentByte\LiteCache\CacheObject as stdClass;' . PHP_EOL
            . 'return [' . var_export($object, true) . '];';

        if (@file_put_contents($cacheFileName, $data) === false) {
            throw new CacheException($name, $cacheFileName,
                                     'Cache file could not be written.');
        }
    }

    /**
     * Loads the cached object from the cache file.
     *
     * @param string $name          Unique name of the object.
     * @param string $cacheFileName Filename of the object's cache file.
     *
     * @return mixed The cached object's value.
     *
     * @throws CacheException If the cache file was corrupted or the data could not be loaded.
     */
    private function loadCachedObject(string $name, string $cacheFileName)
    {
        $value = include($cacheFileName);

        if (!$value) {
            throw new CacheException($name, $cacheFileName,
                                     'Cached object could not be loaded.');
        }

        // Actual value is wrapped in an array in order to distinguish
        // between 'include' returning FALSE and FALSE as a value.
        return $value[0];
    }

    /**
     * Gets the object with the specified name. If the object has been previously cached,
     * the cached version will be returned if it has not yet expired. If the object has not
     * been previously cached or the cache file has expired, the specified producer will be
     * called and the new version will be cached and returned.
     *
     * @param string   $name       Unique name of the object.
     * @param callable $producer   Producer that will be called to generate the data
     *                             if the cached object has expired.
     * @param null     $expiration Sets the TTL (time to live) for the cache object.
     *                             If not specified, the default TTL will be used.
     *
     * @return mixed The cached object or a newly created version if it has expired.
     *
     * @throws CacheException If the object could not be cached or loaded.
     */
    public function cache(string $name, callable $producer, $expiration = null)
    {
        if (empty($name)) {
            throw new UnexpectedValueException('Cache object name must not be null or empty.');
        }

        $cacheFileName = $this->getCacheFileName($name);
        $file = new SplFileInfo($cacheFileName);

        // Load from cache if cache file exists and has not expired yet.
        if ($file->isFile() && !$this->hasExpired($file->getMTime(), $expiration)) {
            return $this->loadCachedObject($name, $cacheFileName);
        }

        $object = $producer();
        $this->cacheObject($name, $object);
        return $object;
    }

    /**
     * Indicates whether a cache file for the object with the specified name exists.
     *
     * @param string $name Unique name of the object.
     *
     * @return bool True if a cached version of the object exists, false otherwise.
     */
    public function has(string $name)
    {
        $cacheFileName = $this->getCacheFileName($name);
        return file_exists($cacheFileName);
    }

    /**
     * Deletes the cache file for the specified object so that subsequent accesses
     * to the object will trigger an update.
     *
     * @param string $name Unique name of the object.
     */
    public function delete(string $name)
    {
        if ($this->has($name)) {
            unlink($this->getCacheFileName($name));
        }
    }

    /**
     * Deletes all cache files from the cache directory.
     */
    public function clear()
    {
        $iterator = new DirectoryIterator($this->directory);
        foreach ($iterator as $file) {
            if (!$file->isDot() && $file->getExtension() === 'php') {
                unlink($file->getPathname());
            }
        }
    }
}

