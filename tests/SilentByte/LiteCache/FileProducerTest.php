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

class FileProducerTest extends TestCase
{
    use VirtualFileSystemTrait;

    protected function setUp()
    {
        $this->vfs();
    }

    public function testInvokeReadsContents()
    {

    }
}

