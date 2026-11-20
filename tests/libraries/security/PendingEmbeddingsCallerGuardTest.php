<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Security;

use Gaia\Exception\Access;
use Gaia\Exception\UnAuthorized;
use Gaia\Libraries\Security\PendingEmbeddingsCallerGuard;
use PHPUnit\Framework\TestCase;

/**
 * Only the Ares service reaches the pending list (agaia-4764): a live user-less token with the searchpending.get
 * scope whose client is an active service client of kind ares. No live token is 401, any other caller 403.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class PendingEmbeddingsCallerGuardTest extends TestCase
{
    /**
     * Stored tokens by access token.
     *
     * @var array
     */
    private $tokens;

    /**
     * Active service clients by client id: their kind.
     *
     * @var array
     */
    private $clients;

    protected function setUp(): void
    {
        if (!interface_exists(\OAuth2\Storage\AccessTokenInterface::class)) {
            $this->markTestSkipped('The OAuth2 library is not available');
        }
        $future = time() + 3600;
        $this->tokens = [
            'ares' => ['client_id' => 'ares-client', 'user_id' => null, 'scope' => 'issue.get searchpending.get', 'expires' => $future],
            'ares-without-scope' => ['client_id' => 'ares-client', 'user_id' => null, 'scope' => 'issue.get issue.update', 'expires' => $future],
            'ares-scope-prefix' => ['client_id' => 'ares-client', 'user_id' => null, 'scope' => 'searchpending.getx searchpending', 'expires' => $future],
            'ares-expired' => ['client_id' => 'ares-client', 'user_id' => null, 'scope' => 'searchpending.get', 'expires' => time() - 1],
            'hermes' => ['client_id' => 'hermes-client', 'user_id' => null, 'scope' => 'searchpending.get', 'expires' => $future],
            'user' => ['client_id' => 'projects4me', 'user_id' => 'user-1', 'scope' => 'searchpending.get', 'expires' => $future],
            'disabled-ares' => ['client_id' => 'disabled-client', 'user_id' => null, 'scope' => 'searchpending.get', 'expires' => $future],
        ];
        $this->clients = ['ares-client' => 'ares', 'hermes-client' => 'hermes'];
    }

    public function testTheAresServiceWithTheScopePasses()
    {
        $this->assertSame('ares', $this->guard()->assertCaller('ares')->kind);
    }

    /**
     * @dataProvider unauthenticated
     */
    public function testNoLiveTokenOrAnInactiveClientIsUnauthorized($token)
    {
        $this->expectException(UnAuthorized::class);
        $this->guard()->assertCaller($token);
    }

    public static function unauthenticated()
    {
        return [
            'no token' => [null],
            'empty token' => [''],
            'unknown token' => ['nope'],
            'expired token' => ['ares-expired'],
            'client no longer active' => ['disabled-ares'],
        ];
    }

    /**
     * @dataProvider forbidden
     */
    public function testAnyOtherCallerIsForbidden($token)
    {
        $this->expectException(Access::class);
        $this->expectExceptionMessage(PendingEmbeddingsCallerGuard::ERROR);
        $this->guard()->assertCaller($token);
    }

    public static function forbidden()
    {
        return [
            'user token' => ['user'],
            'ares without the scope' => ['ares-without-scope'],
            'only a look-alike scope word' => ['ares-scope-prefix'],
            'another service kind with the scope' => ['hermes'],
        ];
    }

    /**
     * @return PendingEmbeddingsCallerGuard
     */
    private function guard()
    {
        $storage = new class ($this->tokens) implements \OAuth2\Storage\AccessTokenInterface {
            private $tokens;

            public function __construct(array $tokens)
            {
                $this->tokens = $tokens;
            }

            public function getAccessToken($oauthToken)
            {
                return $this->tokens[$oauthToken] ?? null;
            }

            public function setAccessToken($oauthToken, $clientId, $userId, $expires, $scope = null)
            {
            }
        };

        return new class ($storage, $this->clients) extends PendingEmbeddingsCallerGuard {
            private $clients;

            public function __construct($storage, array $clients)
            {
                parent::__construct($storage);
                $this->clients = $clients;
            }

            protected function serviceClient($clientId)
            {
                if (!isset($this->clients[$clientId])) {
                    throw new UnAuthorized('not an active service client');
                }

                return (object) ['kind' => $this->clients[$clientId]];
            }
        };
    }
}
