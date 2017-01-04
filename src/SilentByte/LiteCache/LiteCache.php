<?php declare(strict_types=1);
/**
 * SilentByte LiteCache Library
 * @copyright 2017 SilentByte <https://silentbyte.com/>
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace SilentByte\LiteCache;

class LiteCache
{
    const DEFAULT_CONFIG = [
        'directory' => '.litecache'
    ];

    private $directory;

    private static function escapeComment($text) {
        return str_replace(['*/', "\r", "\n"], ['* /', ' ', ' '], $text);
    }

    public function __construct(array $config) {
        $config = array_merge(self::DEFAULT_CONFIG, $config);

        $this->directory = PathHelper::directory($config['directory']);
        PathHelper::makeDirectory($this->directory, 0766);
    }

    private function getCacheFileName(string $name) {
        $hash = md5($name);
        $cacheFileName = PathHelper::combine($this->directory,
                                             $hash . '.php');

        return $cacheFileName;
    }

    private function cacheObject(string $name, $object) {
        $cacheFileName = $this->getCacheFileName($name);

        $data = '<?php /* ' . self::escapeComment($name) . ' ' . date('c') . ' */' . PHP_EOL .
                'use SilentByte\LiteCache\CacheObject as stdClass;' . PHP_EOL .
                'return [' . var_export($object, true) . '];';

        if(@file_put_contents($cacheFileName, $data) === false) {
            throw new CacheException($name, $cacheFileName,
                'Cache file could not be written.');
        }
    }

    private function loadCachedObject(string $name, string $cacheFileName) {
        $value = include($cacheFileName);

        if(!$value) {
            throw new CacheException($name, $cacheFileName,
                'Cached object could not be loaded.');
        }

        // Actual value is wrapped in an array in order to distinguish
        // between 'include' returning FALSE and FALSE as a value.
        return $value[0];
    }

    public function get($name, callable $producer) {
        $cacheFileName = $this->getCacheFileName($name);
        if(file_exists($cacheFileName)) {
            return $this->loadCachedObject($name, $cacheFileName);
        }

        $object = $producer();
        $this->cacheObject($name, $object);
        return $object;
    }
}

