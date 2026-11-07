<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Utils;

use Gaia\Libraries\Utils\FatalErrorHandler;
use PHPUnit\Framework\TestCase;

/**
 * A PHP fatal error is answered as a JSON 500, not an HTML page with HTTP 200 (#4220).
 */
class FatalErrorHandlerTest extends TestCase
{
    /**
     * Memory exhaustion and other request-ending errors are fatal.
     */
    public function testFatalErrorTypes()
    {
        $handler = new FatalErrorHandler();

        $this->assertTrue($handler->isFatal(['type' => E_ERROR, 'message' => 'Allowed memory size of 134217728 bytes exhausted']));
        $this->assertTrue($handler->isFatal(['type' => E_PARSE]));
        $this->assertTrue($handler->isFatal(['type' => E_COMPILE_ERROR]));
    }

    /**
     * Warnings, notices and a clean shutdown are left alone.
     */
    public function testNonFatalErrorsAreIgnored()
    {
        $handler = new FatalErrorHandler();

        $this->assertFalse($handler->isFatal(null));
        $this->assertFalse($handler->isFatal(['type' => E_WARNING]));
        $this->assertFalse($handler->isFatal(['type' => E_NOTICE]));
        $this->assertFalse($handler->isFatal(['type' => E_DEPRECATED]));
    }

    /**
     * The body uses Gaia's error shape and does not leak the PHP message.
     */
    public function testResponseBodyIsGenericJsonError()
    {
        $body = json_decode((new FatalErrorHandler())->responseBody(), true);

        $this->assertSame(['error' => 'Internal Server Error'], $body);
    }
}
