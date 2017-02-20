
Change Log
==========

## 2.1.1 (2017-02-20)
Version 2.1.1 fixes several issues.

### Changed
- `setMultiple()` and `getMultiple()` now consider integers valid keys. PHP automatically coerces integral string keys to integers (see [PHP Manual](http://php.net/manual/en/language.types.array.php)), making it impossible to distinguish between the two types due to loss of type information.

### Fixed
- `delete()` and `clear()` now correctly respect the current pool.
- `clear()` now works correctly with option `subdivision` enabled.
- String `"0"` can now be used as the name for keys, pools, and cache directories.



## 2.1 (2017-02-19)
Version 2.1 is a small update adding the 'subdivision' feature.

### Added
- Option `subdivision` to place cache files in sub-directories to avoid having a large number of files in the same directory.

### Fixed
- Keys are now properly validated according to PSR-16.
- General improvements and fixes in code base and tests.



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

