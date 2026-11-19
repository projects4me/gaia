<?php

use Phalcon\Di\FactoryDefault\Cli as CliDI;
use Phalcon\Cli\Console as ConsoleApp;
use Phalcon\Loader;
use Phalcon\Logger\Logger;
use Phalcon\Logger\Adapter\Stream as StreamAdapter;
use Gaia\Libraries\Utils\executiontime;

define('APP_PATH', realpath('.'));
define('DS', DIRECTORY_SEPARATOR);

require APP_PATH . '/autoload.php';

// A fatal error is not a Throwable: write it to stderr and exit non-zero.
\Gaia\Exception\CliFatalErrorHandler::register();

global $logger,$timer;
$loggerAdapter = new StreamAdapter(APP_PATH . '/logs/application.log');
$logger = new Logger(
    'applicationlog',
    [
    'local' => $loggerAdapter,
    ]
);
$logger->setLogLevel(Logger::DEBUG);

$timer = new executiontime();

// Using the CLI factory default services container
$di = new CliDI();

$di->set('db', function () {
    $config = $GLOBALS['settings']['database']->toArray();
    $connection = \Gaia\Db\Factory\ConnectionFactory::create($config);
    $eventsManager = new Phalcon\Events\Manager();
    $dblogger = new Logger('dblog', ['local' => new StreamAdapter(APP_PATH . '/logs/db.log')]);
    //Listen all the database events
    $eventsManager->attach('db', function($event, $connection) use ($dblogger) {
        if ($event->getType() == 'beforeQuery') {
            $GLOBALS['timer']->diff();
            $sqlVariables = $connection->getSQLVariables();
            if (!empty($sqlVariables)) {
                $dblogger->info(print_r($connection->getSQLBindTypes(),1) . ' ' . join(', ', $sqlVariables));
            } else {
                $dblogger->info(print_r($connection->getSQLBindTypes(),1));
            }
        }
        if ($event->getType() == 'afterQuery') {
            $dblogger->info('Query execution time:'.($GLOBALS['timer']->diff()).' seconds');
            $dblogger->info($connection->getSQLStatement());
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

$di->set(
    'dialectFactory',
    new \Gaia\Db\Factory\DialectFactory($di, $GLOBALS['settings']['database']['adapter'])
);

$di->set(
    'dialect',
    $di->get('dialectFactory')->getDialect()
);

$di->set(
    'migrationFactory',
    new \Gaia\Db\Factory\MigrationFactory($di, $GLOBALS['settings']['database']['adapter'])
);

$di->set(
    'migrationHandler',
    $di->get('migrationFactory')->getHandler()
);

$di->set(
    'relationshipFactory',
    new \Gaia\Core\MVC\Models\Relationships\Factory\RelationshipFactory($di)
);

$di->set(
    'serviceFactory',
    new \Gaia\Services\ServiceFactory($di)
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
} catch (\Throwable $throwable) {
    // Every error, Phalcon 4 or 5 (whose own exceptions no longer extend
    // Phalcon\Exception), goes to stderr with a failing exit code.
    fwrite(STDERR, $throwable->getMessage() . PHP_EOL);
    exit(1);
}