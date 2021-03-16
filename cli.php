<?php

use Phalcon\Di\FactoryDefault\Cli as CliDI;
use Phalcon\Cli\Console as ConsoleApp;
use Phalcon\Loader;
use Phalcon\Db\Adapter\Pdo\Mysql as DbAdapter;
use Phalcon\Logger;
use Phalcon\Logger\Adapter\File as FileAdapter;
use Gaia\Libraries\Utils\executiontime;

define('APP_PATH', realpath('.'));
define('DS', DIRECTORY_SEPARATOR);

require APP_PATH . '/autoload.php';

global $logger,$timer;
$logger = new FileAdapter(APP_PATH.'/logs/application.log');
$logger->setLogLevel(Logger::DEBUG);

$timer = new executiontime();

// Using the CLI factory default services container
$di = new CliDI();

$di->set('db', function () {
    $connection = new DbAdapter((array) $GLOBALS['settings']['database']);
    $eventsManager = new Phalcon\Events\Manager();
    $dblogger = new \Phalcon\Logger\Adapter\File(APP_PATH . "/db.log");
//         print_r($logger);
    //Listen all the database events
    $eventsManager->attach('db', function($event, $connection) use ($dblogger) {
//            global $logger;
        if ($event->getType() == 'beforeQuery') {
            $GLOBALS['timer']->diff();
            $sqlVariables = $connection->getSQLVariables();
            if (count($sqlVariables)) {
                $dblogger->log(print_r($connection->getSQLBindTypes(),1) . ' ' . join(', ', $sqlVariables), \Phalcon\Logger::INFO);
            } else {
                $dblogger->log(print_r($connection->getSQLBindTypes(),1), \Phalcon\Logger::INFO);
            }
        }
        if ($event->getType() == 'afterQuery') {
            $dblogger->log('Query execution time:'.($GLOBALS['timer']->diff()).' seconds', \Phalcon\Logger::INFO);
            $dblogger->log($connection->getSQLStatement(), \Phalcon\Logger::INFO);
        }
    });

    //Assign the eventsManager to the db adapter instance
    $connection->setEventsManager($eventsManager);
    return $connection;
});

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