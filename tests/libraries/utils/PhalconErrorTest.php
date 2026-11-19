<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Utils;

use Gaia\Exception\PhalconError;
use Phalcon\Dispatcher\Exception as DispatcherException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for turning Phalcon exceptions into JSON error responses.
 */
class PhalconErrorTest extends TestCase
{
    public function testHandlerNotFoundIs404()
    {
        $error = new PhalconError(
            new DispatcherException('AgentController handler class cannot be loaded', DispatcherException::EXCEPTION_HANDLER_NOT_FOUND)
        );

        $this->assertSame(404, $error->statusCode());
        $this->assertSame('resourceNotFound', $error->body()['error']);
    }

    public function testActionNotFoundIs404()
    {
        $error = new PhalconError(
            new DispatcherException('Action not found', DispatcherException::EXCEPTION_ACTION_NOT_FOUND)
        );

        $this->assertSame(404, $error->statusCode());
    }

    public function testOtherDispatcherErrorIs500()
    {
        $error = new PhalconError(
            new DispatcherException('Cyclic routing', DispatcherException::EXCEPTION_CYCLIC_ROUTING)
        );

        $this->assertSame(500, $error->statusCode());
    }

    public function testOtherPhalconErrorIs500()
    {
        $error = new PhalconError(new \Phalcon\Exception('Column "secret" does not belong to table "agents"'));

        $this->assertSame(500, $error->statusCode());
        $this->assertSame('internalServerError', $error->body()['error']);
    }

    public function testRawMessageIsHiddenWithoutDebug()
    {
        $error = new PhalconError(new \Phalcon\Exception('Column "secret" does not belong to table "agents"'));

        $this->assertStringNotContainsString('secret', json_encode($error->body()));
    }

    public function testRawMessageIsReturnedInDebug()
    {
        $error = new PhalconError(new \Phalcon\Exception('Column "secret" does not belong to table "agents"'), true);

        $this->assertSame('Column "secret" does not belong to table "agents"', $error->body()['error_description']);
    }

    /**
     * In Phalcon 5 the framework's exceptions (Mvc\Model\Exception, ...) no
     * longer extend Phalcon\Exception; they must still become a JSON 500.
     */
    public function testExceptionOutsidePhalconExceptionIs500()
    {
        $exception = new \RuntimeException('Column "secret" does not belong to table "agents"');
        $this->assertNotInstanceOf(\Phalcon\Exception::class, $exception);

        $error = new PhalconError($exception);

        $this->assertFalse($error->isNotFound());
        $this->assertSame(500, $error->statusCode());
        $this->assertSame('internalServerError', $error->body()['error']);
        $this->assertStringNotContainsString('secret', json_encode($error->body()));
    }

    public function testPhpErrorIs500()
    {
        $error = new PhalconError(new \TypeError('Argument 1 passed to secret() must be of the type int'));

        $this->assertSame(500, $error->statusCode());
        $this->assertSame('internalServerError', $error->body()['error']);
        $this->assertStringNotContainsString('secret', json_encode($error->body()));
    }

    public function testHandleSendsJsonForExceptionOutsidePhalconException()
    {
        $error = new PhalconError(new \RuntimeException('Syntax error, unexpected token IDENTIFIER'));

        $this->expectOutputString(json_encode($error->body()));
        $response = $error->handle(new \Phalcon\Http\Response());

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame(
            ['error' => 'internalServerError', 'error_description' => 'An internal server error occurred'],
            json_decode($response->getContent(), true)
        );
    }

    public function testHandleSendsJsonWithStatus()
    {
        $error = new PhalconError(
            new DispatcherException('Action not found', DispatcherException::EXCEPTION_ACTION_NOT_FOUND)
        );

        $this->expectOutputString(json_encode($error->body()));
        $response = $error->handle(new \Phalcon\Http\Response());

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame($error->body(), json_decode($response->getContent(), true));
    }
}
