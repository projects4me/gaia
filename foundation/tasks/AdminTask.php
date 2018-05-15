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
            $this->_buildMapping();
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

        $response = $this->client->indices()->create($indexParams);
        if ($response['acknowledged'] != 1) {
            print "Unable to create the index";
            return false;
        }

        return true;
    }

    protected function _buildMapping() {
        // Get the meta for all the models
        // Text => text
        // Keyword for tags, emails, etc
        // Integer => numeric
        // date => dates
        // startDate and endDate as range range
        // relationships as nested
        // suggestors
        // file attachment Attachment Data Type with Apache tika sudo bin/elasticsearch-plugin install ingest-attachment

    }

    protected function _populateData() {

    }

}