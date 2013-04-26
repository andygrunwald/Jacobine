<?php
/**
 * @todo adds a description (license text, description of this class / file, etc)
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