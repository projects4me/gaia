<?php

namespace Gaia\Services;

use GuzzleHttp\Client;

/**
 * This service handles communication with external LLM (Large Language Model) services
 * for AI-powered functionality. It provides methods to generate issue plans and test
 * connectivity with the LLM service endpoint.
 *
 * The service uses Guzzle HTTP client to make REST API calls to the configured
 * LLM service URL and handles various error scenarios gracefully.
 *
 * @package  Gaia\Services
 * @category Service
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 * @author Rana Nouman <ranamnouman@gmail.com>
 */
class LLM
{
    /**
     * HTTP client instance for making API requests
     *
     * @var Client
     */
    protected $client;

    /**
     * Base URL for the LLM service endpoint
     *
     * @var string
     */
    protected $llmServiceUrl;

    /**
     * Constructor initializes the LLM service with HTTP client and service URL
     *
     * Sets up the Guzzle HTTP client with appropriate timeout configurations
     * and retrieves the LLM service URL from environment variables with
     * a fallback to the default service endpoint.
     */
    public function __construct()
    {
        $this->llmServiceUrl = getenv('LLM_SERVICE_URL') ?: 'http://ares:8000';

        $this->client = new Client([
            'timeout' => 120.0,
            'connect_timeout' => 5.0,
        ]);
    }

    /**
     * This function is used to generate an issue plan using the LLM service.
     *
     * The method sends issue details to the LLM service endpoint and processes
     * the response to generate comprehensive planning data. It handles various
     * HTTP status codes and exceptions to ensure robust error handling.
     *
     * @method generateIssuePlan
     * @param array $issueDetails Array containing issue information (projectName, issueDescription, issueSubject)
     *
     * @return array|null Returns the generated plan data as array or null on failure
     * @throws \GuzzleHttp\Exception\RequestException When HTTP request fails
     * @throws \Exception When unexpected errors occur during processing
     */
    public function generateIssuePlan($issueDetails)
    {
        try {
            $response = $this->client->post($this->llmServiceUrl . '/planissue', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'json' => [
                    'issueDetails' => $issueDetails
                ]
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode === 200) {
                $body = json_decode($response->getBody()->getContents(), true);
                return $body;
            } else {
                error_log("LLM service returned status code: " . $statusCode);
                return null;
            }
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            error_log("Error calling LLM service: " . $e->getMessage());
            return null;
        } catch (\Exception $e) {
            error_log("Unexpected error calling LLM service: " . $e->getMessage());
            return null;
        }
    }

    /**
     * This function is used to test the connection to the LLM service.
     *
     * The method performs a simple GET request to the LLM service root endpoint
     * to verify connectivity and service availability. It returns a boolean
     * indicating whether the service is reachable and responding.
     *
     * @method testConnection
     *
     * @return bool Returns true if connection is successful, false otherwise
     * @throws \Exception When connection test fails
     */
    public function testConnection()
    {
        try {
            $response = $this->client->get($this->llmServiceUrl . '/');
            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            error_log("LLM service connection test failed: " . $e->getMessage());
            return false;
        }
    }
}
