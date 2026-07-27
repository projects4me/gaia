#!/usr/bin/env php
<?php

/**
 * Generate backend-truth API catalog from backend docs only:
 * - core/config/routes.php  (resource × allowed methods)
 * - app/metadata/model/*.php (required writable fields → request bodies)
 *
 * No frontend overlay. Syncs sources/server-routes/routes.v1.json.
 */

$root = dirname(__DIR__, 3);
$toolRoot = dirname(__DIR__);
$routesConfig = include $root . '/core/config/routes.php';
$routesSnapshotPath = $toolRoot . '/sources/server-routes/routes.v1.json';
$backendPath = $toolRoot . '/apis/backend.json';
$metadataDir = $root . '/app/metadata/model';

if (empty($routesConfig['routes']['rest']['v1']) || !is_array($routesConfig['routes']['rest']['v1'])) {
    fwrite(STDERR, "No rest.v1 routes found in core/config/routes.php\n");
    exit(1);
}

$routeResources = [];
foreach ($routesConfig['routes']['rest']['v1'] as $resource => $cfg) {
    $methods = isset($cfg['allowedMethods']) && is_array($cfg['allowedMethods'])
        ? array_values(array_unique(array_map('strtoupper', $cfg['allowedMethods'])))
        : [];
    $routeResources[$resource] = [
        'path' => isset($cfg['path']) ? $cfg['path'] : ('/api/:version/' . $resource),
        'allowedMethods' => $methods,
        'identifier' => array_key_exists('identifier', $cfg) ? $cfg['identifier'] : null,
    ];
}

