<?php
/**
 * SilentByte LiteCache Library
 *
 * @copyright 2017 SilentByte <https://silentbyte.com/>
 * @license https://opensource.org/licenses/MIT MIT
 */

declare(strict_types = 1);

namespace SilentByte\LiteCache;

use RuntimeException;

/**
 * Provides the ability to load a file and produces
 * a string object corresponding to the content.
 *
 * @package SilentByte\LiteCache
 */
class FileProducer
{
    private $filename;

    /**
     * Creates a producer that loads the specified file.
     *
     * @param string $filename File to be loaded.
     */
    public function __construct(string $filename)
    {
        $this->filename = $filename;
    }

    /**
     * Loads the file and produces the object.
     *
     * @return mixed The file's content.
     */
    public function __invoke()
    {
        $content = @file_get_contents($this->filename);

        if ($content === null) {
            throw new RuntimeException("Could not load file '{$this->filename}'.");
        }

        return $content;
    }

    /**
     * Gets the filename.
     *
     * @return string The file's path.
     */
    public function getFileName() : string
    {
        return $this->filename;
    }
}

