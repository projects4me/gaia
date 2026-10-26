<?php

// Test fixture for tests/tools/gaia-dev: the mail and Hermes checks the extractor reads.
namespace ApiTester;

class Runner
{
    private function runCase(array $api)
    {
        $hermes = isset($api['hermes']) ? $api['hermes'] : null;
        $expectEvents = $hermes['expect'];
        $timeoutMs = isset($hermes['timeoutMs']) ? (int) $hermes['timeoutMs'] : 5000;
        if (array_key_exists('requireUp', $hermes)) {
            return $timeoutMs;
        }

        return $expectEvents;
    }

    private function assertMail(array $expect)
    {
        $timeoutMs = isset($expect['timeoutMs']) ? (int) $expect['timeoutMs'] : 5000;
        $failures = [];
        foreach (['fromEmail' => 'Address', 'fromName' => 'Name'] as $key => $field) {
            if (isset($expect[$key])) {
                $failures[] = $key . $field . $timeoutMs;
            }
        }
        if (isset($expect['subject']) && $expect['to'] !== '') {
            $failures[] = 'subject';
        }

        return $failures;
    }
}
