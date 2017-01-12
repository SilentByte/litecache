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
use DirectoryIterator;
use Psr\SimpleCache\CacheInterface;
use Traversable;

/**
 * Main class of the LiteCache library that allows the user to cache and load objects
 * into and from PHP cache files which can be optimized by the execution environment.
 *
 * @package SilentByte\LiteCache
 */
class LiteCache implements CacheInterface
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
        'directory' => '.litecache',
        'ttl'       => LiteCache::EXPIRE_NEVER
    ];

    /**
     * User defined path to the cache directory.
     *
     * @var string
     */
    private $cacheDirectory;

    /**
     * User defined default time to live in seconds.
     *
     * @var int
     */
    private $defaultTimeToLive;

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
     * Generates the header comment for cache file, which includes key, timestamp and TTL.
     *
     * @param string $key       Unique name of the object.
     * @param int    $ttl       The object's time to live.
     * @param int    $timestamp Indicates at what point in time the object has been stored.
     *
     * @return string Header comment.
     */
    private static function generateCacheFileComment(string $key, int $ttl, int $timestamp) : string
    {
        return self::escapeComment($key . ' ' . date('c', $timestamp) . ' ' . $ttl);
    }

    /**
     * Generates a condition (resulting in a boolean) that indicates whether
     * the cache file has expired or not.
     *
     * @param int $ttl       The object's time to live.
     * @param int $timestamp Indicates at what point in time the object has been stored.
     *
     * @return string A string representing a valid PHP boolean condition.
     */
    private static function generateCacheFileExpirationCondition(int $ttl, int $timestamp) : string
    {
        if ($ttl === self::EXPIRE_NEVER) {
            return 'false';
        } else {
            $relativeTtl = $timestamp + $ttl;
            return "time() > {$relativeTtl}";
        }
    }

    /**
     * Ensures that the specified string is valid cache key.
     * If this condition is not met, CacheArgumentException will be thrown.
     *
     * @param mixed $key Key to be checked.
     *
     * @throws CacheArgumentException
     *     If the specified key is neither an array nor a Traversable.
     */
    private static function ensureKeyValidity($key)
    {
        if (empty($key)) {
            throw new CacheArgumentException('Key must not be null or empty.');
        }
    }

    /**
     * Ensures that the specified argument is either an array or an instance of Traversable.
     * If these conditions are not met, CacheArgumentException will be thrown.
     *
     * @param mixed $argument Argument to be checked.
     *
     * @throws CacheArgumentException
     *     If the specified argument is neither an array nor a Traversable.
     */
    private static function ensureArrayOrTraversable($argument)
    {
        if (!is_array($argument) && !$argument instanceof Traversable) {
            throw new CacheArgumentException('Argument is neither an array nor a Traversable.');
        }
    }

    /**
     * Converts the specified DateInterval instance to a value indicating
     * the number of seconds within that interval.
     *
     * @param DateInterval $interval Interval to be converted to seconds.
     *
     * @return int Number of seconds in the interval.
     */
    private static function dateIntervalToSeconds(DateInterval $interval) : int
    {
        return ($interval->s                        // Seconds.
            + ($interval->i * 60)                   // Minutes.
            + ($interval->h * 60 * 60)              // Hours.
            + ($interval->d * 60 * 60 * 24)         // Days.
            + ($interval->m * 60 * 60 * 24 * 30)    // Months.
            + ($interval->y * 60 * 60 * 24 * 365)); // Years.
    }

    /**
     * Creates the object based on the specified configuration.
     *
     * @param array $config Passes in the user's cache configuration.
     *                      * directory: Defines the location where cache files are to be stored.
     *                      * ttl: Default time to live for cache files in seconds.
     */
    public function __construct(array $config = null)
    {
        $config = array_merge(self::DEFAULT_CONFIG,
                              $config !== null ? $config : []);

        $this->cacheDirectory = PathHelper::directory($config['directory']);
        $this->defaultTimeToLive = (int)$config['ttl'];

        PathHelper::makeDirectory($this->cacheDirectory, 0766);
    }

    /**
     * Normalizes the given TTL (time to live) value to an integer representing
     * the number of seconds an object is to be cached.
     *
     * @param null|int|DateInterval $ttl TTL value in seconds (or as a DateInterval) where null
     *                                   indicates this cache instance's default TTL.
     *
     * @return int TTL in seconds.
     */
    private function normalizeTimeToLive($ttl) : int
    {
        if ($ttl === null) {
            return $this->defaultTimeToLive;
        } else {
            if ($ttl instanceof DateInterval) {
                $ttl = self::dateIntervalToSeconds($ttl);
                return $ttl;
            } else {
                $ttl = (int)$ttl;
                return $ttl;
            }
        }
    }

    /**
     * Computes and returns the hash for the specified key.
     * The hash is used to to name the cache file.
     *
     * @param string $key Unique name of the object.
     *
     * @return string
     */
    private static function getKeyHash(string $key) : string
    {
        return md5($key);
    }

    /**
     * Computes the filename of the cache file for the specified object.
     *
     * @param string $key Unique name of the object.
     *
     * @return string The filename of the cache file including *.php extension.
     */
    private function getCacheFileName(string $key) : string
    {
        $hash = self::getKeyHash($key);
        $cacheFileName = PathHelper::combine($this->cacheDirectory,
                                             $hash . '.litecache.php');

        return $cacheFileName;
    }

    /**
     * Writes data into the specified file using an exclusive lock.
     *
     * @param string   $filename Target filename.
     * @param string[] $parts    Array of strings, where each entry will be written
     *                           into the file in consecutive order.
     *
     * @return bool True on success and false on failure.
     *
     */
    private function writeDataToFile(string $filename, array $parts) : bool
    {
        if (!$fp = @fopen($filename, 'c')) {
            return false;
        }

        if (flock($fp, LOCK_EX)) {
            ftruncate($fp, 0);

            foreach ($parts as $part) {
                fwrite($fp, $part);
            }

            fflush($fp);
            flock($fp, LOCK_UN);
        }

        fclose($fp);
        return true;
    }

    /**
     * Exports the object into its cache file using 'var_export()'.
     *
     * @param string $key       Unique name of the object.
     * @param mixed  $object    Actual value to be cached.
     * @param int    $ttl       Time to live in seconds.
     * @param int    $timestamp Indicates at what point in time the object has been stored.
     *
     * @return bool True on success, false on failure.
     */
    private function writeCodeCache(string $key, $object, int $ttl, int $timestamp) : bool
    {
        $comment = self::generateCacheFileComment($key, $ttl, $timestamp);
        $condition = self::generateCacheFileExpirationCondition($ttl, $timestamp);

        $export = var_export($object, true);
        $code = "<?php /* {$comment} */" . PHP_EOL
            . "return ({$condition}) ? null : [{$export}];";

        return $this->writeDataToFile($this->getCacheFileName($key), [$code]);
    }

    /**
     * Exports the object into its cache file using 'serialize()'.
     *
     * @param string $key       Unique name of the object.
     * @param mixed  $object    Actual value to be cached.
     * @param int    $ttl       Time to live in seconds.
     * @param int    $timestamp Indicates at what point in time the object has been stored.
     *
     * @return bool True on success, false on failure.
     */
    private function writeSerializedCache(string $key, $object, int $ttl, int $timestamp) : bool
    {
        $comment = self::generateCacheFileComment($key, $ttl, $timestamp);
        $condition = self::generateCacheFileExpirationCondition($ttl, $timestamp);

        $code = "<?php /* {$comment} */" . PHP_EOL
            . "return ({$condition}) ? null : (function() {" . PHP_EOL
            . "    static \$data = null;" . PHP_EOL
            . "    if(\$data) {" . PHP_EOL
            . "        return \$data;" . PHP_EOL
            . "    } else {" . PHP_EOL
            . "        \$data = [unserialize(file_get_contents(__FILE__," . PHP_EOL
            . "                                                false," . PHP_EOL
            . "                                                null," . PHP_EOL
            . "                                                __COMPILER_HALT_OFFSET__))];" . PHP_EOL
            . "        return \$data;" . PHP_EOL
            . "    }" . PHP_EOL
            . "})();" . PHP_EOL
            . "__halt_compiler();";

        return $this->writeDataToFile($this->getCacheFileName($key),
                                      [$code, serialize($object)]);
    }

    /**
     * Caches (i.e. persists) the object under the given name with the specified TTL (time to live).
     *
     * @param string $key    Unique name of the object.
     * @param mixed  $object Actual value to be cached.
     * @param int    $ttl    Time to live in seconds or null for persistent caching.
     *
     * @return bool True on success and false on failure.
     */
    private function storeObject(string $key, $object, int $ttl) : bool
    {
        // TODO: Distinguish between complex and simple objects.
        if ($object === null
            || is_bool($object)
            || is_int($object)
            || is_float($object)
            || is_string($object)
        ) {
            return $this->writeCodeCache($key, $object, $ttl, time());
        } else {
            return $this->writeSerializedCache($key, $object, $ttl, time());
        }
    }

    /**
     * Loads the cached object from the cache file.
     *
     * @param string $key Unique name of the object.
     *
     * @return mixed The cached object's value.
     */
    private function loadObject(string $key)
    {
        $cacheFileName = $this->getCacheFileName($key);

        /** @noinspection PhpIncludeInspection */
        $value = @include($cacheFileName);
        if (!$value) {
            // As per PSR-16, cache misses result in null.
            return null;
        }

        // Actual value is wrapped in an array in order to distinguish
        // between 'include' returning FALSE and FALSE as a value.
        return $value[0];
    }

    /**
     * Gets the user defined cache directory.
     *
     * @return string
     */
    public function getCacheDirectory() : string
    {
        return $this->cacheDirectory;
    }

    /**
     * Gets the user defined default TTL (time to live) in seconds.
     *
     * @return int
     */
    public function getDefaultTimeToLive() : int
    {
        return $this->defaultTimeToLive;
    }

    /**
     * Fetches a value from the cache.
     *
     * @param string $key     The unique key of this item in the cache.
     * @param mixed  $default Default value to return if the key does not exist.
     *
     * @return mixed The value of the item from the cache, or $default in case of cache miss.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *     MUST be thrown if the $key string is not a legal value.
     */
    public function get($key, $default = null)
    {
        self::ensureKeyValidity($key);
        $object = $this->loadObject($key);

        if ($object === null) {
            return $default;
        }

        return $object;
    }

    /**
     * Persists data in the cache, uniquely referenced by a key with an optional expiration TTL time.
     *
     * @param string                $key   The key of the item to store.
     * @param mixed                 $value The value of the item to store, must be serializable.
     * @param null|int|DateInterval $ttl   Optional. The TTL value of this item. If no value is sent and
     *                                     the driver supports TTL then the library may set a default value
     *                                     for it or let the driver take care of that.
     *
     * @return bool True on success and false on failure.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *     MUST be thrown if the $key string is not a legal value.
     */
    public function set($key, $value, $ttl = null)
    {
        self::ensureKeyValidity($key);

        $ttl = $this->normalizeTimeToLive($ttl);
        return $this->storeObject($key, $value, $ttl);
    }

    /**
     * Gets the object with the specified name. If the object has been previously cached
     * and has not expired yet, the cached version will be returned. If the object has not
     * been previously cached or the cache file has expired, the specified producer will be
     * called and the new version will be cached and returned.
     *
     * @param string                $key        Unique name of the object.
     * @param callable              $producer   Producer that will be called to generate the data
     *                                          if the cached object has expired.
     * @param null|int|DateInterval $ttl        The TTL (time to live) value for the object.
     *
     * @return mixed The cached object or a newly created version if it has expired.
     *
     * @throws CacheArgumentException
     *     If the object could not be cached or loaded.
     */
    public function cache(string $key, callable $producer, $ttl = null)
    {
        self::ensureKeyValidity($key);

        $object = $this->get($key);
        if ($object !== null) {
            // Object's still in cache and has not expired.
            return $object;
        } else {
            // If object is not cached or has expired, call producer to obtain
            // the new value and subsequently cache it.
            $object = $producer();
            $this->set($key, $object, $ttl);

            return $object;
        }
    }

    /**
     * Delete an item from the cache by its unique key.
     *
     * @param string $key The unique cache key of the item to delete.
     *
     * @return bool True if the item was successfully removed.
     *              False if there was an error.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *     MUST be thrown if the $key string is not a legal value.
     */
    public function delete($key)
    {
        self::ensureKeyValidity($key);

        $cacheFileName = $this->getCacheFileName($key);
        return @unlink($cacheFileName);
    }

    /**
     * Wipes clean the entire cache's keys.
     *
     * @return bool True on success and false on failure.
     */
    public function clear()
    {
        $iterator = new DirectoryIterator($this->cacheDirectory);

        foreach ($iterator as $file) {
            if (!$file->isDot()
                && preg_match('/[0-9a-f]{32}\\.litecache.php/', $file->getFilename())
            ) {
                if (!unlink($file->getPathname())) {
                    return false;
                }
            }
        }

        return true;
    }

    /** @noinspection PhpUndefinedClassInspection */
    /**
     * Obtains multiple cache items by their unique keys.
     *
     * @param mixed $keys    A list of keys that can obtained in a single operation.
     * @param mixed $default Default value to return for keys that do not exist.
     *
     * @return mixed A list of key => value pairs. Cache keys that do not exist or are stale will have $default as value.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *     MUST be thrown if $keys is neither an array nor a Traversable,
     *     or if any of the $keys are not a legal value.
     */
    public function getMultiple($keys, $default = null)
    {
        self::ensureArrayOrTraversable($keys);

        $objects = [];
        foreach ($keys as $key) {
            $objects[$key] = $this->get($key, $default);
        }

        return $objects;
    }

    /** @noinspection PhpUndefinedClassInspection */
    /**
     * Persists a set of key => value pairs in the cache, with an optional TTL.
     *
     * @param mixed                 $values A list of key => value pairs for a multiple-set operation.
     * @param null|int|DateInterval $ttl    Optional. The TTL value of this item. If no value is sent and
     *                                      the driver supports TTL then the library may set a default value
     *                                      for it or let the driver take care of that.
     *
     * @return bool True on success and false on failure.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *     MUST be thrown if $values is neither an array nor a Traversable,
     *     or if any of the $values are not a legal value.
     */
    public function setMultiple($values, $ttl = null)
    {
        self::ensureArrayOrTraversable($values);

        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }
    }

    /** @noinspection PhpUndefinedClassInspection */
    /**
     * Deletes multiple cache items in a single operation.
     *
     * @param mixed $keys A list of string-based keys to be deleted.
     *
     * @return bool True if the items were successfully removed. False if there was an error.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *     MUST be thrown if $keys is neither an array nor a Traversable,
     *     or if any of the $keys are not a legal value.
     */
    public function deleteMultiple($keys)
    {
        self::ensureArrayOrTraversable($keys);

        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determines whether an item is present in the cache.
     *
     * NOTE: It is recommended that has() is only to be used for cache warming type purposes
     * and not to be used within your live applications operations for get/set, as this method
     * is subject to a race condition where your has() will return true and immediately after,
     * another script can remove it making the state of your app out of date.
     *
     * @param string $key The cache item key.
     *
     * @return bool
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *     MUST be thrown if the $key string is not a legal value.
     */
    public function has($key)
    {
        self::ensureKeyValidity($key);

        $cacheFileName = $this->getCacheFileName($key);
        return file_exists($cacheFileName);
    }
}

