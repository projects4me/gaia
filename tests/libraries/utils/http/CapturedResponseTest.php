<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Utils\Http;

use Gaia\Core\MVC\Http\CapturedResponse;
use Phalcon\Http\Response\CookiesInterface;
use PHPUnit\Framework\TestCase;

/**
 * Tests that a sub-request's response never reaches the client when a
 * controller sends it directly, as ForgetpasswordController::postAction() does.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class CapturedResponseTest extends TestCase
{
    public function testDirectSendWritesNoBodyHeadersOrCookies(): void
    {
        $cookies = $this->createMock(CookiesInterface::class);
        $cookies->expects($this->never())->method('send');

        // ForgetpasswordController::postAction(): status, JSON content, send(), return.
        $response = new CapturedResponse();
        $response->setCookies($cookies);
        $response->setHeader('X-Probe', 'inner');
        $response->setStatusCode(200, 'OK');
        $response->setJsonContent(['message' => 'Password reset link has been sent to your email address.']);

        ob_start();
        try {
            $returned = $response->send();
        } finally {
            $output = ob_get_clean();
        }

        $this->assertSame('', $output);
        $this->assertSame($response, $returned);
        $this->assertTrue($response->isSent());
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('inner', $response->getHeaders()->get('X-Probe'));
        $this->assertSame(
            ['message' => 'Password reset link has been sent to your email address.'],
            json_decode($response->getContent(), true)
        );
    }
}
