<?php declare(strict_types=1);
/**
 * SilentByte LiteCache Library
 * @copyright 2017 SilentByte <https://silentbyte.com/>
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace SilentByte\LiteCache;

use RuntimeException;

class PathHelper
{
    private function __construct() {
        // Static Class.
    }

    public static function directory(string $path) : string {
        return rtrim($path, '/');
    }

    public static function combine(string ...$paths) : string {
        return implode(DIRECTORY_SEPARATOR, $paths);
    }

    public static function makeDirectory(string $path, int $permissions) {
        // Nothing to do if directory already exists.
        if(is_dir($path)) {
            return;
        }

        if(!mkdir($path, $permissions, true)) {
            throw new RuntimeException("Directory '{$path}' could not be created.");
        }
    }
}

