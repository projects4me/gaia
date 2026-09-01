<?php

namespace ApiTester;

/**
 * Arms a Node Socket.IO observer against live Hermes, then collects matched
 * domain:event envelopes after a Gaia write.
 */
class HermesObserver
{
    private $hermesUrl;
    private $waitScript;
    private $nodeBin;

    public function __construct($hermesUrl, $toolRoot = null)
    {
        $this->hermesUrl = rtrim($hermesUrl, '/');
        $root = $toolRoot !== null ? $toolRoot : (defined('API_TESTER_ROOT') ? API_TESTER_ROOT : dirname(__DIR__));
        $this->waitScript = $root . '/harness/hermes-wait/wait.js';
        $this->nodeBin = getenv('API_TESTER_NODE') ?: 'node';
    }

    /**
     * Fast preflight so misconfigured runs fail before the Gaia write.
     */
    public function assertHealthy()
    {
        $url = $this->hermesUrl . '/health';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 3,
            CURLOPT_CONNECTTIMEOUT => 2,
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false || $status < 200 || $status >= 300) {
            throw new \RuntimeException(
                "Hermes not reachable at {$url}"
                . ($error ? " ({$error})" : " (HTTP {$status})")
            );
        }
    }

    /**
     * Subscribe before the Gaia write; call finish() after the write completes.
     *
     * @param  string $token   OAuth bearer token (same user as the Gaia write)
     * @param  array  $expect  List of match objects (eventName, projectId, ...)
     * @param  int    $timeoutMs
     * @return array{finish: callable, cancel: callable}
     */
    public function arm($token, array $expect, $timeoutMs = 5000)
    {
        if (!is_file($this->waitScript)) {
            throw new \RuntimeException('hermes-wait script missing: ' . $this->waitScript);
        }

        $tmp = sys_get_temp_dir() . '/api-tester-hermes-' . bin2hex(random_bytes(8));
        $readyFile = $tmp . '.ready';
        $resultFile = $tmp . '.result';
        $expectFile = $tmp . '.expect.json';
        file_put_contents($expectFile, json_encode(array_values($expect)));

        $cmd = [
            $this->nodeBin,
            $this->waitScript,
            '--hermes-url',
            $this->hermesUrl,
            '--token',
            $token,
            '--expect-file',
            $expectFile,
            '--timeout-ms',
            (string) $timeoutMs,
            '--ready-file',
            $readyFile,
            '--result-file',
            $resultFile,
        ];

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        // Prefer local node_modules; fall back to sibling hermes install for socket.io-client.
        $env = $this->processEnv();
        $process = proc_open(
            $cmd,
            $descriptors,
            $pipes,
            dirname($this->waitScript),
            $env
        );
        if (!is_resource($process)) {
            @unlink($expectFile);
            throw new \RuntimeException('Failed to start hermes-wait (is node installed?)');
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $deadline = microtime(true) + max(5, ($timeoutMs / 1000) + 2);

        while (!is_file($readyFile)) {
            $stdout .= (string) stream_get_contents($pipes[1]);
            $stderr .= (string) stream_get_contents($pipes[2]);
            $status = proc_get_status($process);
            if (!$status['running']) {
                $stdout .= (string) stream_get_contents($pipes[1]);
                $stderr .= (string) stream_get_contents($pipes[2]);
                $this->closeProcess($process, $pipes);
                $this->cleanupFiles([$readyFile, $resultFile, $expectFile]);
                throw new \RuntimeException(
                    'hermes-wait exited before arming'
                    . ($stderr !== '' ? ': ' . trim($stderr) : '')
                    . ($stdout !== '' ? ' | ' . trim($stdout) : '')
                );
            }
            if (microtime(true) > $deadline) {
                proc_terminate($process);
                $this->closeProcess($process, $pipes);
                $this->cleanupFiles([$readyFile, $resultFile, $expectFile]);
                throw new \RuntimeException('Timed out waiting for hermes-wait to arm');
            }
            usleep(50000);
        }

        $state = [
            'process' => $process,
            'pipes' => $pipes,
            'readyFile' => $readyFile,
            'resultFile' => $resultFile,
            'expectFile' => $expectFile,
            'timeoutMs' => $timeoutMs,
            'stdout' => &$stdout,
            'stderr' => &$stderr,
        ];

        return [
            'finish' => function () use (&$state) {
                return $this->finish($state);
            },
            'cancel' => function () use (&$state) {
                $this->cancel($state);
            },
        ];
    }

    private function finish(array &$state)
    {
        $process = $state['process'];
        $pipes = $state['pipes'];
        $deadline = microtime(true) + ($state['timeoutMs'] / 1000) + 2;
        $exitCode = null;

        while (true) {
            $state['stdout'] .= (string) stream_get_contents($pipes[1]);
            $state['stderr'] .= (string) stream_get_contents($pipes[2]);
            $status = proc_get_status($process);
            if (!$status['running']) {
                // exitcode is only available on the first status read after exit.
                if (array_key_exists('exitcode', $status) && $status['exitcode'] !== -1) {
                    $exitCode = (int) $status['exitcode'];
                }
                break;
            }
            if (microtime(true) > $deadline) {
                proc_terminate($process);
                $this->closeProcess($process, $pipes);
                $this->cleanupFiles([$state['readyFile'], $state['resultFile'], $state['expectFile']]);
                throw new \RuntimeException('Timed out waiting for hermes-wait result');
            }
            usleep(50000);
        }

        // Drain and close pipes before proc_close (which invalidates them).
        $state['stdout'] .= (string) stream_get_contents($pipes[1]);
        $state['stderr'] .= (string) stream_get_contents($pipes[2]);
        foreach ([1, 2] as $i) {
            if (isset($pipes[$i]) && is_resource($pipes[$i])) {
                fclose($pipes[$i]);
                $pipes[$i] = null;
            }
        }
        if ($exitCode === null && is_resource($process)) {
            $exitCode = proc_close($process);
        } elseif (is_resource($process)) {
            proc_close($process);
        }
        if ($exitCode === null) {
            $exitCode = -1;
        }
        $state['process'] = null;

        $raw = is_file($state['resultFile'])
            ? file_get_contents($state['resultFile'])
            : $state['stdout'];
        $this->cleanupFiles([$state['readyFile'], $state['resultFile'], $state['expectFile']]);

        $json = json_decode(trim((string) $raw), true);
        if (!is_array($json)) {
            throw new \RuntimeException(
                'hermes-wait returned invalid JSON'
                . ($state['stderr'] !== '' ? ': ' . trim($state['stderr']) : '')
                . ($raw !== '' ? ' | ' . trim($raw) : '')
            );
        }

        if ($exitCode !== 0 || empty($json['ok'])) {
            $message = isset($json['error']) ? $json['error'] : 'hermes-wait failed';
            $message .= ' (exit=' . $exitCode . ')';
            if ($state['stderr'] !== '' && strpos($message, trim($state['stderr'])) === false) {
                $message .= ' | stderr: ' . trim($state['stderr']);
            }
            if (!empty($raw) && strpos($message, trim((string) $raw)) === false) {
                $message .= ' | out: ' . trim((string) $raw);
            }
            // Successful payload wins over a lost exit code (-1 after prior status read).
            if (!empty($json['ok']) && !empty($json['matched']) && is_array($json['matched']) && $exitCode === -1) {
                return $json['matched'];
            }
            throw new \RuntimeException($message);
        }

        if (empty($json['matched']) || !is_array($json['matched'])) {
            throw new \RuntimeException('hermes-wait succeeded without matched envelopes');
        }

        return $json['matched'];
    }

    private function cancel(array &$state)
    {
        if (isset($state['process']) && is_resource($state['process'])) {
            $status = proc_get_status($state['process']);
            if ($status['running']) {
                proc_terminate($state['process']);
            }
            $this->closeProcess($state['process'], $state['pipes']);
        }
        $this->cleanupFiles([
            isset($state['readyFile']) ? $state['readyFile'] : null,
            isset($state['resultFile']) ? $state['resultFile'] : null,
            isset($state['expectFile']) ? $state['expectFile'] : null,
        ]);
    }

    private function closeProcess($process, array $pipes)
    {
        foreach ([1, 2] as $i) {
            if (isset($pipes[$i]) && is_resource($pipes[$i])) {
                fclose($pipes[$i]);
            }
        }
        if (is_resource($process)) {
            proc_close($process);
        }
    }

    private function cleanupFiles(array $paths)
    {
        foreach ($paths as $path) {
            if ($path && is_file($path)) {
                @unlink($path);
            }
        }
    }

    /**
     * Build env for hermes-wait, including NODE_PATH fallbacks for socket.io-client.
     */
    private function processEnv()
    {
        $env = [];
        foreach ($_SERVER as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $env[$key] = $value;
            }
        }
        foreach ($_ENV as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $env[$key] = $value;
            }
        }

        $paths = [];
        $localModules = dirname($this->waitScript) . '/node_modules';
        if (is_dir($localModules)) {
            $paths[] = $localModules;
        }

        // wait.js lives at tools/api-tester/harness/hermes-wait/wait.js → gaia root is 5 up
        $gaiaRoot = dirname(dirname(dirname(dirname(dirname($this->waitScript)))));
        $hermesModules = $gaiaRoot . '/../hermes/node_modules';
        $hermesModules = realpath($hermesModules) ?: $hermesModules;
        if (is_dir($hermesModules)) {
            $paths[] = $hermesModules;
        }

        if (!empty($paths)) {
            $existing = isset($env['NODE_PATH']) ? $env['NODE_PATH'] : '';
            $env['NODE_PATH'] = implode(PATH_SEPARATOR, $paths)
                . ($existing !== '' ? PATH_SEPARATOR . $existing : '');
        }

        return $env;
    }
}
