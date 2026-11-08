<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Security;

use PHPUnit\Framework\TestCase;
use Gaia\Libraries\Agents\AgentCredentials;
use Gaia\Libraries\Agents\AgentLifecycle;
use Gaia\Libraries\Authorization\ClientKind;
use Gaia\Libraries\Authorization\ServiceClients;
use Gaia\Libraries\Authorization\TokenIntrospection;
use Gaia\Libraries\Oauth\Storage\Pdo;
use Gaia\Libraries\Security\IntrospectionCallerGuard;
use Gaia\MVC\Models\Agent;
use Gaia\MVC\Models\Serviceclient;
use Gaia\MVC\Models\User;

/**
 * Tests service-facing token introspection (D-065): which tokens are active,
 * their actor type, client, delegation, scope and project ids, and which
 * callers may introspect.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class TokenIntrospectionTest extends TestCase
{
    /**
     * Human user whose tokens are introspected; it also creates the agents.
     */
    public const USER_ID = 'test-user-token-introspect';

    private const USER_EMAIL = 'token-introspect-user@gaia.test';

    private const PREFIX = 'unit-tintro-';

    private const APP_CLIENT = 'unit-tintro-app';

    private const RUNTIME_CLIENT = 'unit-tintro-runtime';

    private const HERMES_CLIENT = 'unit-tintro-hermes';

    public static function setUpBeforeClass(): void
    {
        AgentTestFixtures::ensureHumanUser(self::USER_ID, self::USER_EMAIL);
    }

    public static function tearDownAfterClass(): void
    {
        self::purge();
        AgentTestFixtures::deleteUser(self::USER_ID);
    }

    protected function setUp(): void
    {
        global $currentUser;

        $currentUser = User::findFirstById(self::USER_ID);
        self::purge();
        $this->insertOauthClient(self::APP_CLIENT, ClientKind::APP);
        $this->createServiceClient(self::RUNTIME_CLIENT, 'runtime', '["agent.delegate","agent.read"]');
        $this->createServiceClient(self::HERMES_CLIENT, 'hermes', '["token.introspect"]');
    }

    public function testAUsersTokenCarriesTheUserAndTheirProjects(): void
    {
        $this->addMembership('unit-tintro-project-1');
        $this->addMembership('unit-tintro-project-2');
        $token = $this->token(self::APP_CLIENT, self::USER_EMAIL, 'application');

        $result = $this->introspection()->introspect($token);

        $this->assertTrue($result['active']);
        $this->assertSame(self::USER_ID, $result['sub']);
        $this->assertSame(TokenIntrospection::ACTOR_USER, $result['actorType']);
        $this->assertSame(self::APP_CLIENT, $result['clientId']);
        $this->assertNull($result['act']);
        $this->assertSame('application', $result['scope']);
        $this->assertGreaterThan(time(), $result['exp']);
        $this->assertEqualsCanonicalizing(['unit-tintro-project-1', 'unit-tintro-project-2'], $result['projectIds']);
        $this->assertNull($result['serviceKind']);
        $this->assertArrayNotHasKey('agent', $result);
        $this->assertArrayNotHasKey('toolGrants', $result);
    }

    public function testAnAgentsTokenCarriesTheAgentAndItsGrants(): void
    {
        $agent = $this->createAgent('unit-agent-tintro@gaia.test');
        $clientId = (new AgentCredentials())->issue($agent->id)['credential']->clientId;
        $token = $this->token($clientId, 'unit-agent-tintro@gaia.test');

        $result = $this->introspection()->introspect($token);

        $this->assertTrue($result['active']);
        $this->assertSame($agent->userId, $result['sub']);
        $this->assertSame(TokenIntrospection::ACTOR_AGENT, $result['actorType']);
        $this->assertSame($clientId, $result['clientId']);
        $this->assertNull($result['act']);
        $this->assertSame([], $result['projectIds']);
        $this->assertSame($agent->id, $result['agent']['id']);
        $this->assertSame([], $result['toolGrants']);
    }

    public function testAnAgentsTokenFromAnotherClientIsInactive(): void
    {
        $this->createAgent('unit-agent-tintro-cli@gaia.test');
        $token = $this->token(self::APP_CLIENT, 'unit-agent-tintro-cli@gaia.test');

        $this->assertSame(['active' => false], $this->introspection()->introspect($token));
    }

    public function testADelegatedTokenNamesTheActingService(): void
    {
        $agent = $this->createAgent('unit-agent-tintro-del@gaia.test');
        $token = $this->token(self::RUNTIME_CLIENT, 'unit-agent-tintro-del@gaia.test', 'issue.get');

        $result = $this->introspection()->introspect($token);

        $this->assertTrue($result['active']);
        $this->assertSame($agent->userId, $result['sub']);
        $this->assertSame(TokenIntrospection::ACTOR_AGENT, $result['actorType']);
        $this->assertSame(self::RUNTIME_CLIENT, $result['clientId']);
        $this->assertSame(self::RUNTIME_CLIENT, $result['act']);
        $this->assertSame('runtime', $result['serviceKind']);
        $this->assertSame('issue.get', $result['scope']);
        $this->assertSame($agent->id, $result['agent']['id']);
    }

    public function testAServiceCannotActForAPerson(): void
    {
        $token = $this->token(self::RUNTIME_CLIENT, self::USER_EMAIL);

        $this->assertSame(['active' => false], $this->introspection()->introspect($token));
    }

    public function testADelegatedTokenOfADisabledServiceIsInactive(): void
    {
        $this->createAgent('unit-agent-tintro-dis@gaia.test');
        $token = $this->token(self::RUNTIME_CLIENT, 'unit-agent-tintro-dis@gaia.test');
        $this->disable(self::RUNTIME_CLIENT);

        $this->assertSame(['active' => false], $this->introspection()->introspect($token));
    }

    public function testAServiceTokenCarriesTheServiceKind(): void
    {
        $token = $this->token(self::RUNTIME_CLIENT, null, 'agent.delegate agent.read');

        $result = $this->introspection()->introspect($token);

        $this->assertTrue($result['active']);
        $this->assertNull($result['sub']);
        $this->assertSame(TokenIntrospection::ACTOR_SERVICE, $result['actorType']);
        $this->assertSame(self::RUNTIME_CLIENT, $result['clientId']);
        $this->assertNull($result['act']);
        $this->assertSame('runtime', $result['serviceKind']);
        $this->assertSame([], $result['projectIds']);
        $this->assertSame('agent.delegate agent.read', $result['scope']);
    }

    public function testAServiceTokenOfADisabledServiceIsInactive(): void
    {
        $token = $this->token(self::RUNTIME_CLIENT, null);
        $this->disable(self::RUNTIME_CLIENT);

        $this->assertSame(['active' => false], $this->introspection()->introspect($token));
    }

    public function testATokenWithoutAUserFromAnAppClientIsInactive(): void
    {
        $token = $this->token(self::APP_CLIENT, null);

        $this->assertSame(['active' => false], $this->introspection()->introspect($token));
    }

    public function testExpiredUnknownAndEmptyTokensAreInactive(): void
    {
        $expired = $this->token(self::APP_CLIENT, self::USER_EMAIL, null, time() - 60);

        $this->assertSame(['active' => false], $this->introspection()->introspect($expired));
        $this->assertSame(['active' => false], $this->introspection()->introspect(self::PREFIX . 'unknown'));
        $this->assertSame(['active' => false], $this->introspection()->introspect(''));
        $this->assertSame(['active' => false], $this->introspection()->introspect(null));
    }

    public function testATokenOfADisabledUserIsInactive(): void
    {
        $token = $this->token(self::APP_CLIENT, self::USER_EMAIL);
        $db = \Phalcon\Di::getDefault()->get('db');
        $db->execute('UPDATE users SET "accountStatus" = \'Inactive\' WHERE id = ?', [self::USER_ID]);

        try {
            $this->assertSame(['active' => false], $this->introspection()->introspect($token));
        } finally {
            $db->execute('UPDATE users SET "accountStatus" = \'Active\' WHERE id = ?', [self::USER_ID]);
        }
    }

    public function testAServiceWithTokenIntrospectMayCall(): void
    {
        $token = $this->token(self::HERMES_CLIENT, null, 'token.introspect');

        $caller = $this->guard()->assertCaller($token);

        $this->assertSame(self::HERMES_CLIENT, $caller->clientId);
    }

    /**
     * @dataProvider refusedCallers
     */
    public function testOtherCallersAreRefused(string $case): void
    {
        $tokens = [
            'no token' => null,
            'unknown token' => self::PREFIX . 'unknown',
            'service without the scope' => $this->token(self::RUNTIME_CLIENT, null, 'agent.delegate'),
            'scope as a pattern only' => $this->token(self::HERMES_CLIENT, null, 'token.*'),
            'a user token with the scope' => $this->token(self::APP_CLIENT, self::USER_EMAIL, 'token.introspect'),
            'an app client without a user' => $this->token(self::APP_CLIENT, null, 'token.introspect'),
            'expired service token' => $this->token(self::HERMES_CLIENT, null, 'token.introspect', time() - 60),
        ];
        if ($case === 'disabled service') {
            $tokens[$case] = $this->token(self::HERMES_CLIENT, null, 'token.introspect');
            $this->disable(self::HERMES_CLIENT);
        }

        $this->expectException(\Gaia\Exception\UnAuthorized::class);
        $this->expectExceptionMessage(IntrospectionCallerGuard::ERROR);
        $this->guard()->assertCaller($tokens[$case]);
    }

    /**
     * @return array
     */
    public function refusedCallers(): array
    {
        $cases = [
            'no token', 'unknown token', 'service without the scope', 'scope as a pattern only',
            'a user token with the scope', 'an app client without a user', 'expired service token',
            'disabled service',
        ];

        return array_combine($cases, array_map(function ($case) {
            return [$case];
        }, $cases));
    }

    /**
     * @return TokenIntrospection
     */
    private function introspection()
    {
        return new TokenIntrospection($this->storage());
    }

    /**
     * @return IntrospectionCallerGuard
     */
    private function guard()
    {
        return new IntrospectionCallerGuard($this->storage());
    }

    /**
     * @param  string $email
     * @return Agent
     */
    private function createAgent($email)
    {
        return (new AgentLifecycle())->create(['name' => 'Unit ' . $email, 'email' => $email]);
    }

    /**
     * An access token as the grants store it.
     *
     * @param  string      $clientId
     * @param  string|null $email
     * @param  string|null $scope
     * @param  int|null    $expires
     * @return string
     */
    private function token($clientId, $email, $scope = null, $expires = null)
    {
        $token = self::PREFIX . bin2hex(random_bytes(8));
        $this->storage()->setAccessToken($token, $clientId, $email, $expires ?: time() + 3600, $scope);

        return $token;
    }

    /**
     * @param  string $clientId
     * @param  string $kind
     * @return void
     */
    private function insertOauthClient($clientId, $kind)
    {
        \Phalcon\Di::getDefault()->get('db')->execute(
            'INSERT INTO oauth_clients (id, client_id, client_secret, redirect_uri, grant_types, scope, user_id, kind)
             VALUES (?, ?, \'unit-not-a-secret\', \'\', \'client_credentials\', NULL, NULL, ?)',
            [$clientId, $clientId, $kind]
        );
    }

    /**
     * @param  string $clientId
     * @param  string $kind
     * @param  string $scopes JSON list
     * @return void
     */
    private function createServiceClient($clientId, $kind, $scopes)
    {
        $this->insertOauthClient($clientId, ClientKind::SERVICE);

        $client = new Serviceclient();
        $client->id = $clientId;
        $client->name = 'Unit ' . $clientId;
        $client->kind = $kind;
        $client->scopes = $scopes;
        $client->clientId = $clientId;
        $this->assertTrue($client->save(), implode(' ', array_map('strval', (array) $client->getMessages())));
    }

    /**
     * Disable a service client in the database, leaving its tokens in place.
     *
     * @param  string $clientId
     * @return void
     */
    private function disable($clientId)
    {
        \Phalcon\Di::getDefault()->get('db')->execute(
            'UPDATE serviceclients SET status = ? WHERE "clientId" = ?',
            [ServiceClients::STATUS_DISABLED, $clientId]
        );
    }

    /**
     * @param  string $projectId
     * @return void
     */
    private function addMembership($projectId)
    {
        \Phalcon\Di::getDefault()->get('db')->execute(
            'INSERT INTO memberships (id, "projectId", "userId", "createdUser", "modifiedUser", "createdUserName", "modifiedUserName")
             VALUES (?, ?, ?, ?, ?, \'testUser\', \'testUser\')',
            [$projectId, $projectId, self::USER_ID, self::USER_ID, self::USER_ID]
        );
    }

    /**
     * @return Pdo
     */
    private function storage()
    {
        return new Pdo(\Phalcon\Di::getDefault()->get('db')->getInternalHandler(), ['user_table' => 'users']);
    }

    private static function purge()
    {
        $db = \Phalcon\Di::getDefault()->get('db');
        $db->execute("DELETE FROM oauth_access_tokens WHERE access_token LIKE 'unit-tintro-%'");
        $db->execute("DELETE FROM memberships WHERE id LIKE 'unit-tintro-%'");
        $db->execute("DELETE FROM serviceclients WHERE \"clientId\" LIKE 'unit-tintro-%'");
        $db->execute("DELETE FROM oauth_clients WHERE client_id LIKE 'unit-tintro-%'");
        AgentTestFixtures::purge();
    }
}
