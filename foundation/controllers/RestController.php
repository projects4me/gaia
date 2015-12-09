<?php

namespace Foundation\Mvc;

use Phalcon\Http\Response;
use Phalcon\Mvc\Model\Resultset;
use Phalcon\DI;
use Phalcon\Mvc\Dispatcher;
use Phalcon\Text;
use function Foundation\create_guid as create_guid;

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
    protected $projectAuthorization = true;

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
        'post' => 'create',
        'search' => 'search',
        'delete' => 'delete',
        'put' => 'update',
        'patch' => 'update'
    );

    /**
     * System level flag
     * @var bool
     */
    protected $systemLevel = false;    
    
    protected $uses = array('acl','auditable');
    
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
     * @todo improve version mapping
     */
    public function optionsAction(){
        global $settings;
        
        $modelName = $this->modelName;
        $requestURI = $this->request->getURI();
        if (preg_match('@api/?([^/]+)@',$requestURI,$matches))
        {
            $version = $matches[1];
            $allowedMethods = (array) $settings->routes['rest']->$version->$modelName->allowedMethods;
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

        $data = $modelName::find( $this->id );
        return $this->extractData($data);
    }


    /**
    * Method Http accept: GET
     * @return JSON Retrive all data, with and without relationship
    */
    public function listAction()
    {
        //check for accessible projects
        $modelName = $this->modelName;
        $identifier = 'projectId';
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

        return $this->extractData($data);
    }

    /**
     * Extract collection data to json
     * @param  Objcet     data object collecion with data
     * @return JSON       data in JSON
     */
    protected function extractData($data){
        //extracting data to array
        $data->setHydrateMode(Resultset::HYDRATE_ARRAYS);        
        $result = array();
        foreach( $data as $value ){
            $result[] = $value;
        }   

        //se for um único elemento remove os índices de cada item
        if ($this->id && !$this->relationship) $result = $result[0];

        $this->response->setJsonContent($result);     

        return $this->response;
    }

    /**
    * Method Http accept: GET
    * Search data with field/value passed in parameter
    * 
    * Ex : url: /user/search/name/edvaldo/email/edvaldo2107@gmail.com/order/name
    *      sql generated: select * from user where name='edvaldo' and email='edvaldo2107@gmail.com' order by name
    * Ex2 : url: /user/search/id_department/(IN)1,2/order/name
    *      sql generated: select * from user where id_department in (1,2) order by name
    */
    public function searchAction()
    {
        //get model name
        $modelName = $this->modelName;

        //extracting parameterrs
        $params = array();
        for ( $i=3; $i<count($this->params); $i++ ){
            $params[$this->params[$i]] = $this->params[++$i];
        }

        //building the query        
        $query = '$model = \\' . $modelName . "::query()";        
        $query .= "->where(\"1=1\")";//where default
        foreach ($params as $key => $value) {
            //if order by
            if ( $key=="order" ){
                $query .= "->orderBy(\"".$value."\")";
                break;
            //if condition is IN
            }else if ( strpos($value, "(IN)")!==false ){
                $value = substr($value, strpos($value, "(IN)")+4); 
                $query .= "->andWhere(\"$key IN ($value)\")";
            }else{
                $query .= "->andWhere(\"$key = '$value'\")";
            }
        }
        $query .= "->execute();";
        eval($query);

        //extracting data from resultset after query executed
        $model->setHydrateMode(Resultset::HYDRATE_ARRAYS);
        $result = array();
        foreach ($model as $key => $value) {
           $result[] = $value;
        }
    
        $this->response->setJsonContent($result);     

        return $this->response;
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

}
