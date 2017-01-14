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
 * Provides the ability to load and parse an INI configuration file
 * and produces an object corresponding to the data defined.
 *
 * @package SilentByte\LiteCache
 */
class IniProducer
{
    /**
     * @var string
     */
    private $filename;

    /**
     * Creates a producer that loads the specified file.
     *
     * @param string $filename INI configuration file to be loaded.
     */
    public function __construct(string $filename)
    {
        $this->filename = $filename;
    }

    /**
     * Loads the INI configuration file and produces the object.
     *
     * @return mixed Object defined in the INI configuration file.
     */
    public function __invoke()
    {
        $config = @parse_ini_file($this->filename, true);

        if ($config === null) {
            throw new RuntimeException("Could not load configuration file '{$this->filename}'.");
        }

        return $config;
    }

    /**
     * Gets the filename of the INI configuration file.
     *
     * @return string INI configuration filename.
     */
    public function getFileName() : string
    {
        return $this->filename;
    }
}

