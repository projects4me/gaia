<?php
use Phalcon\DI\FactoryDefault,
    Phalcon\Mvc\View,
    Phalcon\Mvc\Dispatcher,
    Phalcon\Mvc\Url as UrlResolver,
    Phalcon\Db\Adapter\Pdo\Mysql as DbAdapter,
    Phalcon\Mvc\Model\Metadata\Memory as MetaDataAdapter,
    Phalcon\Session\Adapter\Files as SessionAdapter,
    Phalcon\Translate\Adapter\NativeArray;

error_reporting(E_ALL);
define('APP_PATH', realpath('..'));
ini_set('display_errors',true);

try {
    
    global $stime;
    $stime = explode(" ",microtime());
    $stime = $stime[1] + $stime[0];
    //Register an autoloader
    $loader = new \Phalcon\Loader();
    $loader->registerDirs(array(
        APP_PATH.'/foundation/controllers/',
        APP_PATH.'/app/controllers/',
        APP_PATH.'/app/models/',
        APP_PATH.'/config/',        
    ))->register();

    require_once(APP_PATH.'/foundation/controllers/RestController.php');
    //Create a DI
    $di = new Phalcon\DI\FactoryDefault();

    //Setup the view component
    $di->set('view', function(){
        $view = new \Phalcon\Mvc\View();
        $view->setViewsDir(APP_PATH.'/app/views/');
        return $view;
    });

    //Setup a base URI so that all generated URIs include the "tutorial" folder
    $di->set('url', function(){
        $url = new \Phalcon\Mvc\Url();
        $url->setBaseUri('/');
        return $url;
    });
    
    require_once APP_PATH.'/foundation/models/model.php';
    require_once APP_PATH.'/foundation/libs/fileHandler.php';

    require_once APP_PATH.'/foundation/libs/metaManager.php';
    
    // @todo - set in di
    require_once APP_PATH.'/foundation/libs/config.php';
    $config = new \Foundation\Config();
    $config->init();


    // @todo - add actions route
    $di->set('router', function(){
        require APP_PATH.'/foundation/mvc/router.php';
        $router = new Foundation\Mvc\Router();
        $router->init();
        return $router;
    });

    // Set up the database service
    $di->set('db', function () {
        return new DbAdapter((array) $GLOBALS['settings']['database']);
    });

    //require_once '../foundation/libs/modelMigration.php';
    //$modelMigration = new Foundation\Mvc\Model\Migration();
    
    //$modelMigration->init();
    
    
    //Handle the request
    $app = new \Phalcon\Mvc\Application($di);
    
    echo $app->handle()->getContent();

} catch(\Phalcon\Exception $e) {
     echo "PhalconException: ", $e->getMessage();
}

