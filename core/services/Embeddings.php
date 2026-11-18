<?php

namespace Gaia\Services;

use Gaia\Exception\EmbeddingsUnavailableException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;

/**
 * This service embeds text through the Ares POST /embeddings endpoint. It owns
 * the configuration and transport, and validates the response, so callers get
 * a vector of the expected size or an EmbeddingsUnavailableException, never an
 * empty vector.
 *
 * @package  Gaia\Services
 * @category Service
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 * @author Rana Nouman <ranamnouman@gmail.com>
 */
class Embeddings
{
    /**
     * Number of dimensions of the vectors Ares returns
     */
    const DIMENSIONS = 768;

    /**
     * HTTP client instance for making API requests
     *
     * @var Client
     */
    protected $client;

    /**
     * Base URL of the Ares service
     *
     * @var string
     */
    protected $serviceUrl;

    /**
     * Constructor reads the service URL and timeout from the environment.
     *
     * @param Client|null $client the HTTP client; built from the environment when null
     */
    public function __construct(?Client $client = null)
    {
        $this->serviceUrl = rtrim(getenv('LLM_SERVICE_URL') ?: 'http://ares:8000', '/');

        $configured = getenv('EMBEDDINGS_TIMEOUT');
        $timeout = (is_numeric($configured) && (float) $configured > 0) ? (float) $configured : 3.0;
        $this->client = $client ?: new Client([
            'timeout' => $timeout,
            'connect_timeout' => min($timeout, 3.0),
        ]);
    }

    /**
     * This function embeds a search query.
     *
     * @method embedQuery
     * @param string $text The query to embed
     *
     * @return float[] The vector, with exactly DIMENSIONS entries
     * @throws EmbeddingsUnavailableException When the service fails or answers with an invalid body
     */
    public function embedQuery(string $text): array
    {
        try {
            $response = $this->client->post($this->serviceUrl . '/embeddings', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'json' => [
                    'texts' => [$text],
                    'task' => 'query',
                ],
            ]);
        } catch (GuzzleException $e) {
            // The message stays generic (no internal host); the cause is kept as previous.
            $status = ($e instanceof RequestException && $e->hasResponse()) ? $e->getResponse()->getStatusCode() : null;
            $reason = $status !== null ? 'status ' . $status : 'no response';
            throw new EmbeddingsUnavailableException('Embeddings service request failed: ' . $reason, 0, $e);
        }

        if ($response->getStatusCode() !== 200) {
            throw new EmbeddingsUnavailableException('Embeddings service returned status ' . $response->getStatusCode());
        }

        return $this->vectorFrom((string) $response->getBody());
    }

    /**
     * Extract and validate the single vector from a response body.
     *
     * @param string $body The raw response body
     *
     * @return float[]
     * @throws EmbeddingsUnavailableException When the body does not match the contract
     */
    protected function vectorFrom(string $body): array
    {
        $data = json_decode($body, true);
        if (!is_array($data) || !isset($data['embeddings']) || !is_array($data['embeddings']) || count($data['embeddings']) !== 1) {
            throw new EmbeddingsUnavailableException('Embeddings service returned a malformed response');
        }

        $vector = $data['embeddings'][0];
        if (!is_array($vector)) {
            throw new EmbeddingsUnavailableException('Embeddings service returned a malformed vector');
        }
        if (count($vector) !== self::DIMENSIONS) {
            throw new EmbeddingsUnavailableException(
                'Embeddings service returned ' . count($vector) . ' dimensions, expected ' . self::DIMENSIONS
            );
        }
        foreach ($vector as $value) {
            if (!is_int($value) && !is_float($value)) {
                throw new EmbeddingsUnavailableException('Embeddings service returned a non-numeric vector');
            }
        }

        return array_map('floatval', array_values($vector));
    }
}
