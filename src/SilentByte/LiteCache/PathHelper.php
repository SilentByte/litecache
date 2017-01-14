<?php
/**
 * SilentByte LiteCache Library
 *
 * @copyright 2017 SilentByte <https://silentbyte.com/>
 * @license   https://opensource.org/licenses/MIT MIT
 */

declare(strict_types = 1);

namespace SilentByte\LiteCache;

use RuntimeException;

/**
 * Provides useful functions for dealing with paths and filenames.
 *
 * @package SilentByte\LiteCache
 */
final class PathHelper
{
    /**
     * Disallow instantiation (Static Class).
     */
    private function __construct()
    {
        // Static Class.
    }

    /**
     * Treats the path as a directory and removes trailing slashes.
     *
     * @param string $path Path representing a directory.
     *
     * @return string Returns the specified path without trailing slashes.
     */
    public static function directory(string $path) : string
    {
        return rtrim($path, '/\\');
    }

    /**
     * Combines the specified path parts with the system's directory separator.
     *
     * @param string[] ...$parts Parts to be combined
     *
     * @return string Resulting path including all parts.
     */
    public static function combine(string ...$parts) : string
    {
        return implode(DIRECTORY_SEPARATOR, $parts);
    }

    /**
     * Creates a directory structure recursively at the specified path
     * with the given permissions.
     *
     * @param string $path        Location of the directory.
     * @param int    $permissions Permissions to be set for the directory.
     */
    public static function makePath(string $path, int $permissions)
    {
        // Nothing to do if directory already exists.
        if (is_dir($path)) {
            return;
        }

        if (!mkdir($path, $permissions, true)) {
            throw new RuntimeException("Directory '{$path}' could not be created.");
        }
    }
}

