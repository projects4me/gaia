<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Security;

use PHPUnit\Framework\TestCase;
use Gaia\Libraries\Agents\AgentCredentials;
use Gaia\Libraries\Agents\AgentLifecycle;
use Gaia\Libraries\Oauth\Storage\Pdo;
use Gaia\Libraries\Security\AgentLoginGuard;
use Gaia\MVC\Models\Agent;
use Gaia\MVC\Models\Agentcredential;
use Gaia\MVC\Models\Oauthclient;
use Gaia\MVC\Models\User;

/**
 * Tests issuing, rotating and revoking an agent's client credentials, the
 * hashed secret check, and which grants agents and people may use.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class AgentCredentialsTest extends TestCase
{
    /**
     * Human user that issues the credentials.
     */
    public const ISSUER_ID = 'test-user-agent-cred-issuer';

    /**
     * A client id no agent in these tests owns.
     */
    private const TAKEN_CLIENT_ID = 'agent-unit-cred-taken';

    public static function setUpBeforeClass(): void
    {
        global $currentUser;

        AgentTestFixtures::ensureHumanUser(self::ISSUER_ID, 'agent-cred-issuer@gaia.test');
        $currentUser = User::findFirstById(self::ISSUER_ID);
    }

    public static function tearDownAfterClass(): void
    {
        AgentTestFixtures::purge();
        self::deleteTakenClient();
        AgentTestFixtures::deleteUser(self::ISSUER_ID);
    }

    protected function setUp(): void
    {
        global $currentUser;

        $currentUser = User::findFirstById(self::ISSUER_ID);
        AgentTestFixtures::purge();
        self::deleteTakenClient();
    }

    public function testIssueStoresOnlyTheHashOfAPrefixedSecret(): void
    {
        $agent = $this->createAgent('Unit Cred Agent', 'unit-agent-cred-issue@gaia.test');

        $issued = (new AgentCredentials())->issue($agent->id);

        $secret = $issued['secret'];
        $this->assertStringStartsWith(AgentCredentials::SECRET_PREFIX, $secret);
        $this->assertGreaterThan(strlen(AgentCredentials::SECRET_PREFIX) + 32, strlen($secret));

        $credential = $issued['credential'];
        $this->assertSame('agent-unit-cred-agent', $credential->clientId);
        $this->assertSame($agent->id, $credential->agentId);
        $this->assertSame(self::ISSUER_ID, Agentcredential::findFirstById($credential->id)->createdUser);

        $client = $this->clientOf($agent);
        $this->assertSame($credential->clientId, $client->client_id);
        $this->assertSame(AgentCredentials::GRANT_TYPES, $client->grant_types);
        $this->assertNotSame($secret, $client->client_secret);
        $this->assertTrue(password_verify($secret, $client->client_secret));
    }

    public function testRotatingKeepsTheClientIdAndRevokesThePreviousSecret(): void
    {
        $agent = $this->createAgent('Unit Cred Rotate', 'unit-agent-cred-rotate@gaia.test');
        $credentials = new AgentCredentials();

        $first = $credentials->issue($agent->id);
        $storage = $this->storage();
        $token = 'unit-cred-rotate-token-' . bin2hex(random_bytes(8));
        $storage->setAccessToken($token, $first['credential']->clientId, $agent->userId, time() + 3600);
        $this->assertNotEmpty($storage->getAccessToken($token));

        $second = $credentials->issue($agent->id);

        $this->assertSame($first['credential']->clientId, $second['credential']->clientId);
        $this->assertTrue((bool) Agentcredential::findFirstById($first['credential']->id)->deleted);
        $this->assertFalse((bool) Agentcredential::findFirstById($second['credential']->id)->deleted);

        $this->assertFalse($storage->checkClientCredentials($second['credential']->clientId, $first['secret']));
        $this->assertTrue($storage->checkClientCredentials($second['credential']->clientId, $second['secret']));
        $this->assertFalse($storage->getAccessToken($token));
    }

    public function testFindLiveSkipsARevokedCredential(): void
    {
        $agent = $this->createAgent('Unit Cred Find', 'unit-agent-cred-find@gaia.test');
        $credentials = new AgentCredentials();
        $first = $credentials->issue($agent->id)['credential'];
        $second = $credentials->issue($agent->id)['credential'];

        $this->assertNull($credentials->findLive($first->id));
        $this->assertSame($second->id, $credentials->findLive($second->id)->id);
        $this->assertNull($credentials->findLive('no-such-credential'));
    }

    public function testIssueRejectsAnUnknownAgent(): void
    {
        $this->expectException(\Gaia\Exception\Permission::class);
        $this->expectExceptionMessage('Credentials can only be issued for an existing agent.');

        (new AgentCredentials())->issue('no-such-agent');
    }

    public function testATakenClientIdGetsASuffix(): void
    {
        $db = \Phalcon\Di::getDefault()->get('db');
        $db->execute(
            "INSERT INTO oauth_clients (id, client_id, client_secret, redirect_uri, grant_types, scope, user_id)
             VALUES ('unit-cred-taken', ?, 'x', '', 'client_credentials', NULL, NULL)",
            [self::TAKEN_CLIENT_ID]
        );
        $agent = $this->createAgent('Unit Cred Taken', 'unit-agent-cred-taken@gaia.test');

        $clientId = (new AgentCredentials())->issue($agent->id)['credential']->clientId;

        $this->assertMatchesRegularExpression('/^agent-unit-cred-taken-[0-9a-f]{6}$/', $clientId);
    }

    public function testALongNameMakesAClientIdThatFitsTheColumn(): void
    {
        $agent = $this->createAgent(str_repeat('Long Agent Name ', 3), 'unit-agent-cred-long@gaia.test');

        $clientId = (new AgentCredentials())->issue($agent->id)['credential']->clientId;

        $this->assertLessThanOrEqual(36, strlen($clientId));
        $this->assertStringStartsWith('agent-long-agent-name-', $clientId);
    }

    public function testRevokeClientDeletesTheClient(): void
    {
        $agent = $this->createAgent('Unit Cred Revoke', 'unit-agent-cred-revoke@gaia.test');
        $issued = (new AgentCredentials())->issue($agent->id);

        (new AgentCredentials())->revokeClient($issued['credential']->clientId);

        $this->assertNull($this->clientOf($agent));
        $this->assertFalse($this->storage()->checkClientCredentials($issued['credential']->clientId, $issued['secret']));
    }

    public function testRevokeAgentRevokesItsCredentials(): void
    {
        $agent = $this->createAgent('Unit Cred Delete', 'unit-agent-cred-delete@gaia.test');
        $issued = (new AgentCredentials())->issue($agent->id);

        (new AgentCredentials())->revokeAgent($agent);

        $this->assertNull($this->clientOf($agent));
        $this->assertTrue((bool) Agentcredential::findFirstById($issued['credential']->id)->deleted);
    }

    public function testRecordUseStampsTheLiveCredential(): void
    {
        $agent = $this->createAgent('Unit Cred Used', 'unit-agent-cred-used@gaia.test');
        $credential = (new AgentCredentials())->issue($agent->id)['credential'];
        $this->assertNull(Agentcredential::findFirstById($credential->id)->lastUsedAt);

        $this->assertTrue((new AgentCredentials())->recordUse($credential->clientId));

        $this->assertNotNull(Agentcredential::findFirstById($credential->id)->lastUsedAt);
    }

    public function testAFailedUsageStampDoesNotThrow(): void
    {
        $credentials = new class extends AgentCredentials {
            protected function db()
            {
                return new class {
                    public function execute()
                    {
                        throw new \PDOException('database unavailable');
                    }
                };
            }
        };

        $this->assertFalse($credentials->recordUse('agent-unit-cred-any'));
    }

    public function testPlainClientSecretsStillCheck(): void
    {
        $db = \Phalcon\Di::getDefault()->get('db');
        $db->execute(
            "INSERT INTO oauth_clients (id, client_id, client_secret, redirect_uri, grant_types, scope, user_id)
             VALUES ('unit-cred-taken', ?, 'plain-secret', '', 'password', NULL, NULL)",
            [self::TAKEN_CLIENT_ID]
        );

        $this->assertTrue($this->storage()->checkClientCredentials(self::TAKEN_CLIENT_ID, 'plain-secret'));
        $this->assertFalse($this->storage()->checkClientCredentials(self::TAKEN_CLIENT_ID, 'wrong'));
        $this->assertFalse($this->storage()->checkClientCredentials(self::TAKEN_CLIENT_ID, null));
    }

    public function testClientCredentialsSignInAsTheAgentUser(): void
    {
        $agent = $this->createAgent('Unit Cred Login', 'unit-agent-cred-login@gaia.test');
        $clientId = (new AgentCredentials())->issue($agent->id)['credential']->clientId;

        $user = AgentLoginGuard::assertClientCredentialsAllowed($clientId);

        $this->assertSame($agent->userId, $user->id);
    }

    public function testClientCredentialsRefusedForADisabledAgent(): void
    {
        $agent = $this->createAgent('Unit Cred Disabled', 'unit-agent-cred-disabled@gaia.test');
        $clientId = (new AgentCredentials())->issue($agent->id)['credential']->clientId;
        \Phalcon\Di::getDefault()->get('db')->execute(
            'UPDATE users SET "accountStatus" = \'Inactive\' WHERE id = ?',
            [$agent->userId]
        );

        $this->expectException(\Gaia\Exception\UnAuthorized::class);
        $this->expectExceptionMessage(AgentLoginGuard::CLIENT_NOT_AGENT_ERROR);

        AgentLoginGuard::assertClientCredentialsAllowed($clientId);
    }

    public function testClientCredentialsRefusedForAClientWithoutAnAgent(): void
    {
        $db = \Phalcon\Di::getDefault()->get('db');
        $db->execute(
            "INSERT INTO oauth_clients (id, client_id, client_secret, redirect_uri, grant_types, scope, user_id)
             VALUES ('unit-cred-taken', ?, 'x', '', 'client_credentials', NULL, ?)",
            [self::TAKEN_CLIENT_ID, self::ISSUER_ID]
        );

        $this->expectException(\Gaia\Exception\UnAuthorized::class);
        $this->expectExceptionMessage(AgentLoginGuard::CLIENT_NOT_AGENT_ERROR);

        AgentLoginGuard::assertClientCredentialsAllowed(self::TAKEN_CLIENT_ID);
    }

    public function testPasswordGrantRefusedForAnAgent(): void
    {
        $agent = $this->createAgent('Unit Cred Password', 'unit-agent-cred-password@gaia.test');

        $this->expectException(\Gaia\Exception\UnAuthorized::class);
        $this->expectExceptionMessage(AgentLoginGuard::AGENT_PASSWORD_ERROR);

        AgentLoginGuard::assertPasswordGrantAllowed(User::findFirstById($agent->userId));
    }

    public function testPasswordGrantAllowedForAPerson(): void
    {
        AgentLoginGuard::assertPasswordGrantAllowed(User::findFirstById(self::ISSUER_ID));

        $this->addToAssertionCount(1);
    }

    /**
     * @param  string $name
     * @param  string $email
     * @return Agent
     */
    private function createAgent($name, $email)
    {
        return (new AgentLifecycle())->create(['name' => $name, 'email' => $email]);
    }

    /**
     * @param  Agent $agent
     * @return Oauthclient|null
     */
    private function clientOf(Agent $agent)
    {
        $client = Oauthclient::findFirst([
            'conditions' => 'user_id = :userId:',
            'bind' => ['userId' => (string) $agent->userId],
        ]);

        return $client ?: null;
    }

    /**
     * @return Pdo
     */
    private function storage()
    {
        return new Pdo(\Phalcon\Di::getDefault()->get('db')->getInternalHandler(), ['user_table' => 'users']);
    }

    private static function deleteTakenClient()
    {
        \Phalcon\Di::getDefault()->get('db')->execute(
            "DELETE FROM oauth_clients WHERE id = 'unit-cred-taken'"
        );
    }
}
