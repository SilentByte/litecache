
Change Log
==========

## 2.0 (2017-01-26)
Version 2.0 implements major improvements in terms of the API, error handling, error reporting and logging, and general stability. Additionally, LiteCache is now [PSR-16](http://www.php-fig.org/psr/psr-16/) compliant.

### Added
- PSR-16 compatibility.
- Lots of unit tests for core functionality.
- Ability to use multiple independent caching pools (see option `pool`).
- A [PSR-3](http://www.php-fig.org/psr/psr-3/) compliant logger can now be specified to capture logging output (see option `logger`).
- Option `strategy` to tune strategy behavior.
- EXPIRE_NEVER and EXPIRE_IMMEDIATELY constants.
- `ObjectComplexityAnalyzer` class to analyze objects and choose optimal caching strategy.
- TTL values can now be specified as an integer indicating the number of seconds, as a DateInterval instance, or as a duration string (e.g. '20 seconds').
- Ability to specify an object's default value on cache miss.
- All required methods from PSR-16's CacheInterface: `get()`, `set()`, `delete()`, `clear()`, `getMultiple()`, `setMultiple()`, `deleteMultiple()`, and `has()`.
- `getCacheDirectory()` method.
- `getDefaultTimeToLive()` method.
- `OutputProducer` class to capture output.
- `FileProducer` class for easy caching of files.
- `IniProducer` class for dealing with `*.ini` files.
- `JsonProducer` class for dealing with `*.json` files.
- `CacheArgumentException` class for cache related issues with arguments.
- `CacheProducerException` class for exceptions occurring in producers.


### Changed
- Renamed option `expiration` to `ttl`.
- Renamed method `get()` to `cache()` due to name clash with PSR-16.
- Updated method `cache()` (previously `get()`).
- Updated signature of previously existing methods to comply with PSR-16.
- `CacheException` class is now base class for exceptions.
- Updated all examples accordingly.
- Changed documentation generator from PHPDoc to Sami.
- Removed `CacheObject` class which became unnecessary due to improved caching strategies.

