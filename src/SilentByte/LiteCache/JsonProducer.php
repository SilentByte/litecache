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
 * Provides the ability to load and parse a JSON file and
 * produces an object corresponding to the data defined.
 *
 * @package SilentByte\LiteCache
 */
class JsonProducer
{
    /**
     * @var string
     */
    private $filename;

    /**
     * Creates a producer that loads the specified file.
     *
     * @param string $filename JSON file to be loaded.
     */
    public function __construct(string $filename)
    {
        $this->filename = $filename;
    }

    /**
     * Loads the JSON file and produces the object.
     *
     * @return mixed Object defined in the JSON file.
     */
    public function __invoke()
    {
        $content = @file_get_contents($this->filename);

        if ($content === null) {
            throw new RuntimeException("Could not load file '{$this->filename}'.");
        }

        $json = json_decode($content);
        if ($json === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("Could not parse JSON file '{$this->filename}': " .
                                       'Reason: ' . json_last_error_msg());
        }

        return $json;
    }

    /**
     * Gets the filename of the JSON file.
     *
     * @return string JSON filename.
     */
    public function getFileName() : string
    {
        return $this->filename;
    }
}

