<?php

namespace Foundation\Mvc;

use Phalcon\Http\Response;
use Phalcon\Mvc\Model\Resultset;
use Phalcon\DI;
use Phalcon\Mvc\Dispatcher;
use Phalcon\Text;
use function Foundation\create_guid as create_guid;
use Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;
 
class RestController extends \Phalcon\Mvc\Controller
{

    /**
    * Model's name is registered from controller via parameter
    */
    protected $modelName;

    /**
     * Model object in question
     * @var mixed
     */
    protected $model;
            
    /**
    * Model's name of relationship model
    */
    protected $relationship=null;

    /**
    * Name of controller is passed in parameter 
    */
    protected $controllerName;
    
    /**
    * Name of action is passed in parameter 
    */
    protected $actionName;
    /**
    * Value of primary key field of model (passed in parameter)
    */
    protected $id;

    /**
    * Parameters
    */
    protected $params;

    /**
     * Response object
     * @var Phalcon\Http\Response
     */
    protected $response;

    /**
     * Language's messages
     * @var array
     */
    protected $language;

    /**
     * Authorization flag
     * @var bool
     */
    protected $authorization = true;
    
    /**
     * Project authorization flag
     * @var bool
     */
    protected $projectAuthorization = false;

    /**
     * Accessible projects list
     * @var array
     */
    protected $accessibleProjects = array();

    /**
     * Acl Map
     * @var array
     */
    protected $aclMap = array(
        'get' => 'read',
        'list' => 'read',
        'related' => 'read',
        'post' => 'create',
        'delete' => 'delete',
        'put' => 'update',
        'patch' => 'update'
    );

    /**
     * System level flag
     * @var bool
     */
    protected $systemLevel = false;    
    
    //protected $uses = array('acl','auditable');
    
    protected $components = array();
    
    /**
     * @todo Add a way that will allow us to control the controllers and actions
     * exempted from Authorization
     */
    public function initialize()
    {
        //set the language
        $this->setLanguage();

        $this->response = new Response();
        
    	//print_r($this->dispatcher->getParams());exit;
    	$this->controllerName = $this->dispatcher->getControllerName();//controller
        $this->actionName = $this->dispatcher->getActionName();//controller

        $this->modelName = $this->controllerName;//model
        $this->id = $this->dispatcher->getParam("id");//id
        $this->relationship = $this->dispatcher->getParam("relationship");//relationship
        if ($this->actionName != 'options')
            $this->authorize();
    }
    
    /**
     * This function preloads the component classes for use later
     */
    final private function loadComponents(){
        if (!isset($this->components) || empty($this->components))
        {
            if (isset($this->uses) && !empty($this->uses))
            {
                foreach($this->uses as $component)
                {
                    $componentClass = '\\Foundation\\Mvc\\Controller\\Component\\'.strtolower($component).'Component';
                    $this->components[$component] = new $componentClass();
                }
            }
        }
    }

    /**
     * The event handler that allows us to call multiple mixin behaviors before
     * executing the desired action
     * 
     * @todo Examine performance
     * @todo preload the mixins
     * @param type $dispatcher
     */
    public function beforeExecuteRoute($dispatcher)
    {
        $this->loadComponents();
        $method = 'before'.\Phalcon\Text::camelize($dispatcher->getActionName());
        if (isset($this->uses) && !empty($this->uses))
        {
            foreach($this->uses as $component)
            {
                if ($this->components[$component]->initialize())
                {
                    if (method_exists($this->components[$component],$method))
                    {
                        if (!($this->components[$component]->$method($this)))
                        {
                            throw new \Phalcon\Exception('Failed while executing the function '.$method.' for the Component::'.$component);
                        }
                    }
                }
                else
                {
                    throw new \Phalcon\Exception('Failed to initialize the Component::'.$component);
                }
            }
        }
    }

