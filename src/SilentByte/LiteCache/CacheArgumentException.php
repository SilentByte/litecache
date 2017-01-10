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
use Psr\SimpleCache\InvalidArgumentException;

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
     * @param Exception|null $previous
     */
    public function __construct(string $message, Exception $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}

