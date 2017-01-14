<?php
/**
 * SilentByte LiteCache Library
 *
 * @copyright 2017 SilentByte <https://silentbyte.com/>
 * @license   https://opensource.org/licenses/MIT MIT
 */

declare(strict_types = 1);

namespace SilentByte\LiteCache;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;

class PathHelperTest extends TestCase
{
    private $vfs;

    protected function setUp()
    {
        $this->vfs = vfsStream::setup('root');
    }

    public function testDirectoryStripsSlashes()
    {
        $this->assertEquals('/test/directory',
                            PathHelper::directory('/test/directory/'));
    }

    public function testCombine()
    {
        $expected = 'test' . DIRECTORY_SEPARATOR
            . 'directory' . DIRECTORY_SEPARATOR
            . 'file.txt';

        $this->assertEquals($expected,
                            PathHelper::combine('test',
                                                'directory',
                                                'file.txt'));
    }

    public function testMakePathCreatesStructure()
    {
        $path = vfsStream::url('root/test/directory');

        PathHelper::makePath($path, 777);
        $this->assertFileExists($path);
    }

    public function testMakePathCreatesDirectoryWithPermission()
    {
        $path1 = vfsStream::url('root/test/directory1');
        $path2 = vfsStream::url('root/test/directory2');

        PathHelper::makePath($path1, 0777);
        $this->assertEquals(0777, fileperms($path1) & 0777);

        PathHelper::makePath($path2, 0765);
        $this->assertEquals(0765, fileperms($path2) & 0765);
    }
}

