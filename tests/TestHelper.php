<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

use Phalcon\Di\Di;
use Phalcon\Di\FactoryDefault;
use Phalcon\Logger\Logger;
use Phalcon\Logger\Adapter\Stream as StreamAdapter;
use Gaia\Libraries\Meta\Manager as metaManager;
use Gaia\Db\Factory\ConnectionFactory;

ini_set('display_errors', 1);
error_reporting(E_ALL);
// Deprecations are logged, warnings and notices fail the run (see phpunit.xml) for tools/quality/deprecations-report.php.
// Inside a test PHPUnit's error handler takes the deprecations over and Gaia\Tests\PHPUnit\DiagnosticsLogExtension
// writes them here; outside a test (bootstrap, data providers) PHP logs them itself (log_errors).
$diagnosticsLog = realpath(__DIR__ . '/..') . '/logs/php-diagnostics-phpunit.log';
ini_set('error_log', $diagnosticsLog);
// PHP creates the log on the first entry only; an empty file tells the gate the suite ran clean.
if (!file_exists($diagnosticsLog)) {
    touch($diagnosticsLog);
}

define('APP_PATH', realpath(__DIR__ . '/..'));
if (!defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}

require APP_PATH . '/autoload.php';

require APP_PATH . '/tests/autoload.php';

global $logger;
$loggerAdapter = new StreamAdapter(APP_PATH . '/logs/application.log');
$logger = new Logger(
    'applicationlog',
    [
        'local'   => $loggerAdapter,
    ]
);
$logger->setLogLevel(Logger::DEBUG);


$di = new FactoryDefault();
Di::reset();

$di->set(
    'fileHandler',
    new \Gaia\Libraries\File\Handler($di)
);

$di->set(
    'modelsManager',
    function () {
        return new \Phalcon\Mvc\Model\Manager();
    }
);

$di->set(
    'relationshipFactory',
    new \Gaia\Core\MVC\Models\Relationships\Factory\RelationshipFactory($di)
);

$di->set(
    'metaManager',
    new metaManager($di)
);

// As in public/index.php: the meta manager asks the adapter's migration handler how a metadata type is stored
$di->set(
    'migrationHandler',
    function () use ($di) {
        return (new \Gaia\Db\Factory\MigrationFactory($di, $GLOBALS['settings']['database']['adapter']))->getHandler();
    }
);

$di->set(
    'config',
    new \Gaia\Libraries\Config($di)
);

// Add any needed services to the DI here
$di->set(
    'db',
    function () {
        $config = $GLOBALS['settings']['database']->toArray();
        $connection = ConnectionFactory::create($config);
        $eventsManager = new Phalcon\Events\Manager();
        $dbLoggerAdapter = new StreamAdapter(APP_PATH . "/logs/tests/db.log");
        $dblogger = new Logger(
            'dblog',
            [
                'local'   => $dbLoggerAdapter,
            ]
        );
        $dblogger = $dblogger->setLogLevel(Logger::DEBUG);
        //Listen all the database events
        $eventsManager->attach(
            'db',
            function ($event, $connection) use ($dblogger) {
                if ($event->getType() == 'beforeQuery') {
                    // $GLOBALS['timer']->diff();

                    $sqlVariables = $connection->getSQLVariables();
                    if (isset($sqlVariables)) {
                        $dblogger->debug(print_r($connection->getSQLBindTypes(), 1) . ' ' . join(', ', $sqlVariables));
                    } else {
                        $dblogger->debug(print_r($connection->getSQLBindTypes(), 1));
                    }
                }
                if ($event->getType() == 'afterQuery') {
                    // $dblogger->debug('Query execution time:'.($GLOBALS['timer']->diff()).' seconds');
                    $dblogger->debug($connection->getSQLStatement());
                }
            }
        );

        //Assign the eventsManager to the db adapter instance
        $connection->setEventsManager($eventsManager);
        return $connection;
    }
);

Di::setDefault($di);
