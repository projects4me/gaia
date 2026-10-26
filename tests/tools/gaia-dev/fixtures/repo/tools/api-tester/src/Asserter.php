<?php

// Test fixture for tests/tools/gaia-dev: the assertion checks the extractor reads.
namespace ApiTester;

class Asserter
{
    public function assert(array $expects, array $response)
    {
        $failures = [];

        if (isset($expects['status'])) {
            if ($expects['status'] !== $response['status']) {
                $failures[] = sprintf('status: expected %s, got %s', $expects['status'], $response['status']);
            }
        }

        $shape = isset($expects['shape']) ? $expects['shape'] : null;
        if ($shape === 'json' || $shape === 'jsonapi.resource') {
            $failures[] = 'shape: body does not match';
        }

        if (array_key_exists('errorEqual', $expects)) {
            $failures[] = "errorEqual: expected '{$expects['errorEqual']}'";
        }

        if (!empty($expects['attributesEqual'])) {
            foreach ($expects['attributesEqual'] as $attr => $expected) {
                $failures[] = "attributesEqual: attributes.{$attr} differs";
            }
        }

        return $failures;
    }
}
