<?php declare(strict_types=1);

require_once '../vendor/autoload.php';


use SilentByte\LiteCache\LiteCache;
use SilentByte\LiteCache\JsonProducer;

// Create the cache object with a customized configuration.
$cache = new LiteCache([
    // Specify the caching directory.
    'directory' => '.litecache',

    // Cache objects permanently.
    'expiration' => -1
]);

// Load the specified JSON configuration file and cache it.
$config = $cache->get('json-cache', new JsonProducer('./sample_data/test_json.json'));

echo "Host: ", $config->server->host, "\n",
     "User: ", $config->server->user, "\n",
     "Password: ", $config->server->password, "\n";

