<?php

use Phalcon\Cli\Task;
use Elasticsearch\ClientBuilder;
use Phalcon\Mvc\Model\Resultset;
use Gaia\Libraries\Utils\Util;

class AdminTask extends Task
{
    /**
     * This is the client object for elastic search
     *
     * @var Elasticsearch\ClientBuilder $client
     * @protected
     */
    protected $client = null;

    /**
     * This is the cached metadata
     *
     * @var array $cachedMeta
     */
    protected static $cachedMeta = array();

    public function mainAction()
    {
        echo 'This is the default task and the default action' . PHP_EOL;
    }

    /**
     * This function rebuilds the ElasticSearch
     *
     * @param array $params
     */
    public function rebuildAction(array $params)
    {
        $settings = $GLOBALS['settings']['fts'];

        $logger = ClientBuilder::defaultLogger(APP_PATH . '/logs/admin.log', 100);
        $this->client = ClientBuilder::create()
            ->setHosts([$settings['host']])
            ->setLogger($logger)
            ->build();

        try {

            $this->_buildIndex();
            $this->_populateData();

        } catch (Exception $e) {
            print $e->getMessage();
            print "Error";
        }

    }

    /**
     * Thi function builds the index. It can also delete the existing index
     * if required
     *
     * @param bool $reset
     * @return bool
     */
    protected function _buildIndex($reset = true) {
        $settings = $GLOBALS['settings']['fts'];

        if (!($this->client instanceof Elasticsearch\Client)){
            return false;
        }

        $indexParams = [
            'index' => $settings['index'],
            'client' => [ 'ignore' => 404 ]
        ];

        try {

            if ($reset) {
                $res = $this->client->indices()->exists($indexParams);
                if ($res) {

                    $response = $this->client->indices()->delete($indexParams);
                    if ($response['acknowledged'] != 1) {
                        print "Unable to delete the index";
                        return false;
                    }
                }
            }
        } catch (Exception $e) {
            print "test";
        }

        $mappings = require APP_PATH . "/app/metadata/FTS.php";;
        $this->_removeIndex($mappings,'model');
        $params = [
            'index' => $settings['index'],
            'body' => array(
                "settings" => array(
                    "analysis" => array(
                        "filter" => array(
                            "autocomplete_filter" => array(
                                "type" => "edge_ngram",
                                "min_gram" =>  1,
                                "max_gram" => 20
                            )
                        ),
                        "analyzer" => array(
                            "autocomplete" => array(
                                "type" => "custom",
                                "tokenizer" => "standard",
                                "filter" => array(
                                    "lowercase",
                                    "autocomplete_filter"
                                )
                            )
                        )
                    ),
                ),
                'mappings' => $mappings
            )
        ];

        $response = $this->client->indices()->create($params);
        if ($response['acknowledged'] != 1) {
            print "Unable to create the index";
            return false;
        }

        return true;
    }

    protected function _buildMapping() {
//        $mapping = array();
//        // Get the meta for all the models
//        $metaPath = APP_PATH . '/app/metadata/model/';
//        foreach (new DirectoryIterator($metaPath) as $fileInfo) {
//            if($fileInfo->isDot()) continue;
//            $modelName = $fileInfo->getBasename('.php');
//            $metaPath = APP_PATH.'/app/metadata/model/'.$modelName.'.php';
//
//            $metadata = $this->di->get('fileHandler')->readFile($metaPath);
//            $metadata = $metadata[$modelName];
//            if (isset($metadata['fts']) && $metadata['fts']) {
//                $mapping[$metadata['tableName']] = array(
//                    '_source' => [
//                        'enabled' => true
//                    ],
//                    'properties' => array()
//                );
//                foreach ($metadata['fields'] as $field => $definition) {
//                    if (isset($definition['fts']) && $definition['fts']) {
//                        $def = $this->_getFieldMapping($definition);
//                        $mapping[$metadata['tableName']]['properties'][$field] = $def;
//                    }
//                }
//            }
//        }
//
//        return $mapping;


//        return $mappings;

        // suggestors
        // file attachment Attachment Data Type with Apache tika sudo bin/elasticsearch-plugin install ingest-attachment

    }

