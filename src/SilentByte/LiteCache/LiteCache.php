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
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Psr\SimpleCache\CacheInterface;
use Throwable;
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
        'directory'   => '.litecache',
        'subdivision' => false,
        'pool'        => 'default',
        'ttl'         => LiteCache::EXPIRE_NEVER,
        'logger'      => null,
        'strategy'    => [
            'code' => [
                'entries' => 1000,
                'depth'   => 16
            ]
        ]
    ];

    /**
     * User defined path to the cache directory.
     *
     * @var string
     */
    private $cacheDirectory;

    /**
     * Indicates whether or not cache files should be placed in sub-directories.
     *
     * @var bool
     */
    private $subdivision;

    /**
     * User defined pool for this instance.
     *
     * @var string
     */
    private $pool;

    /**
     * User defined default time to live in seconds.
     *
     * @var int
     */
    private $defaultTimeToLive;

    /**
     * User defined PSR-3 compliant logger.
     *
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Used to determine whether an object is 'simple' or 'complex'.
     *
     * @var ObjectComplexityAnalyzer
     */
    private $oca;

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
     * @param string $type      Type of the export.
     * @param string $key       Unique name of the object.
     * @param int    $ttl       The object's time to live.
     * @param int    $timestamp Indicates at what point in time the object has been stored.
     *
     * @return string Header comment.
     */
    private static function generateCacheFileComment(string $type, string $key, int $ttl, int $timestamp) : string
    {
        $time = date('c', $timestamp);
        $duration = sprintf('%02d:%02d:%02d',
                            floor($ttl / 3600),
                            ($ttl / 60) % 60,
                            $ttl % 60);

        return self::escapeComment("{$type} '{$key}' {$time} {$duration}");
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
     *                      - directory: Defines the location where cache files are to be stored.
     *                      - ttl: Default time to live for cache files in seconds or a DateInterval object.
     */
    public function __construct(array $config = null)
    {
        $config = array_replace_recursive(self::DEFAULT_CONFIG,
                                          $config !== null ? $config : []);

        $this->ensureLoggerValidity($config['logger']);
        $this->logger = $config['logger'] ?? new NullLogger();

        $this->ensurePoolNameValidity($config['pool']);
        $this->pool = $config['pool'];

        $this->ensureTimeToLiveValidity($config['ttl']);
        $this->defaultTimeToLive = $this->normalizeTimeToLive($config['ttl']);

        $this->ensureCacheDirectoryValidity($config['directory']);
        $this->cacheDirectory = PathHelper::directory($config['directory']);
        $this->subdivision = (bool)$config['subdivision'];

        $this->oca = new ObjectComplexityAnalyzer($config['strategy']['code']['entries'],
                                                  $config['strategy']['code']['depth']);

        PathHelper::makePath($this->cacheDirectory, 0766);
    }

    /**
     * Ensures that the specified logger is valid, i.e. an instance of a class
     * implementing Psr\Log\LoggerInterface, or null.
     *
     * @param LoggerInterface|null $logger Logger to be checked.
     *
     * @throws CacheArgumentException
     *     If the specified logger is neither null nor an instance of a class
     *     implementing LoggerInterface.
     */
    private function ensureLoggerValidity($logger)
    {
        if ($logger !== null && !($logger instanceof LoggerInterface)) {
            throw new CacheArgumentException('The specified logger must be an instance'
                                             . ' of Psr\Log\LoggerInterface or null.');
        }
    }

    /**
     * Ensures that the specified pool name is valid.
     * If this condition is not met, CacheArgumentException will be thrown.
     *
     * @param string $pool Pool name to be checked.
     *
     * @throws CacheArgumentException
     *     If the specified pool name is invalid.
     */
    private function ensurePoolNameValidity($pool)
    {
        if (empty($pool)) {
            $this->logger->error('Pool name {pool} is invalid.', ['pool' => $pool]);
            throw new CacheArgumentException('Pool name must not be null or empty');
        }
    }

    /**
     * Ensures that the specified time to live is valid.
     * If this condition is not met, CacheArgumentException will be thrown.
     *
     * @param null|int|string|DateInterval $ttl TTL value in seconds (or as a DateInterval) where null
     *                                          indicates this cache instance's default TTL.
     *
     * @throws CacheArgumentException
     *     If the specified TTL is invalid.
     */
    private function ensureTimeToLiveValidity($ttl)
    {
        if ($ttl === null
            || is_int($ttl)
        ) {
            return;
        }

        if (is_string($ttl)
            && strtotime($ttl) !== false
        ) {
            return;
        }

        if ($ttl instanceof DateInterval) {
            return;
        }

        $this->logger->error('TTL {ttl} is invalid.', ['ttl' => $ttl]);
        throw new CacheArgumentException('Time to live is invalid.');
    }

    /**
     * Ensures that the specified cache directory is valid.
     * If this condition is not met, CacheArgumentException will be thrown.
     *
     * @param string $directory Cache directory.
     *
     * @throws CacheArgumentException
     *     If the specified cache directory is invalid.
     */
    private function ensureCacheDirectoryValidity($directory)
    {
        if (empty($directory)) {
            $this->logger->error('Directory {directory} is invalid.', ['directory' => $directory]);
            throw new CacheArgumentException('The cache directory is invalid.');
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
    private function ensureKeyValidity($key)
    {
        if (empty($key)) {
            $this->logger->error('Key {key} is invalid.', ['key' => $key]);
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
    private function ensureArrayOrTraversable($argument)
    {
        if (!is_array($argument) && !$argument instanceof Traversable) {
            $this->logger->error('Argument is neither an array nor a Traversable');
            throw new CacheArgumentException('Argument is neither an array nor a Traversable.');
        }
    }

    /**
     * Normalizes the given TTL (time to live) value to an integer representing
     * the number of seconds an object is to be cached.
     *
     * @param null|int|string|DateInterval $ttl TTL value in seconds (or as a DateInterval) where null
     *                                          indicates this cache instance's default TTL.
     *
     * @return int TTL in seconds.
     */
    private function normalizeTimeToLive($ttl) : int
    {
        if ($ttl === null) {
            return $this->defaultTimeToLive;
        } else if (is_string($ttl)) {
            return self::dateIntervalToSeconds(DateInterval::createFromDateString($ttl));
        } else if ($ttl instanceof DateInterval) {
            return self::dateIntervalToSeconds($ttl);
        } else {
            $ttl = (int)$ttl;
            return $ttl;
        }
    }

    /**
     * Checks whether the specified object is 'simple' or 'complex'.
     *
     * - Simple: null, integer, float, string, boolean, and array (only containing 'simple' objects).
     * - Complex: object, resource, and array (containing 'complex' objects).
     *
     * @param mixed $object Object to be analyzed.
     *
     * @return bool True if the specified object is considered 'simple', false otherwise.
     */
    private function isSimpleObject($object) : bool
    {
        return $this->oca->analyze($object) === ObjectComplexityAnalyzer::SIMPLE;
    }

    /**
     * Computes and returns the hash for the specified key.
     * The hash is used to to name the cache file.
     *
     * @param string $key Unique name of the object.
     *
     * @return string
     */
    private function getKeyHash(string $key) : string
    {
        // Add extra character in order to avoid an overlap between pool
        // and key. Pool 'ab' and key 'xy' would otherwise be indistinguishable
        // from pool 'a' and key 'bxy'.
        return md5($this->pool . '|' . $key);
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
        $hash = $this->getKeyHash($key);
        if ($this->subdivision) {
            // Take the first two characters of the hashed key as the name of the sub-directory.
            $cacheFileName = PathHelper::combine($this->cacheDirectory,
                                                 substr($hash, 0, 2),
                                                 $hash . '.litecache.php');
        } else {
            $cacheFileName = PathHelper::combine($this->cacheDirectory,
                                                 $hash . '.litecache.php');
        }

        return $cacheFileName;
    }

    /**
     * Creates the sub-directory for the specified cache file.
     *
     * @param string $filename The filename of the cache file.
     */
    private function createCacheSubDirectory(string $filename)
    {
        if ($this->subdivision) {
            PathHelper::makePath(dirname($filename), 0766);
        }
    }

    /**
     * Writes data into the specified file using an exclusive lock.
     *
     * @param string   $key   Unique name of the object.
     * @param string[] $parts Array of strings, where each entry will be written
     *                        into the file in consecutive order.
     *
     * @return bool True on success and false on failure.
     *
     */
    private function writeDataToFile(string $key, array $parts) : bool
    {
        $filename = $this->getCacheFileName($key);
        $this->createCacheSubDirectory($filename);

        if (!$fp = @fopen($filename, 'c')) {
            $this->logger->error('File {filename} could not be created.', ['filename' => $filename]);
            return false;
        }

        if (!flock($fp, LOCK_EX)) {
            $this->logger->error('Lock on file {filename} could not be acquired.', ['filename' => $filename]);
            fclose($fp);
            return false;
        } else {
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
        $comment = self::generateCacheFileComment('code', $key, $ttl, $timestamp);
        $condition = self::generateCacheFileExpirationCondition($ttl, $timestamp);

        $export = var_export($object, true);
        $code = "<?php /* {$comment} */" . PHP_EOL
            . "return ({$condition}) ? null : [{$export}];";

        return $this->writeDataToFile($key, [$code]);
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
        $comment = self::generateCacheFileComment('serialized', $key, $ttl, $timestamp);
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

        return $this->writeDataToFile($key, [$code, serialize($object)]);
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
        if ($this->isSimpleObject($object)) {
            $this->logger->info('Object {key} is \'simple\'.', ['key' => $key]);
            $result = $this->writeCodeCache($key, $object, $ttl, time());
        } else {
            $this->logger->info('Object {key} is \'complex\'.', ['key' => $key]);
            $result = $this->writeSerializedCache($key, $object, $ttl, time());
        }

        if (!$result) {
            $this->logger->error('Object {key} could not be cached.', ['key' => $key]);
        }

        return $result;
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
            $this->logger->notice('Cache miss on object {key}.', ['key' => $key]);

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
        $this->ensureKeyValidity($key);
        $object = $this->loadObject($key);

        if ($object === null) {
            return $default;
        }

        $this->logger->info('Object {key} loaded from cache.', ['key' => $key]);
        return $object;
    }

    /**
     * Persists data in the cache, uniquely referenced by a key with an optional expiration TTL time.
     *
     * @param string                       $key   The key of the item to store.
     * @param mixed                        $value The value of the item to store, must be serializable.
     * @param null|int|string|DateInterval $ttl   Optional. The TTL value of this item. If no value is sent and
     *                                            the driver supports TTL then the library may set a default value
     *                                            for it or let the driver take care of that.
     *
     * @return bool True on success and false on failure.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *     MUST be thrown if the $key string is not a legal value.
     */
    public function set($key, $value, $ttl = null)
    {
        $this->ensureKeyValidity($key);

        // According to PSR-16, it is not possible to distinguish between
        // null and a cache miss; there is no need to store null.
        if ($value === null) {
            $this->logger->info('Object {key} skipped (was null).', ['key' => $key]);
            return true;
        }

        $ttl = $this->normalizeTimeToLive($ttl);
        $result = $this->storeObject($key, $value, $ttl);

        if ($result) {
            $this->logger->info('Object {key} cached.', ['key' => $key]);
        }

        return $result;
    }

    /**
     * Gets the object with the specified name. If the object has been previously cached
     * and has not expired yet, the cached version will be returned. If the object has not
     * been previously cached or the cache file has expired, the specified producer will be
     * called and the new version will be cached and returned.
     *
     * @param string                       $key      Unique name of the object.
     * @param callable                     $producer Producer that will be called to generate the data
     *                                               if the cached object has expired.
     * @param null|int|string|DateInterval $ttl      The TTL (time to live) value for the object.
     *
     * @return mixed The cached object or a newly created version if it has expired.
     *
     * @throws CacheArgumentException
     *     If the object could not be cached or loaded.
     *
     * @throws CacheProducerException
     *     If the specified producers throws an exception.
     */
    public function cache(string $key, callable $producer, $ttl = null)
    {
        $this->ensureKeyValidity($key);

        $object = $this->get($key);
        if ($object !== null) {
            // Object's still in cache and has not expired.
            return $object;
        } else {

            try {
                // If object is not cached or has expired, call producer to obtain
                // the new value and subsequently cache it.
                $object = $producer();
            } catch (Throwable $t) {
                $this->logger->error('Producer for {key} has thrown an exception.', ['key' => $key]);
                throw new CacheProducerException($t);
            }

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
        $this->ensureKeyValidity($key);

        $cacheFileName = $this->getCacheFileName($key);
        if (!@unlink($cacheFileName)) {
            $this->logger->error('Object {key} could not be deleted.', ['key' => $key]);
            return false;
        }

        return true;
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
                    $this->logger->error('Cache file {filename} could not be deleted.',
                                         ['filename' => $file->getFilename()]);
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
        $this->ensureArrayOrTraversable($keys);

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
     * @param mixed                        $values A list of key => value pairs for a multiple-set operation.
     * @param null|int|string|DateInterval $ttl    Optional. The TTL value of this item. If no value is sent and
     *                                             the driver supports TTL then the library may set a default value
     *                                             for it or let the driver take care of that.
     *
     * @return bool True on success and false on failure.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *     MUST be thrown if $values is neither an array nor a Traversable,
     *     or if any of the $values are not a legal value.
     */
    public function setMultiple($values, $ttl = null)
    {
        $this->ensureArrayOrTraversable($values);

        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                return false;
            }
        }

        return true;
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
        $this->ensureArrayOrTraversable($keys);

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
        $this->ensureKeyValidity($key);

        $cacheFileName = $this->getCacheFileName($key);
        return file_exists($cacheFileName);
    }
}

