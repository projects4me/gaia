<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Utils;

use Gaia\Exception\CliFatalErrorHandler;
use Gaia\Exception\FatalError;
use Gaia\Exception\FatalErrorHandler;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the shutdown handlers that turn a fatal PHP error into a log
 * entry and a JSON 500 (HTTP) or a non-zero exit (CLI).
 */
class FatalErrorHandlerTest extends TestCase
{
    /**
     * A simulated error_get_last() for a class that fails to load.
     *
     * @param int $type
     * @return array
     */
    private function lastError(int $type = E_COMPILE_ERROR): array
    {
        return [
            'type' => $type,
            'message' => 'Declaration of SubRequest::getJsonRawBody() must be compatible with secret()',
            'file' => '/var/www/html/core/http/SubRequest.php',
            'line' => 42,
        ];
    }

    /**
     * A logger double that records what was logged.
     *
     * @return object
     */
    private function logger()
    {
        return new class {
            public $errors = [];

            public function error($message)
            {
                $this->errors[] = $message;
            }
        };
    }

    public function testEveryFatalTypeIsFatal()
    {
        foreach ([E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR] as $type) {
            $this->assertNotNull(FatalError::fromLastError($this->lastError($type)), "type $type");
        }
    }

    public function testNonFatalOrMissingErrorIsIgnored()
    {
        $this->assertNull(FatalError::fromLastError(null));
        foreach ([E_WARNING, E_NOTICE, E_DEPRECATED, E_USER_WARNING] as $type) {
            $this->assertNull(FatalError::fromLastError($this->lastError($type)), "type $type");
        }
    }

    public function testHeadersNotSentLogsAndAnswersJson500()
    {
        $logger = $this->logger();
        $handler = new FatalErrorHandler($logger);
        $expected = ['error' => 'internalServerError', 'error_description' => 'An internal server error occurred'];

        $this->expectOutputString(json_encode($expected));
        $response = $handler->handle($this->lastError(), false, new \Phalcon\Http\Response());

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame($expected, json_decode($response->getContent(), true));
        $this->assertStringNotContainsString('secret', $response->getContent());
        $this->assertCount(1, $logger->errors);
        $this->assertStringContainsString('E_COMPILE_ERROR', $logger->errors[0]);
        $this->assertStringContainsString('getJsonRawBody', $logger->errors[0]);
        $this->assertStringContainsString('/var/www/html/core/http/SubRequest.php:42', $logger->errors[0]);
    }

    public function testDebugReturnsRawMessage()
    {
        $handler = new FatalErrorHandler($this->logger(), true);

        ob_start();
        $response = $handler->handle($this->lastError(), false, new \Phalcon\Http\Response());
        ob_end_clean();

        $this->assertSame(
            $this->lastError()['message'],
            json_decode($response->getContent(), true)['error_description']
        );
    }

    public function testHeadersSentOnlyLogs()
    {
        $logger = $this->logger();
        $handler = new FatalErrorHandler($logger);

        $this->expectOutputString('');
        $this->assertNull($handler->handle($this->lastError(), true, new \Phalcon\Http\Response()));
        $this->assertCount(1, $logger->errors);
    }

    public function testNonFatalErrorDoesNothing()
    {
        $logger = $this->logger();
        $handler = new FatalErrorHandler($logger);

        $this->expectOutputString('');
        $this->assertNull($handler->handle($this->lastError(E_WARNING), false, new \Phalcon\Http\Response()));
        $this->assertSame([], $logger->errors);
    }

    public function testCliWritesFatalErrorAndExitsNonZero()
    {
        $stream = fopen('php://memory', 'w+');

        $exitCode = (new CliFatalErrorHandler())->handle($this->lastError(E_ERROR), $stream);

        rewind($stream);
        $written = stream_get_contents($stream);
        $this->assertSame(CliFatalErrorHandler::EXIT_CODE, $exitCode);
        $this->assertNotSame(0, $exitCode);
        $this->assertStringContainsString('E_ERROR', $written);
        $this->assertStringContainsString('/var/www/html/core/http/SubRequest.php:42', $written);
    }

    public function testCliIgnoresNonFatalError()
    {
        $stream = fopen('php://memory', 'w+');

        $this->assertSame(0, (new CliFatalErrorHandler())->handle($this->lastError(E_NOTICE), $stream));
        rewind($stream);
        $this->assertSame('', stream_get_contents($stream));
    }
}
