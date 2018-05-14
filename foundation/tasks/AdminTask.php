<?php

use Phalcon\Cli\Task;
use Elasticsearch\ClientBuilder;

class AdminTask extends Task
{
    public function mainAction()
    {
        echo 'This is the default task and the default action' . PHP_EOL;
    }

    /**
     * This function builds the ElasticSearch
     * @param array $params
     */
    public function testAction(array $params)
    {
        $settings = $GLOBALS['settings']['fts'];
        print_r($settings);
        $logger = ClientBuilder::defaultLogger(APP_PATH . '/logs/admin.log');

        $client = ClientBuilder::create()
            ->setHosts([$settings['host']])
            ->setLogger($logger)
            ->build();

        $params = [
            'index' => $settings['index'],
            'type' => 'project',
            'id' => 1001
        ];

        $response = $client->get($params);
        print_r($response);



    }
}