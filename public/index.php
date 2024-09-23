<?php

use Phalcon\DI\FactoryDefault,
Phalcon\Db\Adapter\Pdo\Mysql as DbAdapter,
Phalcon\Logger,
Phalcon\Logger\Adapter\Stream as StreamAdapter,
Gaia\Libraries\Utils\executiontime;

/**
 * Error Reporting
 * @todo Remove before publishing
 */
error_reporting(E_ERROR);
ini_set('display_errors', true);

// Setup the application constants
define('APP_PATH', realpath('..'));
define('DS', DIRECTORY_SEPARATOR);

require APP_PATH . '/autoload.php';

/**
 * @todo Need a more appropriate place for this
 */

global $logger;
$loggerAdapter = new StreamAdapter(APP_PATH . '/logs/application.log');
$logger = new Logger(
    'applicationlog',
[
    'local' => $loggerAdapter,
]);
$logger->setLogLevel(Logger::DEBUG);

// Allow from any origin
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400'); // cache for 1 day
    header('Access-Control-Allow-Headers: Accept, Accept-CH, Accept-Charset, Accept-Datetime, Accept-Encoding, Accept-Ext, Accept-Features, Accept-Language, Accept-Params, Accept-Ranges, Access-Control-Allow-Credentials, Access-Control-Allow-Headers, Access-Control-Allow-Methods, Access-Control-Allow-Origin, Access-Control-Expose-Headers, Access-Control-Max-Age, Access-Control-Request-Headers, Access-Control-Request-Method, Age, Allow, Alternates, Authentication-Info, Authorization, C-Ext, C-Man, C-Opt, C-PEP, C-PEP-Info, CONNECT, Cache-Control, Compliance, Connection, Content-Base, Content-Disposition, Content-Encoding, Content-ID, Content-Language, Content-Length, Content-Location, Content-MD5, Content-Range, Content-Script-Type, Content-Security-Policy, Content-Style-Type, Content-Transfer-Encoding, Content-Type, Content-Version, Cookie, Cost, DAV, DELETE, DNT, DPR, Date, Default-Style, Delta-Base, Depth, Derived-From, Destination, Differential-ID, Digest, ETag, Expect, Expires, Ext, From, GET, GetProfile, HEAD, HTTP-date, Host, IM, If, If-Match, If-Modified-Since, If-None-Match, If-Range, If-Unmodified-Since, Keep-Alive, Label, Last-Event-ID, Last-Modified, Link, Location, Lock-Token, MIME-Version, Man, Max-Forwards, Media-Range, Message-ID, Meter, Negotiate, Non-Compliance, OPTION, OPTIONS, OWS, Opt, Optional, Ordering-Type, Origin, Overwrite, P3P, PEP, PICS-Label, POST, PUT, Pep-Info, Permanent, Position, Pragma, ProfileObject, Protocol, Protocol-Query, Protocol-Request, Proxy-Authenticate, Proxy-Authentication-Info, Proxy-Authorization, Proxy-Features, Proxy-Instruction, Public, RWS, Range, Referer, Refresh, Resolution-Hint, Resolver-Location, Retry-After, Safe, Sec-Websocket-Extensions, Sec-Websocket-Key, Sec-Websocket-Origin, Sec-Websocket-Protocol, Sec-Websocket-Version, Security-Scheme, Server, Set-Cookie, Set-Cookie2, SetProfile, SoapAction, Status, Status-URI, Strict-Transport-Security, SubOK, Subst, Surrogate-Capability, Surrogate-Control, TCN, TE, TRACE, Timeout, Title, Trailer, Transfer-Encoding, UA-Color, UA-Media, UA-Pixels, UA-Resolution, UA-Windowpixels, URI, Upgrade, User-Agent, Variant-Vary, Vary, Version, Via, Viewport-Width, WWW-Authenticate, Want-Digest, Warning, Width, X-Content-Duration, X-Content-Security-Policy, X-Content-Type-Options, X-CustomHeader, X-DNSPrefetch-Control, X-Forwarded-For, X-Forwarded-Port, X-Forwarded-Proto, X-Frame-Options, X-Modified, X-OTHER, X-PING, X-PINGOTHER, X-Powered-By, X-Requested-With'); // cache for 1 day
    header('Access-Control-Expose-Headers: Accept, Accept-CH, Accept-Charset, Accept-Datetime, Accept-Encoding, Accept-Ext, Accept-Features, Accept-Language, Accept-Params, Accept-Ranges, Access-Control-Allow-Credentials, Access-Control-Allow-Headers, Access-Control-Allow-Methods, Access-Control-Allow-Origin, Access-Control-Expose-Headers, Access-Control-Max-Age, Access-Control-Request-Headers, Access-Control-Request-Method, Age, Allow, Alternates, Authentication-Info, Authorization, C-Ext, C-Man, C-Opt, C-PEP, C-PEP-Info, CONNECT, Cache-Control, Compliance, Connection, Content-Base, Content-Disposition, Content-Encoding, Content-ID, Content-Language, Content-Length, Content-Location, Content-MD5, Content-Range, Content-Script-Type, Content-Security-Policy, Content-Style-Type, Content-Transfer-Encoding, Content-Type, Content-Version, Cookie, Cost, DAV, DELETE, DNT, DPR, Date, Default-Style, Delta-Base, Depth, Derived-From, Destination, Differential-ID, Digest, ETag, Expect, Expires, Ext, From, GET, GetProfile, HEAD, HTTP-date, Host, IM, If, If-Match, If-Modified-Since, If-None-Match, If-Range, If-Unmodified-Since, Keep-Alive, Label, Last-Event-ID, Last-Modified, Link, Location, Lock-Token, MIME-Version, Man, Max-Forwards, Media-Range, Message-ID, Meter, Negotiate, Non-Compliance, OPTION, OPTIONS, OWS, Opt, Optional, Ordering-Type, Origin, Overwrite, P3P, PEP, PICS-Label, POST, PUT, Pep-Info, Permanent, Position, Pragma, ProfileObject, Protocol, Protocol-Query, Protocol-Request, Proxy-Authenticate, Proxy-Authentication-Info, Proxy-Authorization, Proxy-Features, Proxy-Instruction, Public, RWS, Range, Referer, Refresh, Resolution-Hint, Resolver-Location, Retry-After, Safe, Sec-Websocket-Extensions, Sec-Websocket-Key, Sec-Websocket-Origin, Sec-Websocket-Protocol, Sec-Websocket-Version, Security-Scheme, Server, Set-Cookie, Set-Cookie2, SetProfile, SoapAction, Status, Status-URI, Strict-Transport-Security, SubOK, Subst, Surrogate-Capability, Surrogate-Control, TCN, TE, TRACE, Timeout, Title, Trailer, Transfer-Encoding, UA-Color, UA-Media, UA-Pixels, UA-Resolution, UA-Windowpixels, URI, Upgrade, User-Agent, Variant-Vary, Vary, Version, Via, Viewport-Width, WWW-Authenticate, Want-Digest, Warning, Width, X-Content-Duration, X-Content-Security-Policy, X-Content-Type-Options, X-CustomHeader, X-DNSPrefetch-Control, X-Forwarded-For, X-Forwarded-Port, X-Forwarded-Proto, X-Frame-Options, X-Modified, X-OTHER, X-PING, X-PINGOTHER, X-Powered-By, X-Requested-With'); // cache for 1 day
}