        /**
     * The event handler that allows us to call multiple mixin behaviors before
     * executing the desired action
     * 
     * @todo Examine performance
     * @todo preload the mixins
     * @param type $dispatcher
     */
    public function afterExecuteRoute($dispatcher)
    {
        $method = 'after'.\Phalcon\Text::camelize($dispatcher->getActionName());
        if (isset($this->uses) && !empty($this->uses))
        {
            foreach($this->uses as $component)
            {
                if (method_exists($this->components[$component],$method))
                {
                    if (!($this->components[$component]->$method($this)))
                    {
                        throw new \Exception('Failed while executing the function '.$method.' for the Component::'.$component);
                    }
                }
            }
        }
    }

    /**
     * Make currentUser available as global
     * 
     * @global type $currentUser
     * @param type $reuqest
     */
    private function setUser($reuqest)
    {
        global $currentUser;
        $token = str_replace('Bearer ','',$reuqest->headers['AUTHORIZATION']);
        $oAuthAccessToken = \Oauthaccesstokens::findFirst(array("access_token='".$token."'"));
        if (isset($oAuthAccessToken->user_id))
        {
            $currentUser = \Users::findFirst("username ='".$oAuthAccessToken->user_id."'");
        }
        else
        {
            $this->response->setStatusCode(403, "Forbidden");
            $this->response->setJsonContent(array('error' => 'Invalid Token'));     

            $this->response->send();
            exit();            
        }
    }
    
    private function authorize()
    {
        global $currentUser;
        if ($this->authorization)
        {
            require_once APP_PATH.'/foundation/libs/oAuthServer.php';
            $reuqest = \OAuth2\Request::createFromGlobals();
            if (!$server->verifyResourceRequest($reuqest)) {
                $server->getResponse()->send();
                exit();
            }
            $this->setUser($reuqest);
            
            if ($this->projectAuthorization)
            {
                $projects = array();
                // Check for access on Projects Module
                if (!empty($this->id))
                {
                    $modelName = $this->modelName;
                    $data = $modelName::find($this->id);
                    $identifier = 'projectId';
                    if (strtolower($modelName) === 'projects')
                    {
                        $identifier = 'id';
                    }
                    
                    if (isset($data[0]->$identifier))
                    {
                        $permission = \Foundation\Acl::hasProjectAccess($currentUser->id, $this->controllerName, $this->aclMap[$this->actionName], $data[0]->$identifier);
                        if ($permission != 0)
                        {
                            $projects[] = $data[0]->$identifier;
                        }
                    }
                }
                else
                {
                    $projects = \Foundation\Acl::getProjects($currentUser->id, $this->controllerName, $this->aclMap[$this->actionName]);
                }
                
                if (empty($projects))
                {
                    $this->response->setStatusCode(403, "Forbidden");
                    $this->response->setJsonContent(array('error' => 'Access Denied - Check ACL'));     

                    $this->response->send();
                    exit();
                }
                else
                {
                    $this->accessibleProjects = $projects;
                }
            }
            elseif($this->systemLevel)
            {
                $permission = \Foundation\Acl::roleHasAccess('1', $this->controllerName, $this->aclMap[$this->actionName]);
                
                if ($permission == 0)
                {
                    $this->response->setStatusCode(403, "Forbidden");
                    $this->response->setJsonContent(array('error' => 'Access Denied - Check ACL'));     

                    $this->response->send();
                    exit();
                }
            }
        }        
    }

