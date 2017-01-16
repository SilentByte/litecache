<?php
/**
 * SilentByte LiteCache Library
 *
 * @copyright 2017 SilentByte <https://silentbyte.com/>
 * @license   https://opensource.org/licenses/MIT MIT
 */

declare(strict_types = 1);

$options = [
    'theme'     => 'default',
    'title'     => 'SilentByte LiteCache 2.0 Documentation',
    'build_dir' => __DIR__ . '/docs',
    'cache_dir' => __DIR__ . '/.sami/.twig',
];

$iterator = Symfony\Component\Finder\Finder::create()
    ->files()
    ->name('*.php')
    ->in(__DIR__ . '/src');

$sami = new Sami\Sami($iterator, $options);

if (!is_dir(__DIR__ . '/.sami/themes')) {
    mkdir(__DIR__ . '/.sami/themes', 0777, true);
}

$templates = $sami['template_dirs'];
$templates[] = __DIR__ . '/.sami/themes';

$sami['template_dirs'] = $templates;

return $sami;

