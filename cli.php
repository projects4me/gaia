<?php

use Phalcon\Di\FactoryDefault\Cli as CliDI;
use Phalcon\Cli\Console as ConsoleApp;
use Phalcon\Loader;


define('APP_PATH', realpath('.'));
define('DS', DIRECTORY_SEPARATOR);

require APP_PATH . '/autoload.php';

// Using the CLI factory default services container
$di = new CliDI();

$di->set(
    'metaManager',
    new \Gaia\Libraries\Meta\Manager($di)
);

$di->set(
    'metaMigration',
    new \Gaia\Libraries\Meta\Migration($di)
);

$di->set(
    'migrationDriver',
    new \Gaia\Libraries\Meta\Migration\Driver($di)
);

$di->set(
    'fileHandler',
    new \Gaia\Libraries\File\Handler($di)
);

// Load the configuration file (if any)
$di->set(
    'config',
    new \Gaia\Libraries\Config($di)
);

// Create a console application
$console = new ConsoleApp();

$console->setDI($di);

/**
 * Process the console arguments
 */
$arguments = [];

foreach ($argv as $k => $arg) {
    if ($k === 1) {
        $arguments['task'] = $arg;
    } elseif ($k === 2) {
        $arguments['action'] = $arg;
    } elseif ($k >= 3) {
        $arguments['params'][] = $arg;
    }
}

try {
    // Handle incoming arguments
    $console->handle($arguments);
} catch (\Phalcon\Exception $e) {
    // Do Phalcon related stuff here
    // ..
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
} catch (\Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . PHP_EOL);
    exit(1);
} catch (\Exception $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}