    /**
     * set language of errors responses
     */
    public function setLanguage(){
        //get the best language and all languages
        $bestLanguage = $this->request->getBestLanguage();
        $languages = $this->request->getLanguages();

        //sort the languages for quality desc
        foreach ($languages as $key => $row) {
            $language[$key]  = $row['language'];
            $quality[$key] = $row['quality'];
        }
        array_multisort($quality, SORT_DESC, $language, SORT_ASC, $languages);

        //veriry if exists the best language
        if ( file_exists("../app/languages/".$bestLanguage.".php") ){
            require "../app/languages/".$bestLanguage.".php";

        //if not exist best language find the first language existing
        }else{
            //search for the first existing language
            $cont = 0;
            foreach ($languages as $value) {
                if ( file_exists("../app/languages/".$value['language'].".php") ){
                    require "../app/languages/".$value['language'].".php";
                }
                else $cont++;
            }

            //if not find any language set the desfault
            if ( $cont == count($languages) ){
                require "../app/languages/en.php";
            }

        }

        //set the messages language 
        $this->language = $messages;
    }

    
    /**
     * Method Http accept: OPTIONS
     * @return JSON return list of functions available
     */
    public function optionsAction(){
        global $settings,$apiVersion;
        
        $modelName = $this->modelName;
        // only allow versioned API calls
        if (preg_match('@api/@',$this->request->getURI()))
        {
            $allowedMethods = (array) $settings->routes['rest']->$apiVersion->$modelName->allowedMethods;
            $this->response->setJsonContent(array('methods' => $allowedMethods));
        }
        else
        {
            $this->response->setStatusCode(400);
            $this->response->setJsonContent(array('status' => 'error','description' => 'Method only allowed for API'));
        }
        return $this->response;
    }
    
    
    /**
     * Method Http accept: GET
     * @return JSON Retrive data by id
     */
    public function getAction(){

        $modelName = $this->modelName;

        if (!(isset($this->id) && !empty($this->id)))
        {
            throw new \Phalcon\Exception('Id must be set, please refer to guides.');
        }
        
        $query = $this->request->get('query',null,'');
        $sort = $this->request->get('sort',null,'');
        $order = $this->request->get('order',null,'DESC');
        
        $fields = $this->request->get('fields',null,array());
        $rels = ($this->request->get('rels'))?(explode(',',$this->request->get('rels'))):array();
        
        $params = array(
            'id' => $this->id,
            'rels' => $rels,
            'fields' => $fields,
            'where' => $query,
            'sort' => $sort,
            'order' => $order,
        );
        
        $model = new $modelName;
        
        $data = $model->read($params);
        
        $dataArray = $this->extractData($data);
        $finalData = $this->buildHAL($dataArray);
        return $this->returnResponse($finalData);
    }


    /**
     * Method Http accept: GET
     * @return JSON Retrive all data, with and without relationship
     */
    public function relatedAction()
    {
        $modelName = $this->modelName;
        
        if (!(isset($this->id) && !empty($this->id)))
        {
            throw new \Phalcon\Exception('Id must be set, please refer to guides.');
        }
        
        /**
         * @todo get from settings
         */
        $limit = $this->request->get('limit',null,20);
        
        $requestPage = $this->request->get('page');
        $page = ($requestPage && $requestPage != 0 && $requestPage != 1)?$requestPage:1;
        $offset = ($page-1) * $limit;
        $limit++;
        
        $query = $this->request->get('query',null,'');
        $sort = $this->request->get('sort',null,'');
        $order = $this->request->get('order',null,'DESC');
        
        $fields = $this->request->get('fields',null,array());
        $relation = $this->dispatcher->getParam("relation");
        
        $params = array(
            'id' => $this->id,
            'related' =>$relation,
            'fields' => $fields,
            'where' => $query,
            'sort' => $sort,
            'order' => $order,
            'limit'=> $limit,
            'offset' => $offset,
        );
        
        $model = new $modelName;
        
        $data = $model->readRelated($params);
        
        $dataArray = $this->extractData($data);
        $finalData = $this->buildHAL($dataArray,--$limit,$page);
        return $this->returnResponse($finalData);
    }
    
