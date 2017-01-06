
LiteCache
=========

This is the main repository of the SilentByte LiteCache Library.

LiteCache is a lightweight, easy-to-use, and straightforward caching library for PHP that tries to utilize PHP's built in caching mechanisms. Advanced caching systems such as [Memcached](https://memcached.org/) are often not available on cheap hosting providers while simple code / opcode caching is. LiteCache generates `*.php` files for stored objects which then can be optimized and cached by the execution environment.


## Installation

To install the latest version of LiteCache, either checkout and include the source directly or use:

```bash
$ composer require silentbyte/litecache
```


## General Usage

The main function for storing and retrieving objects to and from the cache is the method `$cache->get(string $name, callable $producer, $expiration = null)`. The first parameter `$name` is the name of the object to be stored and must be unique. `$producer` is a generator function that will be called if the object has expired or not yet been cached. The return value of the function will be stored in the cache. `$expiration` defines the number of seconds before the objects expires. If `$expiration` is not specified, the cache's default expiration duration will be used (in the code below, 10 minutes).

The following example issues a Github API request using cURL and caches the result for 10 minutes. The first time the code is run, the data is fetched from the Github server. Subsequent calls to this script will access the cached value and are thus executed much faster.

```PHP
<?php declare(strict_types=1);

require_once './vendor/autoload.php';


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
$response = $cache->get('git-request', function() {
    $ch = curl_init('https://api.github.com/users/SilentByte');
    curl_setopt($ch, CURLOPT_USERAGENT, 'SilentByte/litecache');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    return json_decode(curl_exec($ch));
});

echo "Name: ", $response->login, "\n",
     "Website: ", $response->blog, "\n",
     "Update: ", $response->updated_at, "\n";
```


### Using Producers
LiteCache uses producers (implemented as function objects) to load and prepare the data for storage in the cache. A couple of useful producers are already shipped with LiteCache, namely: `FileProducer`, `IniProducer`, and `JsonProducer`.

Using the `IniProducer` with the following configuration...

```ini
[server]
host = myhost.test.com
user = root
password = root
```

...and this code...

```php
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
```

...will result in the configuration file being cached for all further calls of the script, thus avoiding unnecessary parsing on every request. The same concept applies to the other types or producers.

See the `examples/` folder for more information.



## FAQ

### Under what license is LiteCache released?
MIT license. Check out `license.txt` for details. More information regarding the MIT license can be found here: <https://opensource.org/licenses/MIT>

### How do I permanently cache static files, i.e. configuration files?
Setting the `expiration` configuration value to `-1` will cause objects to remain in the cache until the cache file is manually deleted. This is the recommended option for configuration files.

