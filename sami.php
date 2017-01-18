<?php
/**
 * SilentByte LiteCache Library
 *
 * @copyright 2017 SilentByte <https://silentbyte.com/>
 * @license   https://opensource.org/licenses/MIT MIT
 */

declare(strict_types = 1);

use Sami\Version\GitVersionCollection;

$dir = __DIR__ . '/src';

$iterator = Symfony\Component\Finder\Finder::create()
    ->files()
    ->name('*.php')
    ->in($dir);

$versions = GitVersionCollection::create($dir)
    ->add('master')
    ->addFromTags('2.0');

$options = [
    'title'     => 'SilentByte LiteCache 2.0 Documentation',
    'theme'     => 'sami-silentbyte',
    'versions'  => $versions,
    'build_dir' => __DIR__ . '/docs/%version%',
    'cache_dir' => __DIR__ . '/.sami/.twig/%version%',
];

$sami = new Sami\Sami($iterator, $options);

if (!is_dir(__DIR__ . '/.sami/themes')) {
    mkdir(__DIR__ . '/.sami/themes', 0777, true);
}

$templates = $sami['template_dirs'];
$templates[] = __DIR__ . '/.sami/themes';

$sami['template_dirs'] = $templates;

return $sami;

