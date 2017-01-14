<?php
/**
 * SilentByte LiteCache Library
 *
 * @copyright 2017 SilentByte <https://silentbyte.com/>
 * @license   https://opensource.org/licenses/MIT MIT
 */

declare(strict_types = 1);

namespace SilentByte\LiteCache;

use Psr\SimpleCache\InvalidArgumentException;
use Throwable;

/**
 * Will be thrown if arguments given to caching functions are invalid or unacceptable.
 *
 * @package SilentByte\LiteCache
 */
class CacheArgumentException extends CacheException
    implements InvalidArgumentException
{
    /**
     * Creates the exception object.
     *
     * @param string         $message
     * @param Throwable|null $previous
     */
    public function __construct(string $message, Throwable $previous = null)
    {
        parent::__construct($message, $previous);
    }
}

