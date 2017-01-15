<?php
/**
 * SilentByte LiteCache Library
 *
 * @copyright 2017 SilentByte <https://silentbyte.com/>
 * @license   https://opensource.org/licenses/MIT MIT
 */

declare(strict_types = 1);

namespace SilentByte\LiteCache;

use PHPUnit\Framework\TestCase;
use RuntimeException;

class OutputProducerTest extends TestCase
{
    public function testInvokeReadsOutput()
    {
        $expected = "Test\n123456789\nabcdefghijklmnopqrstuvwxyz";

        $producer = new OutputProducer(function () use ($expected) {
            echo $expected;
        });

        $actual = $producer();
        $this->assertEquals($expected, $actual);
    }

    /**
     * @expectedException \SilentByte\LiteCache\CacheProducerException
     */
    public function testInvokeRecoversThrowingUserDefinedProducer()
    {
        $expected = "Test\n123456789\nabcdefghijklmnopqrstuvwxyz";

        $producer = new OutputProducer(function () use ($expected) {
            throw new RuntimeException('User Defined Producer.');
        });

        $producer();
    }
}

