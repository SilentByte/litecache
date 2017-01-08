<?php
/**
 * SilentByte LiteCache Library
 *
 * @copyright 2017 SilentByte <https://silentbyte.com/>
 * @license   https://opensource.org/licenses/MIT MIT
 */

declare(strict_types = 1);

namespace SilentByte\LiteCache;

use stdClass;

/**
 * Class used for object serialization.
 *
 * stdClass does not implement the __set_state() magic function
 * that is required for successful deserialization of objects.
 * Since this class extends stdClass, the resulting objects may
 * be treated as regular stdClass objects.
 *
 * @package SilentByte\LiteCache
 */
class CacheObject extends stdClass
{
    /**
     * Sets all properties of the object.
     *
     * @param mixed $properties Properties to be set which are determined at runtime.
     *
     * @return CacheObject New object featuring all specified properties.
     */
    public static function __set_state($properties)
    {
        $object = new CacheObject();

        foreach ($properties as $name => $value) {
            $object->$name = $value;
        }

        return $object;
    }
}