    protected function _removeIndex(&$array, $index)
    {
        if(is_array($array))
        {
            foreach($array as $key=>&$arrayElement)
            {
                if(is_array($arrayElement))
                {
                    $this->_removeIndex($arrayElement, $index);
                }
                else
                {
                    if($key === $index)
                    {
                        unset($array[$key]);
                    }
                }
            }
        }
    }

    protected function _populateData() {
        global $currentUser;
        $settings = $GLOBALS['settings']['fts'];

        $currentUser = \Gaia\MVC\Models\User::findFirst('id ="1"');
        $mappings = require APP_PATH . "/app/metadata/FTS.php";;
        $model = new \Gaia\MVC\Models\Project();
        $this->modelName = 'Project';

        $data = $model->read(['id' => 'add12ff55acb-1902-6b9c-30d5411f7729']);
        $Project = $this->extractData($data, $mappings);
        //print_r($Project);

        $params = array(
            'index' => $settings['index'],
            'type' => 'project',
            'body' => $Project['project'][0]
        );

        print_r($params);

        $responses = $this->client->index($params);
        print_r($responses);

//        foreach($Project as $obj) {
//
//        }

        //        $metaPath = APP_PATH . '/app/metadata/model/';
//        foreach (new DirectoryIterator($metaPath) as $fileInfo) {
//            if($fileInfo->isDot()) continue;
//            $modelName = $fileInfo->getBasename('.php');
//            $model = new $modelName;
//
//            $data = $model->read(['id' => '1']);
//            print_r($data);
//
//        }

    }

    protected function extractData($data, $mappings){

        $modelName = strtolower($this->modelName);

        if (!empty($relation))
        {
            $modelName = strtolower($relation);
        }
        $resultSet = array();
        //print_r($data->toArray());
        //extracting data to array

        if ($data instanceof Resultset) {
            $data->setHydrateMode(Resultset::HYDRATE_ARRAYS);

            $result = array();
            foreach($data as $values){

                foreach ($values as $attr => $value){
                    if (!isset($result[$values['id']])){
                        $result[$values['id']] = array();
                    }

                    if (is_array($value))
                    {
                        $relDef = $this->getRelationshipMeta(Util::extractClassFromNamespace($this->modelName),$attr);
                        if ($relDef['type'] == 'hasMany' || $relDef['type'] == 'hasManyToMany')
                        {
                            if (!empty($value['id']))
                            {
                                $result[$values['id']][$attr][] = $value;
                            }
                        }
                        else
                        {
                            $result[$values['id']][$attr] = $value;
                        }
                    }
                    else
                    {
                        $result[$values['id']][$attr] = $value;
                    }
                }
            }
            //die();
        }
        elseif (is_array ($data))
        {
            $result = $data;
        }
        else
        {
            $result = array();
        }

        $this->removeDuplicates($result);

        $count = 0;

        // prepare the data for JSONAPI.org standard
        foreach ($result as $object)
        {
            $modelNameRaw = Util::extractClassFromNamespace($this->modelName);
            $modelName = strtolower($this->modelName);

            $modelMainKeys = array_keys($mappings[$modelName]['properties']);
            $modelMainKeysRaw = $modelMainKeys;
            foreach($modelMainKeys as $k => $v) {
                if ($mappings[$modelName]['properties'][$v]['type'] === 'nested') {
                    $modelMainKeys[$k] = $mappings[$modelName]['properties'][$v]['model'];
                }
            }
            $resultSet[$modelName][$count] = array();

            foreach($object as $attr => $val)
            {
                if (in_array($attr,$modelMainKeysRaw) || $attr === 'id') {
                    if (!is_array($val)) {
                        // process attributes
                        if ($attr === 'id') {
                            //$resultSet[$modelName][$count]['_id'] = $val;
                        } else {
                            $resultSet[$modelName][$count][$attr] = $val;
                        }
                    } else {
                        // process relationships
                        if (isset($val['id'])) {
                            $relationDefinition = $this->getRelationshipMeta($modelNameRaw, $attr);
                            $relatedModelKey = 'relatedModel';
                            if ($relationDefinition['type'] == 'hasManyToMany') {
                                $relatedModelKey = 'secondaryModel';
                            }
                            $relatedModelName = strtolower(Util::extractClassFromNamespace($relationDefinition[$relatedModelKey]));
                            $resultSet[$modelName][$count][$relatedModelName] = array();
                            $id = $val['id'];
                            unset($val['id']);
                            $relatedModelKeys = array_keys($mappings[$modelName]['properties'][$relatedModelName]['properties']);
                            $resultSet[$modelName][$count][$attr] = array_intersect($val, $relatedModelKeys);
                            $resultSet[$modelName][$count][$attr]['_id'] = $id;
                        } else {
                            foreach ($val as $idx => $object) {
                                if (isset($object['id'])) {
                                    $relationDefinition = $this->getRelationshipMeta($modelNameRaw, $attr);
                                    $relatedCount = 0;
                                    $relatedModelKey = 'relatedModel';
                                    if ($relationDefinition['type'] == 'hasManyToMany' && isset($relationDefinition['secondaryModel'])) {
                                        $relatedModelKey = 'secondaryModel';
                                    }
                                    $relatedModelName = strtolower(Util::extractClassFromNamespace($relationDefinition[$relatedModelKey]));
                                    $i = array_search($relatedModelName, $modelMainKeys);
                                    $rel = $modelMainKeysRaw[$i];
                                    $relatedModelKeys = array_keys($mappings[$modelName]['properties'][$rel]['properties']);

                                    $resultSet[$modelName][$count][$rel][$idx] = array();
                                    $id = $object['id'];
                                    unset($object['id']);

                                    foreach ($object as $field => $value) {
                                        if (in_array($field,$relatedModelKeys)) {
                                            $resultSet[$modelName][$count][$rel][$idx][$field] = $value;
                                        }
                                    }

                                    //$resultSet[$modelName][$count][$rel][$idx]['_id'] = $id;
                                }
                            }
                        }
                    }
                }
            }
            $count++;
        }

        $result = $resultSet;

        // do not allow passwords to be returned
        $this->removePassword($result);

        return $result;
    }

