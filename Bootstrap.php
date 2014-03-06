<?php
/**
 * This file is part of the TYPO3-Analytics package.
 *
 * (c) Andreas Grunwald <andygrunwald@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Bootstrap file to initialize import things like Config or ClassLoader
 */

require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\ClassLoader\UniversalClassLoader;

$loader = new UniversalClassLoader();
$loader->registerNamespaces(array(
    'TYPO3Analysis' => __DIR__ . '/src'
));
$loader->register();

define('CONFIG_FILE', __DIR__ . '/Config/config.yml');