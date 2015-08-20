<?php
use Phalcon\DI\FactoryDefault,
    Phalcon\Mvc\View,
    Phalcon\Mvc\Dispatcher,
    Phalcon\Mvc\Url as UrlResolver,
    Phalcon\Db\Adapter\Pdo\Mysql as DbAdapter,
    Phalcon\Mvc\Model\Metadata\Memory as MetaDataAdapter,
    Phalcon\Session\Adapter\Files as SessionAdapter,
    Phalcon\Translate\Adapter\NativeArray;

try {
    
    global $stime;
    $stime = explode(" ",microtime());
    $stime = $stime[1] + $stime[0];
    //Register an autoloader
    $loader = new \Phalcon\Loader();
    $loader->registerDirs(array(
        '../foundation/controllers/',
        '../app/controllers/',
        '../app/models/',
        '../config/',        
    ))->register();

    require_once('../foundation/controllers/RestController.php');
    //Create a DI
    $di = new Phalcon\DI\FactoryDefault();

    //Setup the view component
    $di->set('view', function(){
        $view = new \Phalcon\Mvc\View();
        $view->setViewsDir('../app/views/');
        return $view;
    });

    //Setup a base URI so that all generated URIs include the "tutorial" folder
    $di->set('url', function(){
        $url = new \Phalcon\Mvc\Url();
        $url->setBaseUri('/');
        return $url;
    });
    
    require_once '../foundation/models/model.php';
    require_once '../foundation/libs/fileHanlder.php';

    require_once '../foundation/libs/metaManager.php';
    
    // @todo - set in di
    require_once '../foundation/libs/config.php';
    $config = new \Foundation\Config();
    $config->init();


    // @todo - add actions route
    $di->set('router', function(){
        require '../foundation/mvc/router.php';
        $router = new Foundation\Mvc\Router();
        $router->init();
        return $router;
    });

    // Set up the database service
    $di->set('db', function () {
        return new DbAdapter((array) $GLOBALS['settings']['database']);
    });
  
    //Handle the request
    $app = new \Phalcon\Mvc\Application($di);
    
    echo $app->handle()->getContent();

} catch(\Phalcon\Exception $e) {
     echo "PhalconException: ", $e->getMessage();
}

