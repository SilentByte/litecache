<?php declare(strict_types=1);
/**
 * SilentByte LiteCache Library
 * @copyright 2017 SilentByte <https://silentbyte.com/>
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace SilentByte\LiteCache;

use Exception;

class CacheException extends Exception
{
    private $name;
    private $cache;

    public function __construct(string $name,
                                string $cache,
                                string $message,
                                Exception $previous = null)
    {
        $this->name = $name;
        $this->cache = $cache;

        parent::__construct($message, 0, $previous);
    }

    public function getName() : string {
        return $this->name;
    }

    public function getCache() : string {
        return $this->cache;
    }
}

