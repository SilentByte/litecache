
LiteCache 2.0
=============

This is the official repository of the SilentByte LiteCache Library.

LiteCache is a lightweight, easy-to-use, and [PSR-16](http://www.php-fig.org/psr/psr-16/) compliant caching library for PHP 7.0+ that tries to utilize PHP's built in caching mechanisms. Advanced caching systems such as [Memcached](https://memcached.org/) are often not available on low-cost hosting providers. However, code/opcode caching is normally enabled to speed up execution. LiteCache leverages this functionality by generating `*.php` files for cached objects which are then optimized and cached by the execution environment.


## Installation
The easiest way to install the latest version of LiteCache is using [Composer](https://getcomposer.org/):

```bash
$ composer require silentbyte/litecache
```

More information can be found on [Packagist](https://packagist.org/packages/silentbyte/litecache).

If you would like to check out and include the source directly without using Composer, simply clone this repository:
```bash
$ git clone https://github.com/SilentByte/litecache.git
```


## General Usage
LiteCache implements [PSR-16](http://www.php-fig.org/psr/psr-16/) and thus provides a standardized API for storing and retrieving data. The full API documentation is available here: [LiteCache 2.0 API Documentation](https://docs.silentbyte.com/litecache/).


### Caching 101
Let's get started with the following basic example that demonstrates how to load and cache an application's configuration from a JSON file.

```php
<?php
$cache = new \SilentByte\LiteCache\LiteCache();

$config = $cache->get('config');
if ($config === null) {
    $config = json_decode(file_get_contents('config.json'), true);
    $cache->set('config', $config);
}

var_dump($config);
```

The methods `$cache->get($key, $default = null)` and `$cache->set($key, $value, $ttl = null)` are used to retrieve and save the configuration from and to the cache under the unique name `config`, respecting the defined TTL. In case of a _cache miss_, the data will be loaded from the actual JSON file and is then immediately cached.

Without the cache as an intermediary layer, the JSON file would have to be loaded and parsed upon every request. LiteCache avoids this issue by utilizing PHP's code caching mechanisms.

The library is designed to cache data of any kind, including integers, floats, strings, booleans, arrays, and objects. In addition, LiteCache provides the ability to cache files and content from the output buffer to provide faster access.


### Advanced Caching
The main function for storing and retrieving objects to and from the cache is the method `$cache->cache($name, $producer, $ttl)`. The first parameter `$name` is the unique name of the object to be stored. `$producer` is a generator function that will be called if the object has expired or not yet been cached. The return value of this `callable` will be stored in the cache. `$ttl`, or _time-to-live_, defines the number of seconds before the objects expires. If `$ttl` is not specified, the cache's default time-to-live will be used (in the listed code below, that is 10 minutes).

The following example issues a Github API request using cURL and caches the result for 10 minutes. When the code is run for the first time, it will fetch the data from the Github server. Subsequent calls to the script will access the cached value without issuing a time-expensive request.

```php
<?php
// Create the cache object with a customized configuration.
$cache = new \SilentByte\LiteCache\LiteCache([
    // Specify the caching directory.
    'directory' => '.litecache',

    // Make cached objects expire after 10 minutes.
    'ttl' => '10 minutes'
]);

// Issue a Github API request and cache it under the specified name ('git-request').
// Subsequent calls to $cache->cache() will be fetched from cache;
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
```

Further examples can be found in the `./examples/` directory within this repository.


### Using Producers
LiteCache's `cache($key, $producer, $ttl)` method uses producers (implemented as function objects) that yield the data for storage in the cache. A couple of useful producers are already shipped with LiteCache, namely: `FileProducer`, `IniProducer`, `JsonProducer`, and `OutputProducer`.

Using the `IniProducer` with the following `*.ini` file...

```ini
[server]
host = myhost.test.com
user = root
password = root
```

...and this code...

```php
<?php
use SilentByte\LiteCache\IniProducer;
use SilentByte\LiteCache\LiteCache;

// Create the cache object with a customized configuration.
$cache = new LiteCache([
    // Cache objects permanently.
    'ttl' => LiteCache::EXPIRE_NEVER
]);

// Load the specified INI configuration file and cache it.
$config = $cache->cache('ini-cache', new IniProducer('./sample_data/test_ini.ini'));

echo "Host: ", $config['server']['host'], "\n",
     "User: ", $config['server']['user'], "\n",
     "Password: ", $config['server']['password'], "\n";
```

...will result in the configuration file being cached for all further calls of the script, thus avoiding unnecessary parsing on every request. The same concept applies to the other types or producers.

The same concept can be applied to cache PHP's output, e.g. caching a web page in order to avoid having to re-render it upon every request. The easiest way to achieve this is by using the integrated `OutputProducer`:

```php
<?php
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
```

All output from the included PHP script (e.g. generated via `echo`) will be cached for 30 seconds. If you are using a templating engine such as [Twig](http://twig.sensiolabs.org/), `OutputProducer` can be used to cache the rendered page. In case the data is directly available as a string, a simple call to `$cache->set($key, $value)` will suffice.

See the `./examples/` folder for more details.


### Options
LiteCache's constructor accepts an array that specifies user-defined options.

```php
// LiteCache 2.0 Default Options.
$options = [
    'directory' => '.litecache',
    'pool'      => 'default',
    'ttl'       => LiteCache::EXPIRE_NEVER,
    'logger'    => null
];

$cache = new LiteCache($options);
```

Option       | Description
-------------|-------------
directory    | Location (path) indicating where the cache files are to be stored.
pool         | Defines the name of the cache pool. A pool is a logical separation of cache objects. Cache objects in different pools are independent of each other and may thus share the same unique name. See [PSR-6 #pool](http://www.php-fig.org/psr/psr-6/#pool).
ttl          | Time-To-Live. Defines a time interval that signaling when cache objects expire by default. This value may be specified as an integer indicating seconds (e.g. 10), a time interval string (e.g '10 seconds'), an instance of DateInterval, or `LiteCache::EXPIRE_NEVER` / `LiteCache::EXPIRE_IMMEDIATELY`.
logger       | An instance of a [PSR-3](http://www.php-fig.org/psr/psr-3/) compliant logger class (implementing `\Psr\Log\LoggerInterface`) that is used to receive logging information. May be `null` if not required.


## Contribution
Unless you explicitly state otherwise, any contribution intentionally submitted for inclusion in this work by you shall be licensed under the [MIT License](https://opensource.org/licenses/MIT), without any additional terms or conditions.


## FAQ

### Under what license is LiteCache released?
MIT license. Check out `license.txt` for details. More information regarding the MIT license can be found here: <https://opensource.org/licenses/MIT>

### How do I permanently cache static files, i.e. configuration files?
Setting the `$ttl` value to `LiteCache::EXPIRE_NEVER` will cause objects to remain in the cache until the cache file is deleted manually, either by physically deleting the file or by calling `$cache->delete($key)` or `$cache->clean()`.

