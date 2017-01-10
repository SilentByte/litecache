<?php
/**
 * SilentByte LiteCache Library
 *
 * @copyright 2017 SilentByte <https://silentbyte.com/>
 * @license   https://opensource.org/licenses/MIT MIT
 */

declare(strict_types = 1);

namespace SilentByte\LiteCache;

use Exception;
use Psr\SimpleCache\CacheException as PsrCacheException;

/**
 * Base class for all cache related exceptions.
 *
 * @package SilentByte\LiteCache
 */
class CacheException extends Exception
    implements PsrCacheException
{
    private $name;
    private $cache;

    /**
     * Creates the exception object.
     *
     * @param string         $name     Unique name of the cache object.
     * @param string         $cache    Name of the cache file.
     * @param string         $message  Message indicating what caused the exception to be thrown.
     * @param Exception|null $previous The exception that was the cause of this cache exception.
     */
    public function __construct(string $name,
                                string $cache,
                                string $message,
                                Exception $previous = null)
    {
        $this->name = $name;
        $this->cache = $cache;

        parent::__construct($message, 0, $previous);
    }

    /**
     * Gets the unique name of the object that caused the exception.
     *
     * @return string
     */
    public function getName() : string
    {
        return $this->name;
    }

    /**
     * Gets the cache name of the object that caused the exception.
     *
     * @return string
     */
    public function getCache() : string
    {
        return $this->cache;
    }
}

