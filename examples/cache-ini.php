<?php declare(strict_types = 1);

require_once '../vendor/autoload.php';

use SilentByte\LiteCache\IniProducer;
use SilentByte\LiteCache\LiteCache;

// Create the cache object with a customized configuration.
$cache = new LiteCache([
    // Specify the caching directory.
    'directory' => '.litecache',

    // Cache objects permanently.
    'expiration' => -1
]);

// Load the specified INI configuration file and cache it.
$config = $cache->cache('ini-cache', new IniProducer('./sample_data/test_ini.ini'));

echo "Host: ", $config['server']['host'], "\n",
     "User: ", $config['server']['user'], "\n",
     "Password: ", $config['server']['password'], "\n";

