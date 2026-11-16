<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers\Components\Events\Support;

/**
 * This class tells the docs collab server that access to a doc changed, so
 * open editors turn read-only or close. It owns the collab server's URL, secret
 * and transport. Failures are logged and swallowed so the REST write is not
 * rolled back; the secret is never logged.
 *
 * @class   CollabNotifier
 * @package Gaia\MVC\REST\Controllers\Components\Events\Support
 */
class CollabNotifier
{
    const REASON_LOCKED = 'locked';
    const REASON_UNLOCKED = 'unlocked';
    const REASON_DELETED = 'deleted';

    /**
     * Optional injectable HTTP client. Callables receive the doc id and reason.
     *
     * @var  mixed
     * @type mixed
     */
    protected $httpClient;

    /**
     * @param  mixed $httpClient Guzzle client or callable that receives ($docId, $reason).
     * @method __construct
     * @return void
     */
    public function __construct($httpClient = null)
    {
        $this->httpClient = $httpClient;
    }

    /**
     * POSTs {reason} to {COLLAB_URL}/internal/docs/{docId}/access. Fails open.
     *
     * @param  string $docId  The doc whose access changed.
     * @param  string $reason One of the REASON_* constants.
     * @method notifyAccessChanged
     * @return bool True when the request was sent without error.
     */
    public function notifyAccessChanged($docId, $reason)
    {
        global $logger;

        if (empty($docId) || !in_array($reason, array(self::REASON_LOCKED, self::REASON_UNLOCKED, self::REASON_DELETED), true)) {
            return false;
        }

        try {
            if (!is_callable($this->httpClient) && $this->secret() === '') {
                if ($logger) {
                    $logger->error('Collab access notify skipped: COLLAB_SECRET is not configured');
                }
                return false;
            }

            if (is_callable($this->httpClient)) {
                ($this->httpClient)((string) $docId, $reason);
                return true;
            }

            $client = $this->httpClient ?: new \GuzzleHttp\Client(array(
                'timeout' => 2.0,
                'connect_timeout' => 0.5,
            ));
            $client->post($this->accessUrl((string) $docId), array(
                'allow_redirects' => false,
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'X-Collab-Secret' => $this->secret(),
                ),
                'json' => array('reason' => $reason),
            ));
            return true;
        } catch (\Throwable $e) {
            if ($logger) {
                $logger->error('Collab access notify failed: doc=' . $docId . ' reason=' . $reason . ' ' . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * @param  string $docId
     * @method accessUrl
     * @return string
     */
    protected function accessUrl($docId)
    {
        return rtrim($this->setting('url', getenv('COLLAB_URL') ?: 'http://host.docker.internal:9100'), '/')
            . '/internal/docs/' . rawurlencode($docId) . '/access';
    }

    /**
     * @method secret
     * @return string
     */
    protected function secret()
    {
        return $this->setting('secret', (string) getenv('COLLAB_SECRET'));
    }

    /**
     * Reads a collab.* setting, falling back to the given default.
     *
     * @param  string $key      Setting key (url or secret).
     * @param  string $fallback Value used when the setting is empty.
     * @method setting
     * @return string
     */
    protected function setting($key, $fallback)
    {
        global $settings;

        if ($settings && isset($settings->collab) && isset($settings->collab->$key) && $settings->collab->$key) {
            return (string) $settings->collab->$key;
        }

        return $fallback;
    }
}
