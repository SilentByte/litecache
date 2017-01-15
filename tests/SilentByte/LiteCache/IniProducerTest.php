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

class IniProducerTest extends TestCase
{
    use VirtualFileSystemTrait;

    protected function setUp()
    {
        $this->vfs();
    }

    public function testInvokeReadsContents()
    {
        $expected = [
            'server' => [
                'host'     => 'myhost.test.com',
                'user'     => 'root',
                'password' => 'root'
            ]
        ];

        $ini
            = "[server]\n"
            . "host = myhost.test.com\n"
            . "user = root\n"
            . "password = root\n";

        $this->file('test.ini', $ini);

        $producer = new IniProducer($this->url('root/test.ini'));
        $actual = $producer();

        $this->assertEquals($expected, $actual);
    }
}

