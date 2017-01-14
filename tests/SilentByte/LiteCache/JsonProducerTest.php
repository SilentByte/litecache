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
use stdClass;

class JsonProducerTest extends TestCase
{
    use VirtualFileSystemTrait;

    protected function setUp()
    {
        $this->vfs();
    }

    public function testInvokeReadsContents()
    {
        $expected = new stdClass();
        $expected->server = new stdClass();
        $expected->server->host = 'myhost.test.com';
        $expected->server->user = 'root';
        $expected->server->password = 'root';

        $ini
            = "{\n"
            . "    \"server\": {"
            . "        \"host\": \"myhost.test.com\",\n"
            . "        \"user\": \"root\",\n"
            . "        \"password\": \"root\"\n"
            . "    }\n"
            . "}";

        $this->file('test.json', $ini);

        $producer = new JsonProducer($this->url('root/test.json'));
        $actual = $producer();

        $this->assertEquals($expected, $actual);
    }
}

