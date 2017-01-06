<?php declare(strict_types = 1);
/**
 * SilentByte LiteCache Library
 * @copyright 2017 SilentByte <https://silentbyte.com/>
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace SilentByte\LiteCache;

use RuntimeException;

class JsonProducer
{
    private $filename;

    public function __construct(string $filename)
    {
        $this->filename = $filename;
    }

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

    public function getFileName() : string
    {
        return $this->filename;
    }
}

