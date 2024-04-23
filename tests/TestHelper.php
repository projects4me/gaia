<?php
/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

use Phalcon\DI;
use Phalcon\DI\FactoryDefault;
use Phalcon\Db\Adapter\Pdo\Mysql as DbAdapter;
use Phalcon\Logger;
use Phalcon\Logger\Adapter\Stream as StreamAdapter;
use Gaia\Libraries\Meta\Manager as metaManager;
use Phalcon\Mvc\Model\Manager as ModelsManager;

ini_set('display_errors', 1);
error_reporting(E_ALL);

define('APP_PATH', realpath('.'));
if (!defined('DS')) { define('DS', DIRECTORY_SEPARATOR);
}

require APP_PATH . '/autoload.php';

require APP_PATH . '/tests/autoload.php';

require APP_PATH . '/tests/UnitTestCase.php';

global $logger;
$loggerAdapter = new StreamAdapter(APP_PATH.'/logs/application.log');
$logger = new Logger(
    'applicationlog',
    [
        'local'   => $loggerAdapter,
    ]
);
$logger->setLogLevel(Logger::DEBUG);


$di = new FactoryDefault();
DI::reset();

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

$di->set(
    'config',
    new \Gaia\Libraries\Config($di)
);

// Add any needed services to the DI here
$di->set(
    'db', function () {
        // global $logger;
        $connection = new DbAdapter($GLOBALS['settings']['database']->toArray());
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
            'db', function ($event, $connection) use ($dblogger) {
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

DI::setDefault($di);
