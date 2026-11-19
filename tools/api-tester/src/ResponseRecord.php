<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace ApiTester;

/**
 * Turns an HTTP response into the record --record-bodies writes into the report:
 * status, content type and body (parsed JSON, the raw text when the body is
 * not JSON, or base64 when it is not valid UTF-8).
 *
 * JSON is decoded to objects (not associative arrays) so that `{}` and `[]`
 * stay distinct when the report is written and compared.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package ApiTester
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ResponseRecord
{
    /**
     * @param array $response the HttpClient response (status, headers, body)
     * @return array status, contentType, bodyFormat (json|text) and body
     */
    public static function fromResponse(array $response)
    {
        $body = (string) $response['body'];
        $format = 'text';
        $parsed = $body;
        if (trim($body) !== '') {
            $decoded = json_decode($body);
            if (json_last_error() === JSON_ERROR_NONE) {
                $format = 'json';
                $parsed = $decoded;
            }
        }
        // A binary (non-UTF-8) body would make json_encode fail on the whole report
        if ($format === 'text' && !preg_match('//u', $body)) {
            $format = 'base64';
            $parsed = base64_encode($body);
        }

        return [
            'status' => $response['status'],
            'contentType' => self::contentType(isset($response['headers']) ? $response['headers'] : ''),
            'bodyFormat' => $format,
            'body' => $parsed,
        ];
    }

    /**
     * The Content-Type of the last header block (curl keeps 100-continue and
     * redirect blocks in front of the final one).
     *
     * @param string $rawHeaders
     * @return string|null
     */
    public static function contentType($rawHeaders)
    {
        $contentType = null;
        foreach (preg_split('/\r?\n/', (string) $rawHeaders) as $line) {
            if (stripos($line, 'HTTP/') === 0) {
                $contentType = null;
                continue;
            }
            if (stripos($line, 'content-type:') === 0) {
                $contentType = trim(substr($line, strlen('content-type:')));
            }
        }
        return $contentType;
    }
}
