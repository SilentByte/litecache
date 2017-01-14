<?php
/**
 * SilentByte LiteCache Library
 *
 * @copyright 2017 SilentByte <https://silentbyte.com/>
 * @license   https://opensource.org/licenses/MIT MIT
 */

declare(strict_types = 1);

namespace SilentByte\LiteCache;

use Throwable;

/**
 * Will be thrown if the specified producer (for LiteCache::cache()) throws an exception.
 *
 * @package SilentByte\LiteCache
 */
class CacheProducerException extends CacheException
{
    /**
     * Creates the exception object.
     *
     * @param Throwable $previous
     */
    public function __construct(Throwable $previous)
    {
        parent::__construct('Cache producer has thrown an exception.', $previous);
    }
}

