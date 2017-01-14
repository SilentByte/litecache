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
 * Provides the ability to cache the output stream
 * and subsequently cache it.
 *
 * @package SilentByte\LiteCache
 */
class OutputProducer
{
    /**
     * @var callable
     */
    private $producer;

    /**
     * Creates a producer that buffers the output producer by the specified producer.
     *
     * @param callable $producer User defined callable that writes content onto the output stream.
     */
    public function __construct(callable $producer)
    {
        $this->producer = $producer;
    }

    /**
     * Executes the producer, buffers its output and returns it.
     *
     * @return mixed The buffered content that has been written onto the output stream.
     */
    public function __invoke()
    {
        $producer = $this->producer;

        if (!ob_start()) {
            return null;
        } else {
            $producer();
            return ob_get_clean();
        }
    }
}

