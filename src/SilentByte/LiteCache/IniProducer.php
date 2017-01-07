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

class IniProducer
{
    private $filename;

    public function __construct(string $filename)
    {
        $this->filename = $filename;
    }

    public function __invoke()
    {
        $config = @parse_ini_file($this->filename, true);

        if ($config === null) {
            throw new RuntimeException("Could not load configuration file '{$this->filename}'.");
        }

        return $config;
    }

    public function getFileName() : string
    {
        return $this->filename;
    }
}

