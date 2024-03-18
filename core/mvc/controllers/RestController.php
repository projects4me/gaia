<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Core\MVC\REST\Controllers;

use Phalcon\Http\Response;
use Phalcon\Mvc\Model\Resultset;
use Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;
use Phalcon\Events\Manager as EventsManager;
use Gaia\Libraries\Utils\Util;
use Phalcon\Events\ManagerInterface as EventsManagerInterface;

use function Gaia\Libraries\Utils\create_guid;

/**
 * The is the default controller used by this application. It provides the basic
 * implementation of REST function, e.g. GET, POST, PATCH, DELETE and OPTIONS.
 *
 * The default functionality support a custom implementation of HAL
 *
 * Supports only JSON, OAUTH, ACL, Mixin Implementation (Components)
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  core
 * @category REST, Controller
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class RestController extends \Phalcon\Mvc\Controller implements \Phalcon\Events\EventsAwareInterface
{
    /**
     * Model's name is registered from controller via parameter
     *
     * @var string $modelName
     */
    protected $modelName;

    /**
     * Model object in question
     *
     * @var \Phalcon\Mvc\ModelInterface $model
     */
    protected $model;

    /**
     * Model's name of relationship model
     *
     * @var string $relationship
     */
    protected $relationship = null;

    /**
     * Name of controller is passed in parameter
     *
     * @var string $controllerName
     */
    protected $controllerName;

    /**
     * Name of action is passed in parameter
     *
     * @var string $actionName
     */
    protected $actionName;

    /**
     * Value of primary key field of model (passed in parameter)
     *
     * @var string $id
     */
    protected $id;

    /**
     * Parameters
     *
     * @var mixed $params
     */
    protected $params;

    /**
     * Response object
     *
     * @var Phalcon\Http\Response $response
     */
    protected $response;

    /**
     * Language's messages
     *
     * @var array $language
     */
    protected $language;

    /**
     * Authorization flag
     *
     * @var bool $authorization
     */
    protected $authorization = true;

    /**
     * Project authorization flag
     *
     * @var bool $projectAuthorization
     */
    protected $projectAuthorization = false;

    /**
     * Accessible projects list
     *
     * @var array $accessibleProjects
     */
    protected $accessibleProjects = array();

    /**
     * This is the cached metadata
     *
     * @var array $cachedMeta
     */
    protected static $cachedMeta = array();

    /**
     * Acl Map
     *
     * @var array $aclMap
     */
    protected $aclMap = array(
        'get' => array(
            'action' => 'readF',
            'label' => 'Read'
        ),
        'list' => array(
            'action' => 'readF',
            'label' => 'Read'
        ),
        'related' => array(
            'action' => 'readF',
            'label' => 'Read'
        ),
        'post' => array(
            'action' => 'createF',
            'label' => 'Create'
        ),
        'delete' => array(
            'action' => 'deleteF',
            'label' => 'delete'
        ),
        'put' => array(
            'action' => 'updateF',
            'label' => 'Update'
        ),
        'patch' => array(
            'action' => 'updateF',
            'label' => 'Update'
        ),
    );

    /**
     * System level flag
     *
     * @var bool $systemLevel
     */
    protected $systemLevel = false;

    //protected $uses = array('acl','auditable');

    /**
     * The components used by this controller
     *
     * @var array $components
     */
    protected $components = array();

    /**
     * The event manager that we are using in order to extend functionality using components
     *
     * @var EventsManager $eventsManager
     */
    protected $eventsManager;

    /**
     * Standard setter function for the event manager
     *
     * @param EventsManagerInterface $eventsManager
     */
    public function setEventsManager(EventsManagerInterface $eventsManager): void
    {
        $this->eventsManager = $eventsManager;
    }

    /**
     * Standard getter function for event manager
     *
     * @return EventsManager
     */
    public function getEventsManager(): EventsManagerInterface
    {
        return $this->eventsManager;
    }

    /**
     * This function is called by Phalcon once it is created the controller object
     * This function must not be overridden by a child controller without calling
     * it first.
     *
     * @todo Add a way that will allow us to control the controllers and actions exempted from Authorization
     */
    public function initialize()
    {
        global $logger;

        $logger->debug('Gaia.core.controllers.rest->initialize()');
        //set the language
        //$this->setLanguage();

        $this->response = new Response();

        //print_r($this->dispatcher->getParams());exit;
        $this->controllerName = $this->dispatcher->getControllerName(); //controller
        $this->actionName = $this->dispatcher->getActionName(); //controller
        $modelName = \Phalcon\Text::camelize($this->controllerName);
        $namespace = 'Gaia\\MVC\\Models\\';
        $this->modelName = $namespace . $modelName; //model

        $this->id = $this->dispatcher->getParam("id"); //id
        $this->relationship = $this->dispatcher->getParam("relationship"); //relationship
        if ($this->actionName != 'options') {
            $this->authorize();
        }
        $logger->debug('-Gaia.core.controllers.rest->initialize()');
    }

    /**
     * This function preloads the component classes for use later
     *
     * @return void
     */
    final private function loadComponents()
    {
        global $logger;

        $logger->debug('Gaia.core.controllers.rest.loadComponents()');
        $logger->debug(
            $this->dispatcher->getControllerName() . '->' .
            $this->dispatcher->getActionName()
        );

        $this->eventsManager = new EventsManager();
        if (!isset($this->components) || empty($this->components)) {
            if (isset($this->uses) && !empty($this->uses)) {
                $logger->debug(print_r($this->uses, 1));

                foreach ($this->uses as $component) {
                    $componentClass = '\\Gaia\\MVC\\REST\\Controllers\\Components\\' . $component . 'Component';
                    $this->components[$component] = new $componentClass();
                    $this->eventsManager->attach(
                        'rest',
                        $this->components[$component]
                    );
                }
            }
        }

        $logger->debug('-Gaia.core.controllers.rest.loadComponents()');
    }

    /**
     * The event handler that allows us to call multiple mixin behaviors before
     * executing the desired action
     *
     * @param \Phalcon\Mvc\Dispatcher $dispatcher
     */
    public function beforeExecuteRoute(\Phalcon\Mvc\Dispatcher $dispatcher)
    {
        $this->loadComponents();
    }
    /**
     * Make currentUser available as global
     *
     * @global type $currentUser
     * @param  type $request
     */
    private function setUser($request)
    {
        global $currentUser;
        $token = str_replace('Bearer ', '', $request->headers['AUTHORIZATION']);
        $oAuthAccessToken = \Gaia\MVC\Models\Oauthaccesstoken::findFirst(array("access_token='" . $token . "'"));
        if (isset($oAuthAccessToken->user_id)) {
            $currentUser = \Gaia\MVC\Models\User::findFirst("username ='" . $oAuthAccessToken->user_id . "'");
        } else {
            throw new \Gaia\Exception\Access("Invalid Token");
        }
    }

    /**
     * This function is used to authorize the current request
     *
     * @return void
     */
    private function authorize()
    {
        global $currentUser;
        if ($this->authorization) {
            include_once APP_PATH . '/core/libs/authorization/oAuthServer.php';
            $request = \OAuth2\Request::createFromGlobals();
            if (!$server->verifyResourceRequest($request)) {
                $server->getResponse()->send();
            }
            $this->setUser($request);

            $permission = new \Gaia\MVC\Models\Permission();
            $permission->fetchUserPermissions($currentUser->id, $this->aclMap[$this->actionName]['action']);

            $this->getDI()->set(
                'permission',
                $permission
            );

            $resource = \Phalcon\Text::camelize($this->controllerName);

            //check ACL on Model
            $permission->checkModelAccess($resource, $this->aclMap[$this->actionName], null);

            //check ACL on Model's relationship
            $relationships = $this->getRelsWithMeta($this->request->get('rels'), Util::extractClassFromNamespace($this->modelName));
            $permission->checkRelsAccess($resource, $relationships, $this->aclMap[$this->actionName]);
        }
    }

    protected function getRelsWithMeta($rels, $modelName)
    {
        $relationships = [];

        $rels = isset($rels) ? explode(",", $rels) : array();
        foreach ($rels as $rel) {
            $relationships[$rel] = $this->getRelationshipMeta($modelName, $rel);
        }

        return $relationships;
    }

    /**
     * set language of errors responses
     */
    public function setLanguage()
    {
        //get the best language and all languages
        $bestLanguage = $this->request->getBestLanguage();
        $languages = $this->request->getLanguages();
        print_r($languages);

        //sort the languages for quality desc
        foreach ($languages as $key => $row) {
            $language[$key] = $row['language'];
            $quality[$key] = $row['quality'];
        }
        array_multisort($quality, SORT_DESC, $language, SORT_ASC, $languages);

        //veriry if exists the best language
        if (file_exists("../app/languages/" . $bestLanguage . ".php")) {
            include "../app/languages/" . $bestLanguage . ".php";

            //if not exist best language find the first language existing
        } else {
            //search for the first existing language
            $cont = 0;
            foreach ($languages as $value) {
                if (file_exists("../app/languages/" . $value['language'] . ".php")) {
                    include "../app/languages/" . $value['language'] . ".php";
                } else {
                    $cont++;
                }
            }

            //if not find any language set the desfault
            if ($cont == count($languages)) {
                include "../app/languages/en.php";
            }

        }

        //set the messages language
        $this->language = $messages;
    }


    /**
     * Method Http accept: OPTIONS
     *
     * @return JSON return list of functions available
     */
    public function optionsAction()
    {
        global $settings, $apiVersion;

        $modelName = $this->modelName;
        // only allow versioned API calls
        if (preg_match('@api/@', $this->request->getURI())) {
            $allowedMethods = (array) $settings->routes['rest']->$apiVersion->$modelName->allowedMethods;
            $this->response->setJsonContent(array('methods' => $allowedMethods));
        } else {
            $this->response->setStatusCode(400);
            $this->response->setJsonContent(array('status' => 'error', 'description' => 'Method only allowed for API'));
        }
        return $this->response;
    }


    /**
     * Method Http accept: GET
     *
     * @return \Phalcon\http\Response Retrive data by id
     * @throws \Phalcon\Exception
     */
    public function getAction()
    {
        global $logger;
        $logger->debug('Gaia.core.controllers.rest->getAction');
        $modelName = $this->modelName;

        if (!(isset($this->id) && !empty($this->id))) {
            throw new \Phalcon\Exception('Id must be set, please refer to guides.');
        }

        $query = $this->request->get('query', null, '');
        $sort = $this->request->get('sort', null, '');
        $order = $this->request->get('order', null, 'DESC');
        $include = ($this->request->get('include')) ? (explode(',', $this->request->get('include'))) : array();
        $fields = ($this->request->get('fields')) ? (explode(',', $this->request->get('fields'))) : array();
        $rels = ($this->request->get('rels')) ? (explode(',', $this->request->get('rels'))) : array();
        $rels = array_merge($rels, $include);
        $addRelFields = filter_var($this->request->get('addRelFields', null, false), FILTER_VALIDATE_BOOLEAN);

        if (Util::extractClassFromNamespace($modelName) === 'User' && $this->id === 'me') {
            $this->id = $GLOBALS['currentUser']->id;
        }

        $params = array(
            'id' => $this->id,
            'rels' => $rels,
            'fields' => $fields,
            'where' => $query,
            'sort' => $sort,
            'order' => $order,
            'addRelFields' => $addRelFields
        );

        $model = new $modelName();

        $data = $model->read($params);
        $dataArray = $this->extractData($data, $params, false, 'one');
        $this->finalData = $this->buildHAL($dataArray);

        $logger->debug('Firing afterRead event');
        $this->eventsManager->fire('rest:afterRead', $this);

        $logger->debug('-Gaia.core.controllers.rest->getAction');
        return $this->returnResponse($this->finalData);
    }


    /**
     * Method Http accept: GET
     *
     * @return JSON Retrive all data, with and without relationship
     * @throws \Phalcon\Exception
     */
    public function relatedAction()
    {
        $modelName = $this->modelName;

        if (!(isset($this->id) && !empty($this->id))) {
            throw new \Phalcon\Exception('Id must be set, please refer to guides.');
        }

        /**
         * @todo get from settings
         */
        $limit = $this->request->get('limit', null, 20);

        $requestPage = $this->request->get('page');
        $page = ($requestPage && $requestPage != 0 && $requestPage != 1) ? $requestPage : 1;
        $offset = ($page - 1) * $limit;
        $limit++;

        $query = $this->request->get('query', null, '');
        $sort = $this->request->get('sort', null, '');
        $order = $this->request->get('order', null, 'DESC');

        $fields = $this->request->get('fields', null, array());
        $relation = $this->dispatcher->getParam("relation");

        $params = array(
            'id' => $this->id,
            'related' => $relation,
            'fields' => $fields,
            'where' => $query,
            'sort' => $sort,
            'order' => $order,
            'limit' => $limit,
            'offset' => $offset,
        );

        $model = new $modelName();

        $data = $model->readRelated($params);

        $dataArray = $this->extractData($data, $params, false, 'all', $relation);
        $finalData = $this->buildHAL($dataArray, --$limit, $page);
        return $this->returnResponse($finalData);
    }

    /**
     * Method Http accept: GET
     *
     * @return JSON Retrive all data, with and without relationship
     */
    public function listAction()
    {
        global $logger;

        $logger->debug('Gaia.core.controllers.rest->listAction');

        $modelName = $this->modelName;

        /**
         * @todo get from settings
         */
        $limit = $this->request->get('limit', null, 20);

        $requestPage = $this->request->get('page');
        $page = ($requestPage && $requestPage != 0 && $requestPage != 1) ? $requestPage : 1;
        $offset = ($page - 1) * $limit;
        $limit++;

        $query = $this->request->get('query', null, '');
        $sort = $this->request->get('sort', null, '');
        $order = $this->request->get('order', null, 'DESC');
        $groupBy = $this->request->get('group', null, array());
        $count = $this->request->get('count', null, '');
        $having = $this->request->get('having', null, '');
        $fields = ($this->request->get('fields')) ? (explode(',', $this->request->get('fields'))) : array();
        $addRelFields = filter_var($this->request->get('addRelFields', null, false), FILTER_VALIDATE_BOOLEAN);
        $rels = ($this->request->get('rels')) ? (explode(',', $this->request->get('rels'))) : array();

        $params = array(
            'fields' => $fields,
            'rels' => $rels,
            'where' => $query,
            'sort' => $sort,
            'order' => $order,
            'limit' => $limit,
            'offset' => $offset,
            'groupBy' => $groupBy,
            'count' => $count,
            'having' => $having,
            'addRelFields' => $addRelFields
        );

        $model = new $modelName();
        $data = $model->readAll($params);

        $dataArray = $this->extractData($data, $params);
        $this->finalData = $this->buildHAL($dataArray, --$limit, $page);

        $logger->debug('Firing afterRead event');
        $this->eventsManager->fire('rest:afterRead', $this);

        $logger->debug('-Gaia.core.controllers.rest->listAction');

        return $this->returnResponse($this->finalData);
    }

    /**
     * Method Http accept: PUT (update but all the fields)
     * Save/update data
     */
    public function putAction()
    {
        $data = array('error' => array('code' => 405, 'description' => 'Method not allowed'));
        return $this->returnResponse($data);
    }

    /**
     * Method Http accept: PUT (update but all the fields)
     * Save/update data
     */
    public function putCollectionAction()
    {
        $data = array('error' => array('code' => 405, 'description' => 'Method not allowed'));
        return $this->returnResponse($data);
    }

    /**
     * Method Http accept: PATCH (update only the given fields)
     * Save/update data
     */
    public function patchAction()
    {
        $modelName = $this->modelName;
        $model = new $modelName();

        $util = new Util();
        $data = array();

        //get data
        $temp = $util->objectToArray($this->request->getJsonRawBody());

        //verify if exist more than one element
        if ($util->existSubArray($temp)) {
            if (isset($temp['data']['attributes'])) {
                if (isset($temp['data']['id']) && !empty($temp['data']['id'])) {
                    $temp['data']['attributes']['id'] = $temp['data']['id'];
                }
                $data[] = $temp['data']['attributes'];
            } else {
                $data = $temp;
            }

        } else {
            $data[0] = $temp;
        }

        //scroll through the array data and make the action save/update
        foreach ($data as $key => $value) {

            //if have param then update
            if (isset($value['id'])) {
                $this->id = $value['id'];

                //if passed by url
                $model = $modelName::findFirst('id = "' . $value['id'] . '"');

                //print_r($value);
                $model->assign($value);
                if ($model->save($value)) {
                    $this->eventsManager->fire('rest:afterUpdate', $this, $model);
                    $dataResponse = get_object_vars($model);
                    //update
                    $this->response->setStatusCode(200, "OK");

                    $data = $model->read(array('id' => $value['id']));

                    $dataArray = $this->extractData($data, [], false, 'one');
                    $finalData = $this->buildHAL($dataArray);
                    return $this->returnResponse($finalData);
                } else {
                    $errors = array();
                    foreach ($model->getMessages() as $message) {
                        $errors[] = $this->language[$message->getMessage()] ? $this->language[$message->getMessage()] : $message->getMessage();
                    }
                    $this->response->setJsonContent(
                        array(
                        'status' => 'ERROR',
                        'messages' => $errors
                        )
                    );
                }
            }
        } //end foreach

        return $this->response;

    }

    /**
     * Method Http accept: PATCH (update only the given fields)
     * Save/update data
     */
    public function patchCollectionAction()
    {
        $modelName = $this->modelName;
        //$model = new $modelName();

        //get data
        $data = $this->request->getJsonRawBody();

        if (!isset($data->collection) && is_array($data->collection)) {
            $data = array('error' => array('code' => 400, 'description' => 'Collection missing'));
            return $this->returnResponse($data);
        }

        $transactionManager = new TransactionManager();
        $transaction = $transactionManager->get();

        foreach ($data->collection as $index => $resource) {
            $temp = (array) $resource;
            if (!isset($temp['id'])) {
                $data = array('error' => array('code' => 400, 'description' => 'Id missing for record ' . $index));
                $transaction->rollback("Id missing for record " . $index);
                return $this->returnResponse($data);
            }

            $model = $modelName::findFirst($temp['id']);
            //$model = \Notes::findFirst($temp['id']);
            if (!isset($model->id)) {
                $data = array('error' => array('code' => 400, 'description' => 'Invalid record with identifier ' . $temp['id']));
                $transaction->rollback("Invalid record with identifier " . $temp['id']);
                return $this->returnResponse($data);
            }
            $updatedData = $model->cloneResult($model, $temp);
            $updatedData->setTransaction($transaction);
            if ($updatedData->save()) {
                $this->eventsManager->fire('rest:afterUpdate', $this, $updatedData);
            } else {
                $data = array('error' => array('code' => 400, 'description' => 'Unable to save ' . $temp['id'] . ', all changes reverted'));
                $transaction->rollback("Patched failed for " . $temp['id']);
                return $this->returnResponse($data);
            }
        }
        $transaction->commit();
        $data = array('status' => 'Data saved successfully');
        return $this->returnResponse($data);
    }

    /**
     * Method Http accept: POST (insert) and PUT (update)
     * Save/update data
     *
     * @return \Phalcon\Http\Response
     */
    public function postAction()
    {
        global $logger;
        $logger->debug('Gaia.core.controllers.rest->postAction');

        $modelName = $this->modelName;
        $model = new $modelName();
        $logger->debug('ModelCreated');

        $util = new Util();
        $data = array();

        $requestData = $util->objectToArray($this->request->getJsonRawBody());

        //verify if exist more than one element
        if ($util->existSubArray($requestData)) {
            if (isset($requestData['data']['attributes'])) {
                $data[] = $requestData['data']['attributes'];
            } else {
                $data = $requestData;
            }

        } else {
            $data[0] = $requestData;
        }

        //scroll through the arraay data and make the action save/update
        foreach ($data as $key => $value) {
            $logger->debug('Inside ForEach');
            //verify if any value is date (CURRENT_DATE, CURRENT_DATETIME), if it was replace for current date
            foreach ($value as $k => $v) {
                if ($v == "CURRENT_DATE") {
                    $now = new \DateTime();
                    $value[$k] = $now->format('Y-m-d');
                } elseif ($v == "CURRENT_DATETIME") {
                    $now = new \DateTime();
                    $value[$k] = $now->format('Y-m-d H:i:s');
                }
                $logger->debug('Setting date and time');
            }

            //if have param then update
            if (isset($this->id)) { //if passed by url
                $model = $modelName::findFirst($this->id);
            } else {
                $new_id = create_guid();
                $model->newId = $new_id;
                $value['id'] = $new_id;
            }
            $model->assign($value);

            $this->eventsManager->fire('rest:beforeCreate', $this, $model);

            if ($model->save($value)) {
                $logger->debug('Firing afterCreate Event');
                $model->id = $model->newId;
                $this->eventsManager->fire('rest:afterCreate', $this, $model);
                $dataResponse = get_object_vars($model);
                //update
                if (isset($this->id)) {
                    $this->response->setJsonContent(array('status' => 'OK'));
                    $logger->debug('Status is OK');
                    //insert
                } else {
                    $dataResponse['id'] = $model->newId;
                    $this->response->setStatusCode(201, "Created");

                    $data = $model->read(array('id' => $model->newId));

                    $dataArray = $this->extractData($data, [], false, 'one');
                    $finalData = $this->buildHAL($dataArray);
                    return $this->returnResponse($finalData);
                }

            } else {
                $errors = array();
                foreach ($model->getMessages() as $message) {
                    $errors[] = $this->language[$message->getMessage()] ? $this->language[$message->getMessage()] : $message->getMessage();
                }
                $logger->error($errors);
                $this->response->setJsonContent(
                    array(
                    'status' => 'ERROR',
                    'messages' => $errors
                    )
                );
            }


        } //end foreach

        $logger->debug('-Gaia.core.controllers.rest->postAction');

        return $this->response;
    }

    /**
     * Method Http accept: DELETE
     */
    public function deleteAction()
    {

        // need to evaluate if we need to use this function
        $modelName = $this->modelName;

        $model = $modelName::findFirst('id = "' . $this->id . '"');

        $GLOBALS['logger']->debug('Deleting a record');
        $GLOBALS['logger']->debug($model->id);
        $GLOBALS['logger']->debug($model->deleted);

        //delete if exists the object
        if ($model != false) {
            if ($model->delete() == true) {
                $this->eventsManager->fire('rest:afterDelete', $this, $model);
                $this->response->setJsonContent(array('data' => array('type' => Util::extractClassFromNamespace($modelName), "id" => $this->id)));
                $this->response->setStatusCode(200, "OK");
            } else {
                $this->response->setStatusCode(409, "Conflict");

                $errors = array();
                foreach ($model->getMessages() as $message) {
                    $errors[] = $this->language[$message->getMessage()] ? $this->language[$message->getMessage()] : $message->getMessage();
                }

                $this->response->setJsonContent(array('status' => "ERROR", 'messages' => $errors));
            }
        } else {
            $this->response->setStatusCode(409, "Conflict");
            $this->response->setJsonContent(array('status' => "ERROR", 'messages' => array("O elemento não existe")));
        }

        return $this->response;
    }

    /**
     * Method Http accept: DELETE
     */
    public function deleteCollectionAction()
    {
    }

    /**
     * This function builds the custom HAL data
     *
     * @param  array $data
     * @param  int   $limit
     * @param  int   $page
     * @return array $hal
     */
    protected function buildHAL(array $data, $limit = -1, $page = -1)
    {
        $hal = $data;
        $query = $this->request->getQuery();
        $self = $next = $prev = array();

        $endPage = true;
        if ($limit != -1) {
            if (isset($hal['data'][$limit])) {
                unset($hal['data'][$limit]);
                $endPage = false;
            }

            if ($page == -1) {
                $page = 1;
            }
            if (!isset($query['page'])) {
                $query['page'] = $page;
            }
        }

        foreach ($query as $param => $value) {
            if ($param != '_url') {
                $self[] = $param . '=' . $value;
                if (isset($limit)) {
                    if ($param == 'page') {
                        $next[] = $param . '=' . ($page + 1);
                        $prev[] = $param . '=' . ($page - 1);
                    } else {
                        $next[] = $param . '=' . $value;
                        $prev[] = $param . '=' . $value;
                    }

                }
            }
        }

        /*
         if ($limit != -1)
         {
         foreach($hal['data'] as $count => &$singleObj){
         $pageOffset = ($page-1) + $count;
         $singleObj['link']['self'] =
         }
         }
         */


        $hal['meta']['count'] = count($data['data']);

        //$hal['meta']['_links']['self']['href'] = $query['_url'];
        $hal['meta']['links']['self']['href'] = $query['_url'];
        //$hal['links']['self']['href'] = $query['_url'];
        if (!empty($self)) {
            //$hal['meta']['_links']['self']['href'] .= '?'.implode('&',$self);
            $hal['meta']['links']['self']['href'] .= '?' . implode('&', $self);
            //$hal['links']['self']['href'] .= '?'.implode('&',$self);
        }
        if (!empty($next) && !$endPage) {
            //$hal['meta']['_links']['next']['href'] = $query['_url'].'?'.  implode('&',$next);
            $hal['meta']['links']['next']['href'] = $query['_url'] . '?' . implode('&', $next);
            //$hal['links']['next']['href'] = $query['_url'].'?'.  implode('&',$next);
        }

        if (!empty($prev) && $page > 1) {
            //$hal['meta']['_links']['prev']['href'] = $query['_url'].'?'.  implode('&',$prev);
            $hal['meta']['links']['prev']['href'] = $query['_url'] . '?' . implode('&', $prev);
            //$hal['links']['prev']['href'] = $query['_url'].'?'.  implode('&',$prev);
        }

        return $hal;
    }

    /**
     * Return the response, allow the error code handling here
     *
     * @param  array $data
     * @return \Phalcon\Http\Response
     */
    protected function returnResponse(array $data)
    {
        $this->response->setJsonContent($data);
        $this->response->setContentType('text/json');

        if (isset($data['error'])) {
            $this->response->setStatusCode($data['error']['code']);
        }

        return $this->response;
    }

    /**
     * Extract collection data to json
     *
     * @param  object $data                 object collection with data.
     * @param  array  $params               User request parameters.
     * @param  $requiredScalarFields Boolean flag that represents that scalar fields are required or not.
     *                               If not required then that field will be added into the object.
     * @param  string $type
     * @param  string $relation
     * @return string $data
     * @todo   optimize the code
     * @todo   build HAL within
     */
    protected function extractData($data, $params = [], $requireScalarFields = false, $type = 'all', $relation = '')
    {
        $modelName = strtolower($this->modelName);
        $permission = $this->getDI()->get('permission');
        if (!empty($relation)) {
            $modelName = strtolower($relation);
        }
        $jsonapi_org = array();
        $jsonapi_org['data'] = array();

        //data extraction conditional statements (starts)..
        if ($data['baseModel'] instanceof Resultset) {
            $data['baseModel']->setHydrateMode(Resultset::HYDRATE_ARRAYS);

            $result = array();
            foreach ($data['baseModel'] as $values) {

                if (isset($params['fields']) && !empty($params['fields'])) {
                    $values = $this->updateFields($values, $params, $requireScalarFields);
                }

                if (empty($permission->getAllowedFields())) {
                    $modelAlias = Util::extractClassFromNamespace($this->modelName);
                    $permission->applyACLOnFields($values, $this->aclMap[$this->actionName]['action'], $modelAlias);
                }


                $values = $this->filterFieldsByACL($permission->getAllowedFields(), $values);

                /**
                 * First check whether there is any array of the current model or not. If its array then
                 * convert its values to a temp array and remove it because this array will cause error
                 * while creating the JSON API included array. The current model array as a result set can
                 * be caused when User gives input to columns array of queryBuilder as "CurrentModel.*".
                 */
                $modelName = Util::extractClassFromNamespace($this->modelName);
                if ($values[$modelName]) {
                    $baseModelArray = $values[$modelName];
                    unset($values[$modelName]);

                    foreach ($baseModelArray as $attr => $value) {
                        $values[$attr] = $value;
                    }
                }

                foreach ($values as $attr => $value) {
                    if (!isset($result[$values['id']])) {
                        $result[$values['id']] = array();
                    }

                    if (is_array($value)) {
                        $relDef = $this->getRelationshipMeta(Util::extractClassFromNamespace($this->modelName), $attr);
                        if ($relDef['type'] == 'hasMany' || $relDef['type'] == 'hasManyToMany') {
                            if (!empty($value['id'])) {
                                $result[$values['id']][$attr][] = $value;
                            }
                        } else {
                            $result[$values['id']][$attr] = $value;
                        }
                    } else {
                        $result[$values['id']][$attr] = $value;
                    }
                }
            }
            //die();
        } elseif (is_array($data)) {
            $result = $data;
        } else {
            $result = array();
        }
        //data extraction conditional statements (ends)..

        unset($data['baseModel']);

        $this->extractManyToManyRelationships($data, $result);

        //preparing jsonapi (starts)..
        $this->removeDuplicates($result);

        $count = 0;

        if ($type == 'all') {
            // prepare the data for JSONAPI.org standard
            foreach ($result as $object) {
                $modelName = Util::extractClassFromNamespace($this->modelName);
                $jsonapi_org['data'][$count]['type'] = $modelName;

                foreach ($object as $attr => $val) {
                    if (!is_array($val)) {
                        // process attributes
                        if ($attr == 'id') {
                            $jsonapi_org['data'][$count]['id'] = $val;
                        } else {
                            $jsonapi_org['data'][$count]['attributes'][$attr] = $val;
                        }
                    } else {
                        // process relationships
                        $included = array();
                        if (isset($val['id'])) {
                            $jsonapi_org['data'][$count]['relationships'][$attr] = array();
                            $relationDefinition = $this->getRelationshipMeta($modelName, $attr);
                            $relatedModelKey = 'relatedModel';
                            if ($relationDefinition['type'] == 'hasManyToMany') {
                                $relatedModelKey = 'secondaryModel';
                            }
                            $included['type'] = $jsonapi_org['data'][$count]['relationships'][$attr]['data']['type'] = strtolower(Util::extractClassFromNamespace($relationDefinition[$relatedModelKey]));
                            $id = '';
                            $included['id'] = $jsonapi_org['data'][$count]['relationships'][$attr]['data']['id'] = $val['id'];
                            $id = $val['id'];
                            unset($val['id']);

                            $included['attributes'] = $val;

                            $jsonapi_org['included'][($this->modelName . $id)] = $included;
                        } else {
                            foreach ($val as $idx => $object) {
                                if (isset($object['id'])) {
                                    $included = array();
                                    $jsonapi_org['data'][$count]['relationships'][$attr]['data'][$idx] = array();
                                    $relationDefinition = $this->getRelationshipMeta($modelName, $attr);
                                    $relatedCount = 0;
                                    $relatedModelKey = 'relatedModel';
                                    if ($relationDefinition['type'] == 'hasManyToMany' && isset($relationDefinition['secondaryModel'])) {
                                        $relatedModelKey = 'secondaryModel';
                                    }
                                    $included['type'] = $jsonapi_org['data'][$count]['relationships'][$attr]['data'][$idx]['type'] = strtolower(Util::extractClassFromNamespace($relationDefinition[$relatedModelKey]));
                                    $id = '';
                                    $included['id'] = $jsonapi_org['data'][$count]['relationships'][$attr]['data'][$idx]['id'] = $object['id'];
                                    $id = $object['id'];
                                    unset($object['id']);

                                    $included['attributes'] = $object;
                                    $jsonapi_org['included'][($relationDefinition[$relatedModelKey] . $id)] = $included;
                                }
                            }
                        }
                    }
                }
                $count++;
            }
        } else {

            foreach ($result as $object) {

                $modelName = Util::extractClassFromNamespace($this->modelName);
                $jsonapi_org['data']['type'] = strtolower($modelName);

                foreach ($object as $attr => $val) {
                    if (!is_array($val)) {
                        // process attributes
                        if ($attr == 'id') {
                            $jsonapi_org['data']['id'] = $val;
                        } else {
                            $jsonapi_org['data']['attributes'][$attr] = $val;
                        }
                    } else {
                        // process relationships
                        if (isset($val['id'])) {
                            $included = array();
                            $jsonapi_org['data']['relationships'][$attr] = array();
                            $relationDefinition = $this->getRelationshipMeta($modelName, $attr);
                            $relatedCount = 0;
                            $relatedModelKey = 'relatedModel';
                            if ($relationDefinition['type'] == 'hasManyToMany') {
                                $relatedModelKey = 'secondaryModel';
                            }
                            $included['type'] = $jsonapi_org['data']['relationships'][$attr]['data']['type'] = strtolower(Util::extractClassFromNamespace($relationDefinition[$relatedModelKey]));
                            $id = '';
                            $included['id'] = $jsonapi_org['data']['relationships'][$attr]['data']['id'] = $val['id'];
                            $id = $val['id'];
                            unset($val['id']);

                            $included['attributes'] = $val;
                            $jsonapi_org['included'][($relationDefinition[$relatedModelKey] . $id)] = $included;
                        } else {
                            foreach ($val as $idx => $object) {
                                if (isset($object['id'])) {
                                    $included = array();
                                    $jsonapi_org['data']['relationships'][$attr]['data'][$idx] = array();
                                    $relationDefinition = $this->getRelationshipMeta($modelName, $attr);
                                    $relatedCount = 0;
                                    $relatedModelKey = 'relatedModel';
                                    if ($relationDefinition['type'] == 'hasManyToMany') {
                                        $relatedModelKey = 'secondaryModel';
                                    }
                                    $included['type'] = $jsonapi_org['data']['relationships'][$attr]['data'][$idx]['type'] = strtolower(Util::extractClassFromNamespace($relationDefinition[$relatedModelKey]));
                                    $id = '';
                                    $included['id'] = $jsonapi_org['data']['relationships'][$attr]['data'][$idx]['id'] = $object['id'];
                                    $id = $object['id'];
                                    unset($object['id']);

                                    $included['attributes'] = $object;
                                    $jsonapi_org['included'][($relationDefinition[$relatedModelKey] . $id)] = $included;
                                }
                            }
                        }
                    }
                }
                $count++;
            }
        }
        if (!empty($jsonapi_org['included'])) {
            $jsonapi_org['included'] = array_values($jsonapi_org['included']);
        }
        $result = $jsonapi_org;

        //preparing jsonapi (ends)..

        // do not allow passwords to be returned
        $this->removePassword($result);

        return $result;
    }

    /**
     * This function is used to extract hasManyToMany relationships and set each of
     * related data to its base model.
     *
     * @todo  Find some other solution instead of matching Resultset type.
     * @param array $data   Array containing relationship result sets.
     * @param array $result Array of result that(currently) contains basemodel data.
     */
    private function extractManyToManyRelationships($data, &$result)
    {
        //iterate many-many relationships
        foreach ($data as $relName => $relData) {

            //now extract and merge many-to-many with base model
            $relData->setHydrateMode(Resultset::HYDRATE_ARRAYS);

            $type = explode("\\", get_class($relData));
            $type = end($type);

            // This is used when User has not requested fields related to hasManyToMany relationship.
            if ($type == "Complex") {
                $this->setAllRelatedFieldsToBaseModel($result, $relData, $relName);
            }
            // This is used when User requested fields related to hasManyToMany relationship.
            elseif ($type == "Simple") {
                $this->setSomeRelatedFieldsToBaseModel($result, $relData, $relName);
            }
        }
    }

    /**
     * This function is used to set related data to base model with all related fields.
     *
     * @param array  $result  Array of result that(currently) contains basemodel data.
     * @param array  $relData Relationship result from database.
     * @param string $relName Name of the relationship.
     */
    public function setAllRelatedFieldsToBaseModel(&$result, $relData, $relName)
    {
        $relMeta = $this->getRelationshipMeta(Util::extractClassFromNamespace($this->modelName), $relName);
        $relatedModelName = Util::extractClassFromNamespace($relMeta['relatedModel']);
        $rhsKey = $relMeta['rhsKey'];

        //iterate each relationship
        foreach ($relData as $model) {
            $modelId = $model[$relatedModelName][$rhsKey];
            if ($result[$modelId]) {
                unset($model[$relatedModelName]);
                $result[$modelId][$relName][] = $model;
            }
        }
    }

    /**
     * This function is used to set related data to base model with some requested related fields. In this
     * we'll get result set in a form of scalar field, not as a object representing model.
     *
     * @param array  $result  Array of result that(currently) contains basemodel data.
     * @param array  $relData Relationship result from database.
     * @param string $relName Name of the relationship.
     */
    public function setSomeRelatedFieldsToBaseModel(&$result, $relData, $relName)
    {
        $relatedModel = [];
        $relMeta = $this->getRelationshipMeta(Util::extractClassFromNamespace($this->modelName), $relName);

        foreach ($relData as $model) {
            $lhsKey = $relMeta['lhsKey'];
            $rhsKey = $relMeta['rhsKey'];

            // iterate model attributes
            foreach ($model as $key => $value) {

                //we shouldn't have to pass middle table information to user, that's why this check is created.
                ($key != $lhsKey && $key != $rhsKey) && ($relatedModel[$model['id']][$key] = $value);
            }

            if ($result[$model[$rhsKey]]) {
                $secondaryModel = $relatedModel[$model[$lhsKey]];
                $result[$model[$rhsKey]][$relName][] = $secondaryModel;
            }
        }
    }

    /**
     * This function is used to retrieve the relationship metadata for a model
     *
     * @param  string $modelName
     * @param  string $rel
     * @return array
     */
    final private function getRelationshipMeta($modelName, $rel)
    {
        if (isset(self::$cachedMeta[$modelName][$rel])) {
            return self::$cachedMeta[$modelName][$rel];
        }
        $modelMetadata = $this->di->get('metaManager')->getModelMeta($modelName);

        $relatedMetadata = array();
        foreach ($modelMetadata['relationships'] as $relationType => $related) {
            foreach ($related as $relName => $relDef) {
                if ($relName == $rel) {
                    $relatedMetadata = $relDef;
                    $relatedMetadata['type'] = $relationType;
                    break 2;
                }
            }
        }
        self::$cachedMeta[$modelName][$rel] = $relatedMetadata;
        return $relatedMetadata;
    }

    /**
     * This function is responsible for removing password from the result set
     *
     * @param array $array
     */
    final private function removePassword(array &$array)
    {
        foreach ($array as $key => &$value) {
            if (is_array($value)) {
                $this->removePassword($value);
            } elseif ($key == 'password') {
                unset($array[$key]);
            }
        }
    }

    /**
     * If there are multiple hasMany relationships in the result of a query
     * then we run into the issue of getting more rows with duplicate data even
     * for the related entities this function is used to remove the duplicate
     * entries before they are processes further
     *
     * @param  array $array
     * @return void
     */
    final private function removeDuplicates(array &$array)
    {
        foreach ($array as $id => &$obj) {
            foreach ($obj as $attr => &$data) {
                if (is_array($data) && isset($data['0'])) {
                    $temp = array();
                    foreach ($data as $relatedIndex => &$relatedData) {
                        if (isset($relatedData['id'])) {
                            $temp[$relatedData['id']] = $relatedData;
                            unset($data[$relatedIndex]);
                        }
                    }
                    $data = array_values(array_merge($data, $temp));
                }
            }
        }
    }

    /**
     * This function is called when user requested some specific fields. If a related model field is requested then by
     * default Phalcon consider it as a scalar field and we have no way to map this field into the object of related
     * model. That's why for the requested related model fields we have used alias e.g. members_username. So for that
     * purpose we map related model fields into the array with the key of relationship name. e.g. this function will
     * convert [members_username=>"Rana Nouman"] to ["members" => ["username"=> "Rana Nouman"]].
     *
     * @method updateFields
     * @param  $values               Array of values retreived against the model and/or related model from the database.
     * @param  $params               Array of user requested parameters.
     * @param  $requiredScalarFields Boolean flag that represents that scalar fields are required or not.
     *                               If not required then that field will be added into the object.
     * @return array
     */
    final private function updateFields($values, $params, $requireScalarFields)
    {
        $updatedValues = [];
        foreach ($values as $fieldName => $value) {
            if (str_contains($fieldName, "_")) {
                list($relName, $relfieldName) = explode("_", $fieldName);

                // Check if user has given alias for the field or not, if given then use that one.
                foreach ($params['fields'] as $requestedField) {
                    if (str_contains(strtoupper($requestedField), "AS")
                        && str_contains($requestedField, str_replace("_", ".", $fieldName))
                    ) {
                        list(, , $alias) = explode(" ", $requestedField);
                        $relfieldName = $alias;
                    }
                }

                // If scalar fields are not required then if condition will work.
                if (in_array($relName, $params['rels']) && !$requireScalarFields) {
                    $updatedValues[$relName][$relfieldName] = $value;
                } else {
                    $updatedValues[$relfieldName] = $value;
                }
            } else {
                $updatedValues[$fieldName] = $value;
            }
        }
        return $updatedValues;
    }

    /**
     * This function will only return those fields on which user has access.
     *
     * @method filterFieldsByACL
     * @param  $allowedFields Array of fields on which user has access.
     * @param  $values        Result retreived from database.
     */
    protected function filterFieldsByACL($allowedFields, $values)
    {
        $filteredValues = [];

        foreach ($values as $key => $value) {
            if (getType($value) === "array") {
                foreach ($value as $fieldName => $fieldValue) {
                    $field = "{$key}.{$fieldName}";
                    $filteredValues[$key][$fieldName] = (in_array($field, $allowedFields)) ? $fieldValue : null;
                }
            } else {
                $modelName = Util::extractClassFromNamespace($this->modelName);
                $field = "{$modelName}.{$key}";
                $filteredValues[$key] = (in_array($field, $allowedFields)) ? $value : null;
            }
        }

        return $filteredValues;
    }
}
