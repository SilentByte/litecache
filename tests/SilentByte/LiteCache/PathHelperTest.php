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

class PathHelperTest extends TestCase
{
    use VirtualFileSystemTrait;

    public function testDirectoryStripsSlashes()
    {
        $this->assertEquals('/root/directory',
                            PathHelper::directory('/root/directory/'));
    }

    public function testCombine()
    {
        $expected = 'root' . DIRECTORY_SEPARATOR
            . 'directory' . DIRECTORY_SEPARATOR
            . 'file.txt';

        $this->assertEquals($expected,
                            PathHelper::combine('root',
                                                'directory',
                                                'file.txt'));
    }

    public function testMakePathCreatesStructure()
    {
        $this->vfs();
        $path = $this->url('root/test/directory');

        PathHelper::makePath($path, 777);
        $this->assertFileExists($path);
    }

    public function testMakePathCreatesDirectoryWithPermission()
    {
        $this->vfs();
        $path1 = $this->url('root/test/directory1');
        $path2 = $this->url('root/test/directory2');

        PathHelper::makePath($path1, 0777);
        $this->assertEquals(0777, fileperms($path1) & 0777);

        PathHelper::makePath($path2, 0765);
        $this->assertEquals(0765, fileperms($path2) & 0765);
    }
}