    /**
     * Method Http accept: GET
     * @return JSON Retrive all data, with and without relationship
     */
    public function listAction()
    {
        $modelName = $this->modelName;
        
        /**
         * @todo get from settings
         */
        $limit = $this->request->get('limit',null,20);
        
        $requestPage = $this->request->get('page');
        $page = ($requestPage && $requestPage != 0 && $requestPage != 1)?$requestPage:1;
        $offset = ($page-1) * $limit;
        $limit++;
        
        $query = $this->request->get('query',null,'');
        $sort = $this->request->get('sort',null,'');
        $order = $this->request->get('order',null,'DESC');
        
        $fields = $this->request->get('fields',null,array());
        
        $rels = ($this->request->get('rels'))?(explode(',',$this->request->get('rels'))):array();

        $params = array(
            'fields' => $fields,
            'rels' => $rels,
            'where' => $query,
            'sort' => $sort,
            'order' => $order,
            'limit'=> $limit,
            'offset' => $offset
        );
        
        $model = new $modelName;
        $data = $model->readAll($params);

        $dataArray = $this->extractData($data);
        $finalData = $this->buildHAL($dataArray,--$limit,$page);
        return $this->returnResponse($finalData);

/*        $identifier = 'projectId';

      
        if (strtolower($modelName) === 'projects')
        {
            $identifier = 'id';
        }


        //data of more models (relationship)
        if ( $this->relationship!=null ){
            $data = $modelName::findFirst( $this->id );

			$relationship = $this->relationship;
            
			$data = $data->$relationship;

        //data of one model
        }else{
            $query  = $modelName::query();
            if ($this->projectAuthorization)
            {
                $conditions = array();
                foreach ($this->accessibleProjects as $accessibleProject)
                {
                    $conditions[] = $identifier."='".$accessibleProject."'";
                }
                $condition = "(".implode(' OR ',$conditions).")";
                $query->where($condition);
            }
            $data = $query->execute();
            
        }      
*/
    }

