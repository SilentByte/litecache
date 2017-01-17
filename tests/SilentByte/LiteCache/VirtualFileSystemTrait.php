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
use org\bovigo\vfs\visitor\vfsStreamStructureVisitor;

trait VirtualFileSystemTrait
{
    private $vfs;

    protected function vfs()
    {
        if ($this->vfs) {
            return $this->vfs;
        }

        $this->vfs = vfsStream::setup('root');
        return $this->vfs;
    }

    protected function url($url)
    {
        return vfsStream::url($url);
    }

    protected function tree()
    {
        /** @noinspection PhpUndefinedMethodInspection */
        return vfsStream::inspect(new vfsStreamStructureVisitor(),
                                  $this->vfs())->getStructure();
    }

    protected function file($filename, $content = null)
    {
        vfsStream::newFile($filename)
            ->at($this->vfs())
            ->setContent($content);
    }
}

