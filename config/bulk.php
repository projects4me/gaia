<?php

/**
 * Limits of the Bulk API (POST /api/v1/bulk), see docs/architecture/bulk/decisions.md.
 *
 * - maxRequests: the most calls one batch may hold.
 * - maxBodyBytes: the largest batch body, in bytes.
 */
$bulkLimit = static function (string $name, int $default): int {
    $value = filter_var(getenv($name), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    return $value === false ? $default : $value;
};

$config['bulk'] = array(
    'maxRequests' => $bulkLimit('BULK_MAX_REQUESTS', 25),
    'maxBodyBytes' => $bulkLimit('BULK_MAX_BODY_BYTES', 1048576),
);

return $config;
