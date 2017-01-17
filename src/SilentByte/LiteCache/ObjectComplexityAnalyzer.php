<?php
/**
 * SilentByte LiteCache Library
 *
 * @copyright 2017 SilentByte <https://silentbyte.com/>
 * @license   https://opensource.org/licenses/MIT MIT
 */

declare(strict_types = 1);

namespace SilentByte\LiteCache;

/**
 * Examines an object and determines whether it is 'simple' or 'complex'.
 * - Simple: null, integer, float, string, boolean, and array (only containing 'simple' objects).
 * - Complex: object, resource, and array (containing 'complex' objects).
 *
 * @package SilentByte\LiteCache
 */
class ObjectComplexityAnalyzer
{
    const UNLIMITED = -1;
    const SIMPLE = 0;
    const COMPLEX = 1;

    /**
     * @var int
     */
    private $maxEntryCount;

    /**
     * @var int
     */
    private $maxDepth;

    /**
     * @var int
     */
    private $currentDepth;

    /**
     * @var int
     */
    private $currentEntryCount;

    /**
     * Creates the object based on the specified restrictions.
     *
     * @param int $maxEntryCount Total maximum number of entries.
     * @param int $maxDepth      Total maximum depth.
     */
    public function __construct(int $maxEntryCount = self::UNLIMITED,
                                int $maxDepth = self::UNLIMITED)
    {
        $this->maxEntryCount = ($maxEntryCount !== self::UNLIMITED)
            ? $maxEntryCount : PHP_INT_MAX;

        $this->maxDepth = ($maxDepth !== self::UNLIMITED)
            ? $maxDepth : PHP_INT_MAX;
    }

    /**
     * Iterates through the object recursively and checks the type of each entry
     * until the the complexity has been determined or the previously specified
     * limits have been reached.
     *
     * @param mixed $object Object to analyze.
     *
     * @return int Either SIMPLE or COMPLEX.
     */
    private function analyzeRecursive(&$object) : int
    {
        if ($object === null
            || is_scalar($object)
        ) {
            return ObjectComplexityAnalyzer::SIMPLE;
        }

        if (is_object($object)
            || !is_array($object)
        ) {
            return ObjectComplexityAnalyzer::COMPLEX;
        }

        $this->currentDepth++;
        if ($this->currentDepth > $this->maxDepth) {
            return ObjectComplexityAnalyzer::COMPLEX;
        }

        foreach ($object as $entry) {
            $this->currentEntryCount++;
            if ($this->currentEntryCount > $this->maxEntryCount) {
                return ObjectComplexityAnalyzer::COMPLEX;
            }

            if ($this->analyzeRecursive($entry) !== ObjectComplexityAnalyzer::SIMPLE) {
                return ObjectComplexityAnalyzer::COMPLEX;
            }
        }
        $this->currentDepth--;

        return ObjectComplexityAnalyzer::SIMPLE;
    }

    /**
     * Analyzes the specified object and determines its complexity.
     *
     * @param mixed $object Object to analyze.
     *
     * @return int Either SIMPLE or COMPLEX.
     */
    public function analyze(&$object) : int
    {
        $this->currentEntryCount = 0;
        $this->currentDepth = 0;

        return $this->analyzeRecursive($object);
    }
}

