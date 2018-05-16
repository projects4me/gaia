<?php

use Phalcon\Cli\Task;
use Elasticsearch\ClientBuilder;

class AdminTask extends Task
{
    /**
     * This is the client object for elastic search
     *
     * @var Elasticsearch\ClientBuilder $client
     * @protected
     */
    protected $client = null;

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

        $indexParams = ['index' => $settings['index']];

        if ($reset && $this->client->indices()->exists($indexParams)) {
            $response = $this->client->indices()->delete($indexParams);
            if ($response['acknowledged'] != 1) {
                print "Unable to delete the index";
                return false;
            }
        }

        $params = [
            'index' => $settings['index'],
            'type' => 'default',
            'body' => array(
                'mappings' => $this->_buildMapping()
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
        $mapping = array();
        // Get the meta for all the models
        $metaPath = APP_PATH . '/app/metadata/model/';
        foreach (new DirectoryIterator($metaPath) as $fileInfo) {
            if($fileInfo->isDot()) continue;
            $modelName = $fileInfo->getBasename('.php');
            $metaPath = APP_PATH.'/app/metadata/model/'.$modelName.'.php';

            $metadata = $this->di->get('fileHandler')->readFile($metaPath);
            $metadata = $metadata[$modelName];
            if (isset($metadata['fts']) && $metadata['fts']) {
                $mapping[$metadata['tableName']] = array(
                    '_source' => [
                        'enabled' => true
                    ],
                    'properties' => array()
                );
                foreach ($metadata['fields'] as $field => $definition) {
                    if (isset($definition['fts']) && $definition['fts']) {
                        $def = $this->_getFieldMapping($definition);
                        $mapping[$metadata['tableName']]['properties'][$field] = $def;
                    }
                }
            }
        }
        return $mapping;

        // suggestors
        // file attachment Attachment Data Type with Apache tika sudo bin/elasticsearch-plugin install ingest-attachment

    }

    protected function _populateData() {

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

}