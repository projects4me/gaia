<?php
/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

use Phalcon\DI;
use Phalcon\DI\FactoryDefault;
use Phalcon\Db\Adapter\Pdo\Mysql as DbAdapter;
use Phalcon\Logger;
use Phalcon\Logger\Adapter\File as FileAdapter;
use Phalcon\Mvc\Model\Manager as ModelsManager;

ini_set('display_errors',1);
error_reporting(E_ALL);

if (!defined('APP_PATH')) define('APP_PATH', realpath('..'));
if (!defined('DS')) define('DS', DIRECTORY_SEPARATOR);

require APP_PATH . '/autoload.php';

require APP_PATH . '/tests/UnitTestCase.php';

global $logger;
$logger = new FileAdapter(APP_PATH.'/logs/application.log');
$logger->setLogLevel(Logger::DEBUG);


$di = new FactoryDefault();
DI::reset();

$di->set(
    'fileHandler',
    new \Gaia\Libraries\File\Handler($di)
);

$di->set(
    'config',
    new \Gaia\Libraries\Config($di)
);

$di->set(
    'modelsManager',
    function()
    {
        return new \Phalcon\Mvc\Model\Manager();
    }
);

// Add any needed services to the DI here
$di->set('db', function () {
    //       global $logger;
    $connection = new DbAdapter((array) $GLOBALS['settings']['database']);
    $eventsManager = new Phalcon\Events\Manager();
    $dblogger = new \Phalcon\Logger\Adapter\File(APP_PATH . "/logs/tests/db.log");
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

DI::setDefault($di);
