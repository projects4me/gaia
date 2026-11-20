<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Utils;

use Gaia\Exception;
use Phalcon\Http\Response;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for building a Gaia exception's error response without sending it.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ExceptionToResponseTest extends TestCase
{
    /**
     * Every Gaia exception with the status and JSON body its response carries.
     *
     * @return array
     */
    public static function exceptionProvider()
    {
        return [
            'Access' => [new Exception\Access('denied'), 403, 'Forbidden', ['error' => 'denied']],
            'BadRequest' => [new Exception\BadRequest('bad'), 400, 'Bad Request', ['error' => 'bad']],
            'Exception' => [new Exception\Exception('boom'), 500, 'Internal Server Error', ['error' => 'boom']],
            'FileNotFound' => [new Exception\FileNotFound('nofile'), 500, 'Internal Server Error', ['error' => 'nofile']],
            'InvalidGrant' => [new Exception\InvalidGrant('ignored'), 401, 'Unauthorized', [
                'error' => 'invalid_grant',
                'error_description' => 'Invalid email and password combination',
            ]],
            'MigrationDriver' => [new Exception\MigrationDriver('driver'), 500, 'Internal Server Error', ['error' => 'driver']],
            'Permission' => [
                new Exception\Permission('levels', 0, null, ['suggestion' => 'use read']),
                422,
                'Access levels not allowed',
                ['error' => 'levels', 'suggestion' => 'use read'],
            ],
            'Conflict' => [new Exception\Conflict('stale'), 409, 'Conflict', [
                'error' => 'conflict',
                'error_description' => 'stale',
            ]],
            'ResourceLocked' => [new Exception\ResourceLocked('locked'), 423, 'Locked', [
                'error' => 'resourceLocked',
                'error_description' => 'locked',
            ]],
            'ResourceNotFound' => [new Exception\ResourceNotFound('missing'), 404, 'Resource Not found', ['error' => 'missing']],
            'TooManyRequests' => [new Exception\TooManyRequests('slow down', 30), 429, 'Too Many Requests', [
                'error' => 'tooManyRequests',
                'error_description' => 'slow down',
            ]],
            'UnAuthorized' => [new Exception\UnAuthorized('who'), 401, 'Unauthorized', ['error' => 'who']],
        ];
    }

    /**
     * @dataProvider exceptionProvider
     */
    public function testToResponseBuildsStatusAndBodyWithoutSending($exception, $status, $reason, $body)
    {
        $this->expectOutputString('');

        $response = $exception->toResponse();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame($status, $response->getStatusCode());
        $this->assertSame($reason, $response->getReasonPhrase());
        $this->assertSame($body, json_decode($response->getContent(), true));
        $this->assertFalse($response->isSent());
    }

    /**
     * @dataProvider exceptionProvider
     */
    public function testToResponseBuildsOnTheGivenResponse($exception, $status)
    {
        $given = new Response();
        $given->setHeader('Cache-Control', 'no-store');

        $response = $exception->toResponse($given);

        $this->assertSame($given, $response);
        $this->assertSame($status, $response->getStatusCode());
        $this->assertSame('no-store', $response->getHeaders()->get('Cache-Control'));
    }

    public function testTooManyRequestsSetsRetryAfter()
    {
        $response = (new Exception\TooManyRequests('slow down', 30))->toResponse();

        $this->assertSame('30', $response->getHeaders()->get('Retry-After'));
    }

    /**
     * Exceptions that leave the body empty when they carry no message.
     *
     * @return array
     */
    public static function emptyMessageProvider()
    {
        return [
            'Exception' => [new Exception\Exception()],
            'FileNotFound' => [new Exception\FileNotFound()],
            'MigrationDriver' => [new Exception\MigrationDriver()],
            'Permission' => [new Exception\Permission()],
            'ResourceLocked' => [new Exception\ResourceLocked()],
            'ResourceNotFound' => [new Exception\ResourceNotFound()],
            'UnAuthorized' => [new Exception\UnAuthorized()],
        ];
    }

    /**
     * @dataProvider emptyMessageProvider
     */
    public function testEmptyMessageLeavesBodyEmpty($exception)
    {
        $response = $exception->toResponse();
        $this->assertEmpty($response->getContent());
        $this->assertNotEmpty($response->getStatusCode());
    }

    /**
     * A Permission with a message but no additional data has a null suggestion.
     */
    public function testPermissionWithoutAdditionalDataHasNullSuggestion()
    {
        $response = (new Exception\Permission('levels'))->toResponse();
        $this->assertSame(
            ['error' => 'levels', 'suggestion' => null],
            json_decode($response->getContent(), true)
        );
    }

    /**
     * handle() builds on the shared DI response, keeps its headers, sends it once and returns it.
     */
    public function testHandleSendsTheSharedDiResponse()
    {
        $previous = \Phalcon\Di\Di::getDefault();
        $shared = $this->getMockBuilder(Response::class)->onlyMethods(['send'])->getMock();
        $shared->expects($this->once())->method('send')->willReturnSelf();
        $shared->setHeader('Cache-Control', 'no-store');

        $di = new \Phalcon\Di\Di();
        $di->setShared('response', $shared);
        \Phalcon\Di\Di::setDefault($di);
        try {
            $returned = (new Exception\ResourceNotFound('gone'))->handle();
        } finally {
            if ($previous !== null) {
                \Phalcon\Di\Di::setDefault($previous);
            } else {
                \Phalcon\Di\Di::reset();
            }
        }

        $this->assertSame($shared, $returned);
        $this->assertSame('404 Resource Not found', $returned->getStatusCode() . ' ' . $returned->getReasonPhrase());
        $this->assertSame('no-store', $returned->getHeaders()->get('Cache-Control'));
        $this->assertSame(['error' => 'gone'], json_decode($returned->getContent(), true));
    }
}