    /**
     * Method Http accept: PUT (update but all the fields)
     * Save/update data 
     */
    public function putAction()
    {
        print 'here';
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

        if (!isset($data->collection) && is_array($data->collection))
        {
            $data = array('error' => array('code' => 400, 'description' => 'Collection missing'));
            return $this->returnResponse($data);
        }

        $transactionManager = new TransactionManager();
        $transaction = $transactionManager->get();
        
        foreach ($data->collection as $index => $resource)
        {
            $temp = (array) $resource;
            if (!isset($temp['id']))
            {
                $data = array('error' => array('code' => 400, 'description' => 'Id missing for record '.$index));
                $transaction->rollback("Id missing for record ".$index);
                return $this->returnResponse($data);
            }
            
//            $model = $modelName::findFirst($temp['id']);
            $model = \Notes::findFirst($temp['id']);
            if (!isset($model->id))
            {
                $data = array('error' => array('code' => 400, 'description' => 'Invalid record with identifier '.$temp['id']));
                $transaction->rollback("Invalid record with identifier ".$temp['id']);
                return $this->returnResponse($data);                
            }
            $updatedData = $model->cloneResult($model, $temp);
            $updatedData->setTransaction($transaction);
            if(!$updatedData->save())
            {
                $data = array('error' => array('code' => 400, 'description' => 'Unable to save '.$temp['id'].', all changes reverted'));
                $transaction->rollback("Patched failed for ".$temp['id']);
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
     */
    public function postAction()
    {
        $modelName = $this->modelName;      
        $model = new $modelName();

        $util = new \Util();
        $data = array();

        //get data
        $temp = $util->objectToArray($this->request->getJsonRawBody());

        //verify if exist more than one element
        if ( $util->existSubArray($temp) )
            $data = $temp;
        else
            $data[0] = $temp;

        //scroll through the arraay data and make the action save/update
        foreach ($data as $key => $value) {

             //verify if any value is date (CURRENT_DATE, CURRENT_DATETIME), if it was replace for current date
            foreach ($value as $k => $v) {
                if ( $v=="CURRENT_DATE" ){
                    $now = new \DateTime();
                    $value[$k] =  $now->format('Y-m-d'); 
                }else if ( $v=="CURRENT_DATETIME" ){
                    $now = new \DateTime();
                    $value[$k] =  $now->format('Y-m-d H:i:s'); 
                }
            }

            //if have param then update
            if ( isset($this->id) ) //if passed by url
                $model = $modelName::findFirst($this->id);
            else
            {
                $new_id = create_guid();
                $value['id'] = $new_id;
            }

            if ( $model->save($value) ){
                $dataResponse = get_object_vars($model);
                //update
                if ( isset($this->id) ){
                    $this->response->setJsonContent(array('status' => 'OK'));
                //insert
                }else{
                    $dataResponse['id'] = $new_id;
                    $this->response->setStatusCode(201, "Created");
                    $this->response->setJsonContent(array(
                        'status' => 'OK',
                        'data' => array_merge($value, $dataResponse) //merge form data with return db
                    ));
                }

            }else{
                $errors = array();
                foreach( $model->getMessages() as $message )
                    $errors[] = $this->language[$message->getMessage()] ? $this->language[$message->getMessage()] : $message->getMessage();

                $this->response->setJsonContent(array(
                    'status' => 'ERROR',
                    'messages' => $errors
                ));
            }

  
        }//end foreach

        return $this->response;
    }

    /**
     * Method Http accept: DELETE
     */
    public function deleteAction()
    {
        
        // need to evaluate if we need to use this function
        $modelName = $this->modelName;

        $model = $modelName::findFirst($this->id);

        //delete if exists the object
        if ( $model!=false ){
            if ( $model->delete() == true ){
                $this->response->setJsonContent(array('status' => "OK"));
            }else{
               $this->response->setStatusCode(409, "Conflict");

               $errors = array();
               foreach( $model->getMessages() as $message )
                    $errors[] = $this->language[$message->getMessage()] ? $this->language[$message->getMessage()] : $message->getMessage();

               $this->response->setJsonContent(array('status' => "ERROR", 'messages' => $errors));
            }
        }else{
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
     * 
     * @param array $data
     * @return string
     */
    protected function buildHAL(array $data,$limit=-1, $page=-1)
    {
        $hal = array();
        $query = $this->request->getQuery();
        $self = $next = $prev = array();

        $endPage = true;
        if ($limit != -1)
        {
            if(isset($data[$limit]))
            {            
                unset($data[$limit]);
                $endPage = false;
            }
            
            if($page == -1)
                $page = 0;
            if (!isset($query['page']))
            {
                $query['page'] = $page;
            }
        }
        
        
        foreach($query as $param => $value)
        {
            if ($param != '_url')
            {
                $self[] = $param.'='.$value;
                if (isset($limit))
                {
                    if ($param == 'page')
                    {
                        $next[] = $param.'='.($page+1);                        
                        $prev[] = $param.'='.($page-1);
                    }
                    else
                    {
                        $next[] = $param.'='.$value;
                        $prev[] = $param.'='.$value;
                    }

                }
            }
        }
        
        $hal['resultset'] = $data;
        $hal['count'] = count($data);
        
        $hal['_links']['self']['href'] = $query['_url'];
        if (!empty($self))
        {
            $hal['_links']['self']['href'] .= '?'.implode('&',$self);
        }
        if (!empty($next) && !$endPage)
        {            
            $hal['_links']['next']['href'] = $query['_url'].'?'.  implode('&',$next);
        }
        
        if (!empty($prev) && $page > 1)
        {    
            $hal['_links']['prev']['href'] = $query['_url'].'?'.  implode('&',$prev);
        }
            
        return $hal;
    }
    
    /**
     * Return the response, allow the error code handling here
     * 
     * @param array $data
     * @return \Phalcon\Http\Response
     */
    protected function returnResponse(array $data)
    {
        $this->response->setJsonContent($data);     
        $this->response->setContentType('text/json');
        
        if (isset($data['error']))
        {
            $this->response->setStatusCode($data['error']['code']);
        }
        
        return $this->response;
    }

    /**
     * Extract collection data to json
     * @param  Objcet     data object collecion with data
     * @return JSON       data in JSON
     */
    protected function extractData($data){
        //extracting data to array
        if ($data instanceof Resultset)
        {
            $data->setHydrateMode(Resultset::HYDRATE_ARRAYS);        
            $result = array();
            foreach( $data as $value ){
                $result[] = $value;
            }   
        }
        elseif (is_array ($data))
        {
            $result = $data;
        }
        else
        {
            $result = array();
        }
        
        // do not allow passwords to be returned
        $this->removePassword($result);
        
        return $result;
    }

    /**
     * This function is responsble for removing password from the result set
     * 
     * @param array $array
     */
    final private function removePassword(array &$array)
    {
        foreach($array as $key => &$value)
        {
            if(is_array($value))
            {                
                $this->removePassword($value);
            }
            elseif($key == 'password')
            {
                unset($array[$key]);
            }
        }
    }
    

}