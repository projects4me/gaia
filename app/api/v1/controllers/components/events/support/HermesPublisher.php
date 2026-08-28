<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers\Components\Events\Support;

/**
 * This class posts a V2 domain-event envelope to Hermes. Failures are logged
 * and swallowed so the REST save is not rolled back.
 *
 * @class   HermesPublisher
 * @package Gaia\MVC\REST\Controllers\Components\Events\Support
 */
class HermesPublisher
{
    /**
     * Optional injectable HTTP client. Callables receive the envelope.
     *
     * @var  mixed
     * @type mixed
     */
    protected $httpClient;

    /**
     * Creates a publisher, optionally with a test HTTP client.
     *
     * @param  mixed $httpClient Guzzle client or callable that receives the envelope.
     * @method __construct
     * @return void
     */
    public function __construct($httpClient = null)
    {
        $this->httpClient = $httpClient;
    }

    /**
     * Builds and posts a domain-event envelope. Returns null when required
     * fields are missing or the event name is not allowlisted.
     *
     * @param  string $eventName    Allowlisted event name from EventNames.
     * @param  string $projectId    Project id, or user:<userId> for notifications.
     * @param  string $resourceType Resource type (issue, milestone, comment, ...).
     * @param  string $resourceId   Persisted resource id.
     * @param  array  $changes      Field values the client can apply.
     * @param  array  $meta         Extra envelope metadata.
     * @method publishDomainEvent
     * @return array|null The posted envelope, or null when nothing was sent.
     */
    public function publishDomainEvent(
        $eventName,
        $projectId,
        $resourceType,
        $resourceId,
        array $changes = array(),
        array $meta = array()
    ) {
        global $logger;

        if (empty($eventName) || empty($projectId) || empty($resourceType) || empty($resourceId)) {
            return null;
        }
        if (!EventNames::isAllowed($eventName)) {
            if ($logger) {
                $logger->error('Hermes publish rejected unknown event: ' . $eventName);
            }
            return null;
        }

        $envelope = LiveEventEnvelope::build(
            $eventName,
            $projectId,
            $resourceType,
            $resourceId,
            $changes,
            $meta
        );
        $this->postEnvelope($envelope);
        return $envelope;
    }

    /**
     * POSTs the envelope to Hermes /publish. Catch and log any throwable.
     *
     * @param  array $envelope The V2 envelope built by LiveEventEnvelope.
     * @method postEnvelope
     * @return void
     */
    protected function postEnvelope(array $envelope)
    {
        global $logger;

        try {
            if (is_callable($this->httpClient)) {
                call_user_func($this->httpClient, $envelope);
                return;
            }

            $client = $this->httpClient ?: new \GuzzleHttp\Client(array(
                'timeout' => 2.0,
                'connect_timeout' => 1.0,
            ));
            $client->post($this->publishUrl(), array(
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'X-Hermes-Secret' => $this->secret(),
                ),
                'json' => $envelope,
            ));
        } catch (\Throwable $e) {
            if ($logger) {
                $logger->error('Hermes V2 ingest failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * Returns the Hermes ingest URL from config, env, or the Docker default.
     *
     * @method publishUrl
     * @return string
     */
    protected function publishUrl()
    {
        return rtrim($this->setting('url', getenv('HERMES_URL') ?: 'http://host.docker.internal:9000'), '/') . '/publish';
    }

    /**
     * Returns the shared ingest secret sent as X-Hermes-Secret.
     *
     * @method secret
     * @return string
     */
    protected function secret()
    {
        return $this->setting('secret', getenv('HERMES_SECRET') ?: 'hermes-dev-secret');
    }

    /**
     * Reads a hermes.* setting, falling back to the given default.
     *
     * @param  string $key      Setting key (url or secret).
     * @param  string $fallback Value used when the setting is empty.
     * @method setting
     * @return string
     */
    protected function setting($key, $fallback)
    {
        global $settings;

        if ($settings && isset($settings->hermes) && isset($settings->hermes->$key) && $settings->hermes->$key) {
            return (string) $settings->hermes->$key;
        }

        return $fallback;
    }
}
