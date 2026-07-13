<?php

namespace ApiTester;

class HttpClient
{
    private $baseUri;
    private $timeout;

    public function __construct($baseUri, $timeout = 20)
    {
        $this->baseUri = rtrim($baseUri, '/');
        $this->timeout = $timeout;
    }

    public function request($method, $path, array $options = [])
    {
        $url = $this->baseUri . $path;
        if (!empty($options['query'])) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($options['query']);
        }

        $headers = isset($options['headers']) ? $options['headers'] : [];
        if (!isset($headers['Accept'])) {
            $headers['Accept'] = 'application/json';
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

        if (isset($options['json'])) {
            $body = json_encode($options['json']);
            $headers['Content-Type'] = 'application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        } elseif (isset($options['form'])) {
            $body = http_build_query($options['form']);
            $headers['Content-Type'] = 'application/x-www-form-urlencoded';
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headerLines);

        $raw = curl_exec($ch);
        if ($raw === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException('HTTP request failed: ' . $error . ' (' . $url . ')');
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $rawHeaders = substr($raw, 0, $headerSize);
        $body = substr($raw, $headerSize);
        $decoded = json_decode($body, true);

        return [
            'url' => $url,
            'method' => strtoupper($method),
            'status' => $status,
            'headers' => $rawHeaders,
            'body' => $body,
            'json' => is_array($decoded) ? $decoded : null,
        ];
    }
}
