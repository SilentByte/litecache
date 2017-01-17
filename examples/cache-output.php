<?php declare(strict_types = 1);

require_once '../vendor/autoload.php';

use SilentByte\LiteCache\LiteCache;
use SilentByte\LiteCache\OutputProducer;

// Create the cache object with a customized configuration.
$cache = new LiteCache([
    // Specify the caching directory.
    'directory' => '.litecache',

    // Cache objects for 30 seconds.
    'ttl'       => '30 seconds'
]);

// Load the specified file and cache it.
$output = $cache->cache('script-cache', new OutputProducer(function () {
    include('./sample_data/slow_script.php');
}));

echo "---- (Script Output) -------------------\n";
echo $output;
echo "---------------------------------------\n";