// Access-Control headers are received during OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {

    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
        header("Access-Control-Allow-Methods: GET, POST, PATCH, OPTIONS, DELETE");

    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");

    exit(0);
}


try {

    global $stime, $di, $apiVersion, $logger, $timer;
    $stime = explode(" ", microtime());
    $stime = $stime[1] + $stime[0];

    $timer = new executiontime();

    //Create a DI
    $di = new FactoryDefault();

    //Setup the view component
    $di->set('view', function () {
        $view = new \Phalcon\Mvc\View();
        $view->setViewsDir(APP_PATH . '/app/views/');
        $view->registerEngines(
            array(
            ".volt" => 'Phalcon\Mvc\View\Engine\Volt'
        )
        );
        return $view;
    });

    //Setup a base URI so that all generated URIs include the "tutorial" folder
    $di->set('url', function () {
        $url = new \Phalcon\Mvc\Url();
        $url->setBaseUri('/');
        return $url;
    });

    // @todo - set in di
    // @todo - add actions route
    $di->set('router', function () {
        $router = new Gaia\MVC\Router();
        $router->init();
        return $router;
    });


    // Set up the database service
    $di->set('db', function () {
        $connection = new DbAdapter($GLOBALS['settings']['database']->toArray());
        $eventsManager = new Phalcon\Events\Manager();
        $dbLoggerAdapter = new StreamAdapter(APP_PATH . "/logs/db.log");
        $dblogger = new Logger(
            'dblog',
        [
            'local' => $dbLoggerAdapter,
        ]
            );
        $dblogger = $dblogger->setLogLevel(Logger::DEBUG);
        //Listen all the database events
        $eventsManager->attach('db', function ($event, $connection) use ($dblogger) {
                if ($event->getType() == 'beforeQuery') {
                    $GLOBALS['timer']->diff();
                    $sqlVariables = $connection->getSQLVariables();
                    if (isset($sqlVariables)) {
                        $dblogger->debug(print_r($connection->getSQLBindTypes(), 1) . ' ' . join(', ', $sqlVariables));
                    }
                    else {
                        $dblogger->debug(print_r($connection->getSQLBindTypes(), 1));
                    }
                }
                if ($event->getType() == 'afterQuery') {
                    $dblogger->debug('Query execution time:' . ($GLOBALS['timer']->diff()) . ' seconds');
                    $dblogger->debug($connection->getSQLStatement());
                }
            }
            );

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
        'relationshipFactory',
        new \Gaia\Core\MVC\Models\Relationships\Factory\RelationshipFactory($di)
    );

    // putenv('XHGUI_MONGO_HOST=mongodb://xhgui:27017');
    // require_once('/usr/local/src/xhgui/external/header.php');

    /**
     * @todo move the migration away to elsewhere
     * 
     */
    // $di->get('migrationDriver')->migrate();

    //Handle the request
    $app = new \Phalcon\Mvc\Application($di);
    echo $app->handle($_SERVER["REQUEST_URI"])->getContent();

}
catch (\Gaia\Exception\Access $e) {
    return $e->handle();
}
catch(\Gaia\Exception\ResourceNotFound $e) {
    return $e->handle();
}
catch(\Gaia\Exception\FileNotFound $e) {
    return $e->handle();
}
catch(\Gaia\Exception\MigrationDriver $e) {
    return $e->handle();
}
catch(\Gaia\Exception\Permission $e) {
    return $e->handle();
}
catch (\Phalcon\Exception $e) {
    echo "PhalconException: ", $e->getMessage();
}
catch(\Gaia\Exception\Exception $e) {
    return $e->handle();
}