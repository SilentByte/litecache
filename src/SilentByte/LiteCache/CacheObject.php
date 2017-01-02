<?php declare(strict_types=1);
/**
 * SilentByte LiteCache Library
 * @copyright 2017 SilentByte <https://silentbyte.com/>
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace SilentByte\LiteCache;

use stdClass;

class CacheObject extends stdClass
{
    public static function __set_state($properties) {
        $object = new stdClass();

        foreach($properties as $name => $value) {
            $object->$name = $value;
        }

        return $object;
    }
}

