<?php

namespace ApiTester;

/**
 * Reads the messages an SMTP sink (Mailpit) captured, over its HTTP API.
 *
 * The sink only records mail; nothing is delivered. The suite never touches
 * Gaia internals: it drives public flows and looks here for the result.
 */
class MailSink
{
    private $client;

    public function __construct($sinkUrl)
    {
        $this->client = new HttpClient(rtrim($sinkUrl, '/'), 5);
    }

    /**
     * Fast preflight so a misconfigured run fails before the Gaia write.
     */
    public function assertHealthy()
    {
        try {
            $response = $this->client->request('GET', '/api/v1/info');
        } catch (\RuntimeException $e) {
            throw new \RuntimeException('Mail sink not reachable: ' . $e->getMessage());
        }
        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw new \RuntimeException('Mail sink not healthy (HTTP ' . $response['status'] . ')');
        }
    }

    /**
     * Wait for messages addressed to a recipient and return their full detail.
     *
     * @param  string      $to        Recipient address
     * @param  string|null $subject   Only messages whose subject contains this text
     * @param  int         $timeoutMs How long to wait for the first message
     * @return array             Message detail arrays (empty when none arrived)
     */
    public function messagesTo($to, $subject = null, $timeoutMs = 5000)
    {
        $query = 'to:' . $to . ($subject !== null ? ' subject:"' . $subject . '"' : '');
        $deadline = microtime(true) + ($timeoutMs / 1000);
        do {
            $response = $this->client->request('GET', '/api/v1/search', [
                'query' => ['query' => $query],
            ]);
            $summaries = isset($response['json']['messages']) ? $response['json']['messages'] : [];
            if (!empty($summaries)) {
                $messages = [];
                foreach ($summaries as $summary) {
                    $detail = $this->client->request('GET', '/api/v1/message/' . $summary['ID']);
                    if (is_array($detail['json'])) {
                        $messages[] = $detail['json'];
                    }
                }
                return $messages;
            }
            usleep(200000);
        } while (microtime(true) < $deadline);

        return [];
    }
}
