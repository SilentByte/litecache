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

class FileProducer
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

        return $content;
    }

    public function getFileName() : string
    {
        return $this->filename;
    }
}

