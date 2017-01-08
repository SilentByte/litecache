<?php declare(strict_types = 1);

require_once '../vendor/autoload.php';

use SilentByte\LiteCache\FileProducer;
use SilentByte\LiteCache\LiteCache;

// Create the cache object with a customized configuration.
$cache = new LiteCache([
    // Specify the caching directory.
    'directory' => '.litecache',

    // Cache objects permanently.
    'expiration' => -1
]);

// Load the specified file and cache it.
$content = $cache->cache('file-cache', new FileProducer('./sample_data/test_file.txt'));

echo "---- (File Content) -------------------\n";
echo $content;
echo "---------------------------------------\n";

