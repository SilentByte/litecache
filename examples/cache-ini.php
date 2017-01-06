<?php declare(strict_types=1);

require_once '../vendor/autoload.php';


use SilentByte\LiteCache\LiteCache;
use SilentByte\LiteCache\IniProducer;

// Create the cache object with a customized configuration.
$cache = new LiteCache([
    // Specify the caching directory.
    'directory' => '.litecache',

    // Cache objects permanently.
    'expiration' => -1
]);

// Load the specified INI configuration file and cache it.
$config = $cache->get('ini-cache', new IniProducer('./sample_data/test_ini.ini'));

echo "Host: ", $config['server']['host'], "\n",
     "User: ", $config['server']['user'], "\n",
     "Password: ", $config['server']['password'], "\n";

