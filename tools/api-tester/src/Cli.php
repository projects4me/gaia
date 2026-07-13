<?php

namespace ApiTester;

class Cli
{
    public static function main(array $argv)
    {
        $command = isset($argv[1]) ? $argv[1] : null;
        $options = self::parseOptions(array_slice($argv, 2));

        if ($command === null || $command === 'help' || isset($options['help'])) {
            self::printHelp();
            return 0;
        }

        if ($command !== 'run') {
            fwrite(STDERR, "Unknown command: {$command}\n\n");
            self::printHelp();
            return 1;
        }

        $toolRoot = defined('API_TESTER_ROOT') ? API_TESTER_ROOT : dirname(__DIR__);
        $baseUri = isset($options['base-uri']) ? rtrim($options['base-uri'], '/') : null;
        $apisPath = isset($options['apis'])
            ? $options['apis']
            : ($toolRoot . '/apis/client.json');
        $fixturesPath = isset($options['fixtures'])
            ? $options['fixtures']
            : ($toolRoot . '/fixtures/default.json');
        $reportPath = isset($options['report'])
            ? $options['report']
            : (getcwd() . '/api-tester-report.json');
        $filter = isset($options['filter']) ? $options['filter'] : null;

        if (!$baseUri) {
            fwrite(STDERR, "Missing required --base-uri\n");
            return 1;
        }

        if (!is_file($apisPath)) {
            fwrite(STDERR, "APIs file not found: {$apisPath}\n");
            return 1;
        }

        if (!is_file($fixturesPath)) {
            fwrite(STDERR, "Fixtures file not found: {$fixturesPath}\n");
            return 1;
        }

        $runner = new Runner($baseUri, $apisPath, $fixturesPath, $reportPath, $filter);
        return $runner->run();
    }

    private static function parseOptions(array $args)
    {
        $options = [];
        $count = count($args);
        for ($i = 0; $i < $count; $i++) {
            $arg = $args[$i];
            if (strpos($arg, '--') !== 0) {
                continue;
            }
            $name = substr($arg, 2);
            if ($name === 'help') {
                $options['help'] = true;
                continue;
            }
            if (strpos($name, '=') !== false) {
                list($key, $value) = explode('=', $name, 2);
                $options[$key] = $value;
                continue;
            }
            $value = isset($args[$i + 1]) ? $args[$i + 1] : null;
            if ($value === null || strpos($value, '--') === 0) {
                $options[$name] = true;
                continue;
            }
            $options[$name] = $value;
            $i++;
        }
        return $options;
    }

    private static function printHelp()
    {
        echo <<<HELP
api-tester - portable black-box HTTP API verification tool

This tool is framework-agnostic. It only needs:
  - a running HTTP API (--base-uri)
  - an APIs list JSON (--apis)
  - fixtures/auth map JSON (--fixtures)

Usage:
  php tools/api-tester/bin/api-tester run --base-uri <url> [options]

Required:
  --base-uri <url>         Running API host (e.g. http://localhost:8080)

Options:
  --apis <file>            APIs list JSON
  --fixtures <file>        Fixtures/auth map JSON
  --report <file>          Report output path (default: ./api-tester-report.json)
  --filter <substring>     Only run API ids/paths containing this substring
  --help                   Show this help

Auth env vars (optional; can also come from fixtures.auth):
  API_TEST_CLIENT_ID
  API_TEST_CLIENT_SECRET
  API_TEST_EMAIL
  API_TEST_PASSWORD

Examples:
  php tools/api-tester/bin/api-tester run --base-uri http://localhost:8081
  php tools/api-tester/bin/api-tester run --base-uri https://api.staging.example.com --apis tools/api-tester/apis/backend.json
  php tools/api-tester/bin/api-tester run --base-uri http://localhost:8081 --filter project

HELP;
    }
}