    private function removeDuplicates(array &$array)
    {
        foreach($array as $id => &$obj)
        {
            foreach($obj as $attr => &$data)
            {
                if (is_array($data) && isset($data['0']))
                {
                    $temp = array();
                    foreach($data as $relatedIndex => &$relatedData)
                    {
                        if (isset($relatedData['id']))
                        {
                            $temp[$relatedData['id']] = $relatedData;
                            unset($data[$relatedIndex]);
                        }
                    }
                    $data = array_values(array_merge($data,$temp));
                }
            }
        }
    }

    /**
     * This function is responsible for removing password from the result set
     *
     * @param array $array
     */
    private function removePassword(array &$array)
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

    protected function _getFieldMapping($definition) {
        $typeMapping = array(
            'char' => 'text',
            'varchar' => 'text',
            'text' => 'text',
            'date' => 'date',
            'datetime' => 'date',
            'bool' => 'boolean',
            'int' => 'integer',
            'float' => 'float'
        );

        $def = array(
            'type' => $typeMapping[$definition['type']]
        );

        return $def;
    }

    /**
     * This function is used to retrieve the relationship metadata for a model
     *
     * @param string $modelName
     * @param string $rel
     * @return array
     */
    final private function getRelationshipMeta($modelName,$rel){
        if (isset(self::$cachedMeta[$modelName][$rel])) {
            return self::$cachedMeta[$modelName][$rel];
        }
        $modelMetadata = $this->di->get('metaManager')->getModelMeta($modelName);

        $relatedMetadata = array();
        foreach ($modelMetadata['relationships'] as $relationType => $related)
        {
            foreach ($related as $relName => $relDef)
            {
                if ($relName == $rel)
                {
                    $relatedMetadata = $relDef;
                    $relatedMetadata['type'] = $relationType;
                    break 2;
                }
            }
        }
        self::$cachedMeta[$modelName][$rel] = $relatedMetadata;
        return $relatedMetadata;
    }

}