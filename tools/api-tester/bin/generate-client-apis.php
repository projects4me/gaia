<?php

$candidates = json_decode(file_get_contents(dirname(__DIR__) . '/sources/client-contract/test-candidates.json'), true);

$apis = [];
foreach ($candidates as $c) {
    $path = $c['path'];
    $method = $c['method'];
    $resource = $c['resource'];
    $focus = $c['testFocus'] ?? '';
    $auth = ($resource !== 'token');

    $expects = ['status' => 200, 'shape' => 'json'];
    if ($resource === 'token') {
        $expects = ['status' => 200, 'shape' => 'oauth.token'];
    } elseif ($method === 'POST') {
        $expects = ['status' => 201, 'shape' => 'jsonapi.resource'];
    } elseif ($method === 'DELETE') {
        $expects = ['status' => 200, 'shape' => 'json'];
    } elseif ($method === 'GET' && strpos($path, ':id') === false) {
        $expects = ['status' => 200, 'shape' => 'jsonapi.collection'];
    } elseif ($method === 'GET' && strpos($path, ':id') !== false) {
        $expects = ['status' => 200, 'shape' => 'jsonapi.resource'];
    } elseif ($method === 'PATCH' || $method === 'PUT') {
        $expects = ['status' => 200, 'shape' => 'jsonapi.resource'];
    }

    $entry = [
        'id' => $c['id'],
        'method' => $method,
        'path' => $path,
        'resource' => $resource,
        'auth' => $auth,
        'expects' => $expects,
    ];

    if ($method === 'GET' && strpos($path, ':id') === false) {
        $templates = array_values(array_unique($c['queryTemplates'] ?? []));
        if (!empty($templates)) {
            $entry['query'] = [
                'query' => $templates[0],
                'limit' => 5,
                'page' => 1,
            ];
        } else {
            $entry['query'] = ['limit' => 5, 'page' => 1];
        }
    }

    if (strpos($path, ':id') !== false) {
        $entry['path'] = str_replace(':id', '{' . $resource . 'Id}', $path);
    }

    if (in_array($method, ['POST', 'PATCH', 'PUT'], true) && $resource !== 'token') {
        $entry['body'] = [
            'data' => [
                'type' => $resource,
                'attributes' => new stdClass(),
            ],
        ];
        $entry['enabled'] = false;
        $entry['skipReason'] = 'Mutation body/fixtures not configured yet';
    }

    $apis[] = $entry;
}

// Explicit adapter-parity endpoint for /user/me
$apis[] = [
    'id' => 'get-user-api-v1-user-me',
    'method' => 'GET',
    'path' => '/api/v1/user/me',
    'resource' => 'user',
    'auth' => true,
    'expects' => [
        'status' => 200,
        'shape' => 'jsonapi.resource',
        'attributesPresent' => ['email'],
    ],
];

// Unauthorized smoke for one protected endpoint
$apis[] = [
    'id' => 'get-user-api-v1-user-unauthorized',
    'method' => 'GET',
    'path' => '/api/v1/user',
    'resource' => 'user',
    'auth' => false,
    'query' => ['limit' => 1, 'page' => 1],
    'expects' => [
        'status' => [401, 403],
        'shape' => 'error',
    ],
];

$out = [
    'name' => 'gaia-client-apis',
    'version' => 1,
    'basePath' => '/api/v1',
    'apis' => $apis,
];

$path = dirname(__DIR__) . '/apis/client.json';
file_put_contents($path, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
echo 'Wrote ' . count($apis) . ' APIs to ' . $path . PHP_EOL;
