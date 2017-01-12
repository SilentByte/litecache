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
    /**
     * Creates the exception object.
     *
     * @param string         $message  Message indicating what caused the exception.
     * @param Exception|null $previous The exception that was the cause of this cache exception.
     */
    public function __construct(string $message,
                                Exception $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}