file_put_contents(
    $routesSnapshotPath,
    json_encode([
        'description' => 'Snapshot of Gaia REST v1 route truth for api-tester generators. Regenerated from core/config/routes.php.',
        'source' => 'core/config/routes.php',
        'version' => 'v1',
        'resources' => $routeResources,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
);

/** Resources that are not standard JSON:API CRUD from metadata. */
$skipResources = [
    'forgetpassword' => 'Custom auth endpoint; no model metadata',
    'resetpassword' => 'Custom auth endpoint; requires reset token params',
    'upload' => 'File upload endpoint; not JSON:API collection CRUD',
    'userimage' => 'Multipart image upload endpoint',
    'milestoneoverview' => 'Custom endpoint; no model metadata',
    'issueplanning' => 'LLM/external dependency',
    'mention' => 'Backend SQL error on list (Mention.ified); blocked until fixed',
    'systemnotification' => 'GET-list only custom notifications feed',
    'badge' => 'No route identifier; nonstandard ACL resource',
    'scoreboard' => 'No route identifier; nonstandard ACL resource',
];

/** Resources where mutations are unsafe even if metadata exists (GET still enabled). */
$skipMutations = [
    'converser' => 'POST triggers Socket.IO; requires realtime server',
    'chatroom' => 'POST triggers Socket.IO; requires realtime server',
];

/** Seeded fixture placeholders for get-by-id when available. */
$fixtureIds = [
    'activity' => '{activityId}',
    'conversationroom' => '{conversationroomId}',
    'issue' => '{issueId}',
    'issuestatus' => '{issuestatusId}',
    'issuetype' => '{issuetypeId}',
    'membership' => '{membershipId}',
    'milestone' => '{milestoneId}',
    'project' => '{projectId}',
    'role' => '{roleId}',
    'savedsearch' => '{savedSearchId}',
    'systemnotificationrecipient' => '{systemnotificationrecipientId}',
    'timelog' => '{timelogId}',
    'user' => '{userId}',
];

/** relatedTo/relatedId defaults inferred from metadata relationships / known modules. */
$relatedDefaults = [
    'activity' => ['relatedTo' => 'issue', 'relatedId' => '{issueId}'],
    'comment' => ['relatedTo' => 'conversationrooms', 'relatedId' => '{conversationroomId}'],
    'vote' => ['relatedTo' => 'conversationrooms', 'relatedId' => '{conversationroomId}'],
    'membership' => ['relatedTo' => 'project', 'relatedId' => '{projectId}', 'userId' => '{memberUserId}', 'roleId' => '{developerRoleId}'],
    'tagged' => ['relatedTo' => 'issue', 'relatedId' => '{issueId}'],
];

/** Extra required/useful attributes beyond null=>false inference. */
$extraAttributes = [
    'project' => ['name' => 'API Tester Runtime Project', 'status' => 'new', 'type' => 'software', 'projectManager' => '{userId}', 'done' => 0],
    'issue' => [
        'subject' => 'API Tester Runtime Issue',
        'owner' => '{userId}',
        'assignee' => '{userId}',
        'reportedUser' => '{userId}',
        'typeId' => '{issuetypeId}',
        'statusId' => '{issuestatusId}',
        'priority' => 'medium',
        'projectId' => '{projectId}',
        'projectShortcode' => '{projectShortcode}',
        'isPlanned' => 0,
        'isReopened' => 0,
    ],
    'user' => [
        'name' => 'API Tester Created',
        'email' => 'api-tester-created@example.com',
        'password' => 'unit-testing',
        'accountStatus' => 'active',
    ],
    'role' => [
        'name' => 'API Tester Role',
        'description' => 'Role created by api-tester backend suite',
    ],
    'chatroom' => [
        'subject' => 'API Tester Chatroom',
        'status' => 'open',
        'type' => 'group',
    ],
    'userqualification' => [
        'userId' => '{userId}',
        'type' => 'degree',
        'title' => 'API Tester Degree',
    ],
    'tag' => ['tag' => 'api-tester-tag'],
    'wiki' => ['name' => 'API Tester Wiki', 'projectId' => '{projectId}'],
    'savedsearch' => [
        'name' => 'API Tester Saved Search',
        'relatedTo' => 'issue',
        'searchquery' => '(Issue.projectId : {projectId})',
        'projectId' => '{projectId}',
        'public' => 1,
    ],
    'permission' => [
        'resourceName' => 'wiki.get',
        'allowed' => '1',
        'roleId' => '{roleId}',
    ],
];

/** Custom non-JSON:API request bodies. */
$customBodies = [
    'systemnotificationrecipient' => [
        'markAllAsRead' => true,
        'userId' => '{userId}',
    ],
    'permission' => [
        'data' => [
            'type' => 'permission',
            'attributes' => [
                'resourceName' => 'wiki.get',
                'allowed' => '1',
                'roleId' => '{roleId}',
            ],
        ],
    ],
];

/**
 * Load model metadata array for a REST resource name.
 *
 * @param string $resource
 * @param string $metadataDir
 * @return array|null
 */
function loadMetadata($resource, $metadataDir)
{
    $modelName = ucfirst($resource);
    $path = $metadataDir . '/' . $modelName . '.php';
    if (!is_file($path)) {
        return null;
    }
    $models = [];
    include $path;
    if (isset($models[$modelName]) && is_array($models[$modelName])) {
        return $models[$modelName];
    }
    // Some files only set $models['X'] and return $models
    return null;
}

/**
 * Fields set automatically by behaviors / system.
 *
 * @param array $fieldMeta
 * @param string $name
 * @return bool
 */
function isSystemField($name, array $fieldMeta)
{
    static $system = [
        'id' => true,
        'dateCreated' => true,
        'dateModified' => true,
        'createdUser' => true,
        'createdUserName' => true,
        'modifiedUser' => true,
        'modifiedUserName' => true,
        'deleted' => true,
    ];
    if (isset($system[$name])) {
        return true;
    }
    if (!empty($fieldMeta['identifier'])) {
        return true;
    }
    // Skip audit display names (createdUserName → createdUser), but keep name→id.
    if (!empty($fieldMeta['linkedTo'])) {
        $linked = $fieldMeta['linkedTo'];
        if ($linked === 'createdUser' || $linked === 'modifiedUser' || $linked === 'relatedUser') {
            return true;
        }
        if ($linked !== 'id' && (substr($name, -4) === 'Name' || substr($name, -4) === 'name')) {
            return true;
        }
    }
    return false;
}

/**
 * @param string $resource
 * @param array  $meta
 * @param array  $relatedDefaults
 * @param array  $extraAttributes
 * @return array attributes map
 */
function buildAttributes($resource, array $meta, array $relatedDefaults, array $extraAttributes)
{
    $attrs = [];
    $fields = isset($meta['fields']) && is_array($meta['fields']) ? $meta['fields'] : [];

    foreach ($fields as $name => $fieldMeta) {
        if (!is_array($fieldMeta)) {
            continue;
        }
        if (isSystemField($name, $fieldMeta)) {
            continue;
        }
        $required = (isset($fieldMeta['null']) && $fieldMeta['null'] === false && !array_key_exists('default', $fieldMeta));
        if (!$required) {
            continue;
        }
        $attrs[$name] = sampleValue($resource, $name, $fieldMeta, $relatedDefaults);
    }

    if (isset($relatedDefaults[$resource]) && is_array($relatedDefaults[$resource])) {
        foreach ($relatedDefaults[$resource] as $k => $v) {
            $attrs[$k] = $v;
        }
    }

    if (isset($extraAttributes[$resource]) && is_array($extraAttributes[$resource])) {
        foreach ($extraAttributes[$resource] as $k => $v) {
            $attrs[$k] = $v;
        }
    }

    // Common FK convenience when metadata requires projectId.
    if (isset($fields['projectId']) && !isset($attrs['projectId'])) {
        $nullOk = !isset($fields['projectId']['null']) || $fields['projectId']['null'] === true;
        if (!$nullOk || in_array($resource, ['milestone', 'issuetype', 'issuestatus', 'timelog', 'conversationroom', 'wiki', 'savedsearch'], true)) {
            $attrs['projectId'] = '{projectId}';
        }
    }
    if (isset($fields['projectShortcode']) && isset($attrs['projectId']) && !isset($attrs['projectShortcode'])) {
        $attrs['projectShortcode'] = '{projectShortcode}';
    }

    return $attrs;
}

/**
 * @param string $resource
 * @param string $name
 * @param array  $fieldMeta
 * @param array  $relatedDefaults
 * @return mixed
 */
function sampleValue($resource, $name, array $fieldMeta, array $relatedDefaults)
{
    static $fk = [
        'projectId' => '{projectId}',
        'projectShortcode' => '{projectShortcode}',
        'userId' => '{userId}',
        'issueId' => '{issueId}',
        'milestoneId' => '{milestoneId}',
        'roleId' => '{roleId}',
        'typeId' => '{issuetypeId}',
        'statusId' => '{issuestatusId}',
        'owner' => '{userId}',
        'assignee' => '{userId}',
        'reportedUser' => '{userId}',
        'projectManager' => '{userId}',
        'systemNotificationId' => '{systemnotificationId}',
    ];
    if (isset($fk[$name])) {
        return $fk[$name];
    }
    if ($name === 'relatedId' && isset($relatedDefaults[$resource]['relatedId'])) {
        return $relatedDefaults[$resource]['relatedId'];
    }
    if ($name === 'relatedTo' && isset($relatedDefaults[$resource]['relatedTo'])) {
        return $relatedDefaults[$resource]['relatedTo'];
    }

    $length = isset($fieldMeta['length']) ? (int) $fieldMeta['length'] : 0;
    $type = isset($fieldMeta['type']) ? $fieldMeta['type'] : 'varchar';
    switch ($type) {
        case 'bool':
        case 'boolean':
            return 0;
        case 'int':
        case 'integer':
            return 1;
        case 'float':
        case 'double':
            return 1.0;
        case 'date':
            return 'CURRENT_DATE';
        case 'datetime':
            return 'CURRENT_DATETIME';
        case 'text':
            return 'API Tester ' . $resource . ' ' . $name;
        default:
            if (stripos($name, 'email') !== false) {
                return fitLength('api-tester-' . $resource . '@example.com', $length);
            }
            if (stripos($name, 'password') !== false) {
                return 'unit-testing';
            }
            if ($name === 'status') {
                return fitLength('new', $length);
            }
            if ($name === 'priority') {
                return 'medium';
            }
            if ($name === 'type' && $resource === 'activity') {
                return 'create';
            }
            if ($name === 'type' && $resource === 'project') {
                return 'software';
            }
            if ($name === 'type' && $resource === 'chatroom') {
                return 'group';
            }
            if ($name === 'type' && $resource === 'userqualification') {
                return 'degree';
            }
            if ($name === 'roomType') {
                return 'discussion';
            }
            if ($name === 'milestoneType') {
                return 'sprint';
            }
            if ($name === 'accountStatus') {
                return 'active';
            }
            if ($name === 'context' && $resource === 'timelog') {
                return 'spent';
            }
            return fitLength('api-' . $resource . '-' . $name, $length > 0 ? $length : 40);
    }
}

/**
 * @param string $value
 * @param int    $length
 * @return string
 */
function fitLength($value, $length)
{
    if ($length <= 0) {
        return $value;
    }
    if (strlen($value) <= $length) {
        return $value;
    }
    return substr($value, 0, $length);
}

/**
 * Pick a field to mutate in PATCH (avoid short enum/FK columns).
 *
 * @param array $attrs
 * @param array $meta
 * @return array|null [field, value]
 */
function patchChange(array $attrs, array $meta = [])
{
    static $avoid = [
        'relatedTo' => true,
        'relatedId' => true,
        'type' => true,
        'status' => true,
        'context' => true,
        'roomType' => true,
        'priority' => true,
        'accountStatus' => true,
        'milestoneType' => true,
        'searchquery' => true,
        'password' => true,
        'email' => true,
        'projectId' => true,
        'projectShortcode' => true,
        'userId' => true,
        'roleId' => true,
        'typeId' => true,
        'statusId' => true,
        'owner' => true,
        'assignee' => true,
        'reportedUser' => true,
        'projectManager' => true,
        'isPlanned' => true,
        'isReopened' => true,
        'done' => true,
        'public' => true,
    ];
    $fields = isset($meta['fields']) && is_array($meta['fields']) ? $meta['fields'] : [];
    $prefer = ['name', 'subject', 'description', 'title', 'tag', 'comment', 'institution'];
    foreach ($prefer as $field) {
        if (!isset($fields[$field]) || isset($avoid[$field])) {
            continue;
        }
        $base = (isset($attrs[$field]) && is_string($attrs[$field]) && strpos($attrs[$field], '{') === false)
            ? $attrs[$field]
            : 'API Tester';
        $newVal = $base . ' Updated';
        $len = isset($fields[$field]['length']) ? (int) $fields[$field]['length'] : 0;
        $fieldType = isset($fields[$field]['type']) ? $fields[$field]['type'] : 'varchar';
        if ($fieldType === 'text') {
            $len = 0;
        }
        if ($len > 0) {
            $newVal = fitLength($newVal, $len);
            if ($newVal === $base) {
                $newVal = fitLength('U-' . $base, $len);
            }
            if ($newVal === $base) {
                continue;
            }
        }
        return [$field, $newVal];
    }
    foreach ($attrs as $field => $value) {
        if (isset($avoid[$field]) || !is_string($value) || strpos($value, '{') !== false) {
            continue;
        }
        $len = isset($fields[$field]['length']) ? (int) $fields[$field]['length'] : 0;
        if ($len > 0 && $len < 12) {
            continue;
        }
        $newVal = $value . ' Updated';
        if ($len > 0) {
            $newVal = fitLength($newVal, $len);
            if ($newVal === $value) {
                continue;
            }
        }
        return [$field, $newVal];
    }
    return null;
}

$apis = [];
$stats = [
    'resources' => 0,
    'withMetadata' => 0,
    'skippedResources' => 0,
    'enabled' => 0,
    'disabled' => 0,
];

foreach ($routeResources as $resource => $routeCfg) {
    $stats['resources']++;
    $methods = $routeCfg['allowedMethods'];
    $hasId = !empty($routeCfg['identifier']);
    $base = '/api/v1/' . $resource;

    if (isset($skipResources[$resource])) {
        $stats['skippedResources']++;
        // Still emit disabled list GET for visibility when GET allowed.
        if (in_array('GET', $methods, true)) {
            $apis[] = [
                'id' => "get-{$resource}-api-v1-{$resource}",
                'method' => 'GET',
                'path' => $base,
                'resource' => $resource,
                'auth' => true,
                'expects' => ['status' => 200, 'shape' => 'json'],
                'enabled' => false,
                'skipReason' => $skipResources[$resource],
                'source' => 'backend-metadata',
            ];
            $stats['disabled']++;
        }
        continue;
    }

    // Token is special.
    if ($resource === 'token') {
        if (in_array('POST', $methods, true)) {
            $apis[] = [
                'id' => 'post-token-api-v1-token',
                'method' => 'POST',
                'path' => '/api/v1/token',
                'resource' => 'token',
                'auth' => false,
                'expects' => ['status' => 200, 'shape' => 'oauth.token'],
                'source' => 'backend-metadata',
            ];
            $stats['enabled']++;
        }
        continue;
    }

    // System settings exposes ACL moduleActions catalog (not model CRUD).
    if ($resource === 'systemsetting') {
        if (in_array('GET', $methods, true)) {
            $apis[] = [
                'id' => 'get-systemsetting-api-v1-systemsetting',
                'method' => 'GET',
                'path' => '/api/v1/systemsetting',
                'resource' => 'systemsetting',
                'auth' => true,
                'expects' => ['status' => 200, 'shape' => 'json'],
                'source' => 'backend-metadata',
            ];
            $stats['enabled']++;
        }
        continue;
    }

    $meta = loadMetadata($resource, $metadataDir);
    if ($meta === null) {
        $stats['skippedResources']++;
        if (in_array('GET', $methods, true)) {
            $apis[] = [
                'id' => "get-{$resource}-api-v1-{$resource}",
                'method' => 'GET',
                'path' => $base,
                'resource' => $resource,
                'auth' => true,
                'expects' => ['status' => 200, 'shape' => 'json'],
                'query' => ['limit' => 5, 'page' => 1],
                'enabled' => false,
                'skipReason' => 'No app/metadata/model for resource',
                'source' => 'backend-metadata',
            ];
            $stats['disabled']++;
        }
        continue;
    }
    $stats['withMetadata']++;

    $attrs = buildAttributes($resource, $meta, $relatedDefaults, $extraAttributes);
    $runtimeKey = 'runtime.' . $resource . 'Id';

    // GET list
    if (in_array('GET', $methods, true)) {
        $apis[] = [
            'id' => "get-{$resource}-api-v1-{$resource}",
            'method' => 'GET',
            'path' => $base,
            'resource' => $resource,
            'auth' => true,
            'expects' => ['status' => 200, 'shape' => 'jsonapi.collection'],
            'query' => ['limit' => 5, 'page' => 1],
            'source' => 'backend-metadata',
        ];
        $stats['enabled']++;

        if ($hasId && isset($fixtureIds[$resource])) {
            $apis[] = [
                'id' => "get-{$resource}-api-v1-{$resource}-id",
                'method' => 'GET',
                'path' => $base . '/' . $fixtureIds[$resource],
                'resource' => $resource,
                'auth' => true,
                'expects' => [
                    'status' => 200,
                    'shape' => 'jsonapi.resource',
                    'idsIncludes' => [$fixtureIds[$resource]],
                ],
                'source' => 'backend-metadata',
            ];
            $stats['enabled']++;
        }
    }

    $canMutate = (!empty($attrs) || isset($customBodies[$resource])) && !isset($skipMutations[$resource]);
    $jsonApiBody = isset($customBodies[$resource])
        ? $customBodies[$resource]
        : [
            'data' => [
                'type' => $resource,
                'attributes' => empty($attrs) ? new stdClass() : $attrs,
            ],
        ];

    // POST create + runtime lifecycle
    if (in_array('POST', $methods, true)) {
        if (isset($skipMutations[$resource])) {
            $apis[] = [
                'id' => "post-{$resource}-api-v1-{$resource}",
                'method' => 'POST',
                'path' => $base,
                'resource' => $resource,
                'auth' => true,
                'body' => $jsonApiBody,
                'expects' => ['status' => 201, 'shape' => 'jsonapi.resource'],
                'enabled' => false,
                'skipReason' => $skipMutations[$resource],
                'source' => 'backend-metadata',
            ];
            $stats['disabled']++;
        } elseif (!$canMutate || (isset($attrs) && empty($attrs) && !isset($customBodies[$resource]))) {
            $apis[] = [
                'id' => "post-{$resource}-api-v1-{$resource}",
                'method' => 'POST',
                'path' => $base,
                'resource' => $resource,
                'auth' => true,
                'body' => $jsonApiBody,
                'expects' => ['status' => 201, 'shape' => 'jsonapi.resource'],
                'enabled' => false,
                'skipReason' => 'Metadata did not yield writable required attributes',
                'source' => 'backend-metadata',
            ];
            $stats['disabled']++;
        } else {
            $post = [
                'id' => "post-{$resource}-api-v1-{$resource}",
                'method' => 'POST',
                'path' => $base,
                'resource' => $resource,
                'auth' => true,
                'body' => $jsonApiBody,
                'source' => 'backend-metadata',
            ];
            if ($resource === 'systemnotificationrecipient') {
                $post['expects'] = ['status' => 200, 'shape' => 'json'];
            } elseif (isset($customBodies[$resource])) {
                // Custom JSON:API body (e.g. permission) still returns a created resource.
                $post['expects'] = ['status' => 201, 'shape' => 'jsonapi.resource'];
            } else {
                $post['store'] = [$runtimeKey => 'data.id'];
                $post['expects'] = ['status' => 201, 'shape' => 'jsonapi.resource'];
            }
            $apis[] = $post;
            $stats['enabled']++;

            // Runtime GET / PATCH / DELETE only for JSON:API creates with store.
            // PUT is omitted: RestController::putAction is not implemented.
            if (!isset($customBodies[$resource]) && $hasId) {
                if (in_array('GET', $methods, true)) {
                    $apis[] = [
                        'id' => "get-{$resource}-api-v1-{$resource}-runtime-id",
                        'method' => 'GET',
                        'path' => $base . '/{' . $runtimeKey . '}',
                        'resource' => $resource,
                        'auth' => true,
                        'expects' => [
                            'status' => 200,
                            'shape' => 'jsonapi.resource',
                            'idsIncludes' => ['{' . $runtimeKey . '}'],
                        ],
                        'source' => 'backend-metadata',
                    ];
                    $stats['enabled']++;
                }

                $change = patchChange($attrs, $meta);
                if (in_array('PATCH', $methods, true) && $change) {
                    list($field, $newVal) = $change;
                    $apis[] = [
                        'id' => "patch-{$resource}-api-v1-{$resource}-id",
                        'method' => 'PATCH',
                        'path' => $base . '/{' . $runtimeKey . '}',
                        'resource' => $resource,
                        'auth' => true,
                        'body' => [
                            'data' => [
                                'type' => $resource,
                                'id' => '{' . $runtimeKey . '}',
                                'attributes' => [$field => $newVal],
                            ],
                        ],
                        'expects' => [
                            'status' => 200,
                            'shape' => 'jsonapi.resource',
                            'attributesEqual' => [$field => $newVal],
                        ],
                        'source' => 'backend-metadata',
                    ];
                    $stats['enabled']++;
                }

                if (in_array('DELETE', $methods, true)) {
                    $del = [
                        'id' => "delete-{$resource}-api-v1-{$resource}-runtime-id",
                        'method' => 'DELETE',
                        'path' => $base . '/{' . $runtimeKey . '}',
                        'resource' => $resource,
                        'auth' => true,
                        'expects' => ['status' => [200, 204], 'shape' => 'json'],
                        'source' => 'backend-metadata',
                    ];
                    if ($resource === 'membership') {
                        $del['query'] = ['newAssigneeId' => '{userId}'];
                    }
                    $apis[] = $del;
                    $stats['enabled']++;
                }
            }
        }
    } else {
        // No POST: still emit disabled stubs for PATCH/DELETE when route allows them.
        foreach (['PATCH', 'DELETE'] as $m) {
            if (!in_array($m, $methods, true) || !$hasId) {
                continue;
            }
            $verb = strtolower($m);
            $apis[] = [
                'id' => "{$verb}-{$resource}-api-v1-{$resource}-id",
                'method' => $m,
                'path' => $base . '/{' . $resource . 'Id}',
                'resource' => $resource,
                'auth' => true,
                'expects' => ['status' => 200, 'shape' => 'json'],
                'enabled' => false,
                'skipReason' => 'No POST lifecycle to create disposable runtime id',
                'source' => 'backend-metadata',
            ];
            $stats['disabled']++;
        }
    }
}

// Backend smoke checks known from RestController (not frontend).
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
    'source' => 'backend-metadata',
];
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
    'source' => 'backend-metadata',
];
$stats['enabled'] += 2;

$out = [
    'name' => 'gaia-backend-apis',
    'version' => 3,
    'basePath' => '/api/v1',
    'generatedFrom' => [
        'routes' => 'tools/api-tester/sources/server-routes/routes.v1.json',
        'routesSource' => 'core/config/routes.php',
        'metadata' => 'app/metadata/model',
    ],
    'summary' => [
        'total' => count($apis),
        'enabled' => $stats['enabled'],
        'disabled' => $stats['disabled'],
        'routeResources' => $stats['resources'],
        'withMetadata' => $stats['withMetadata'],
        'skippedResources' => $stats['skippedResources'],
    ],
    'apis' => $apis,
];

file_put_contents($backendPath, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

echo "Synced {$routesSnapshotPath}\n";
echo "Wrote " . count($apis) . " APIs to {$backendPath}\n";
echo "  enabled: {$stats['enabled']}, disabled: {$stats['disabled']}\n";
echo "  metadata resources: {$stats['withMetadata']}, skipped resources: {$stats['skippedResources']}\n";
