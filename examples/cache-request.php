<?php declare(strict_types = 1);

require_once '../vendor/autoload.php';

// Create the cache object with a customized configuration.
$cache = new \SilentByte\LiteCache\LiteCache([
    // Specify the caching directory.
    'directory' => '.litecache',

    // Make cached objects expire after 10 minutes.
    'expiration' => 60 * 10
]);

// Issue a Github API request and cache it under the specified name ('git-request').
// Subsequent calls to $cache->get() will be fetched from cache;
// after expiration, a new request will be issued.
$response = $cache->cache('git-request', function () {
    $ch = curl_init('https://api.github.com/users/SilentByte');
    curl_setopt($ch, CURLOPT_USERAGENT, 'SilentByte/litecache');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    return json_decode(curl_exec($ch));
});

echo "Name: ", $response->login, "\n",
     "Website: ", $response->blog, "\n",
     "Update: ", $response->updated_at, "\n";

