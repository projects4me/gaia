<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Security;

use PHPUnit\Framework\TestCase;
use Gaia\Libraries\Agents\AgentCredentials;
use Gaia\Libraries\Agents\AgentLifecycle;
use Gaia\Libraries\Authorization\ClientCredentialsGrant;
use Gaia\Libraries\Authorization\ClientKind;
use Gaia\Libraries\Authorization\ServiceClients;
use Gaia\Libraries\Oauth\Storage\Pdo;
use Gaia\Libraries\Security\AgentLoginGuard;
use Gaia\Libraries\Security\ServiceClientGuard;
use Gaia\MVC\Models\Oauthaccesstoken;
use Gaia\MVC\Models\Oauthclient;
use Gaia\MVC\Models\Serviceclient;
use Gaia\MVC\Models\User;

/**
 * Tests how the client_credentials grant branches on the client's kind:
 * agent clients get the agent user's token, service clients a token with no
 * user and their own scopes, and disabled service clients and app clients
 * get nothing.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ClientCredentialsGrantTest extends TestCase
{
    /**
     * Human user that creates the service clients.
     */
    public const ADMIN_ID = 'test-user-grant-admin';

    /**
     * Prefix of every service client id these tests create.
     */
    private const SERVICE_PREFIX = 'unit-svc-';

    /**
     * The secret every service client in these tests has.
     */
    private const SERVICE_SECRET = 'p4m_cs_unit-service-secret';

    public static function setUpBeforeClass(): void
    {
        AgentTestFixtures::ensureHumanUser(self::ADMIN_ID, 'grant-admin@gaia.test');
    }

    public static function tearDownAfterClass(): void
    {
        self::purgeServiceClients();
        AgentTestFixtures::purge();
        AgentTestFixtures::deleteUser(self::ADMIN_ID);
    }

    protected function setUp(): void
    {
        global $currentUser;

        $currentUser = User::findFirstById(self::ADMIN_ID);
        self::purgeServiceClients();
        AgentTestFixtures::purge();
    }

    public function testAnAgentClientGetsTheAgentUsersToken(): void
    {
        $agent = (new AgentLifecycle())->create(['name' => 'Unit Grant Agent', 'email' => 'unit-agent-grant@gaia.test']);
        $issued = (new AgentCredentials())->issue($agent->id);
        $clientId = $issued['credential']->clientId;

        $this->assertSame(ClientKind::AGENT, ClientKind::of($clientId));

        $token = $this->requestToken($clientId, $issued['secret']);

        $this->assertSame('unit-agent-grant@gaia.test', $token->user_id);
    }

    public function testAServiceClientGetsATokenWithoutAUserAndWithItsScopes(): void
    {
        $this->createServiceClient('runtime-1', 'runtime');

        $response = $this->tokenResponse(self::SERVICE_PREFIX . 'runtime-1', self::SERVICE_SECRET);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNull($response->getParameter('refresh_token'));
        $token = $this->storedToken($response);
        $this->assertNull($token->user_id);
        $this->assertSame('agent.delegate agent.read project.read issue.read', $token->scope);
        $this->assertNotNull($this->serviceClient('runtime-1')->lastUsedAt);
    }

    public function testAServiceClientMayAskForPartOfItsScopes(): void
    {
        $this->createServiceClient('runtime-2', 'runtime');

        $response = $this->tokenResponse(self::SERVICE_PREFIX . 'runtime-2', self::SERVICE_SECRET, 'agent.read');

        $this->assertSame('agent.read', $this->storedToken($response)->scope);
    }

    public function testAServiceClientCannotAskForScopesItDoesNotHave(): void
    {
        $this->createServiceClient('hermes-1', 'hermes');

        $response = $this->tokenResponse(self::SERVICE_PREFIX . 'hermes-1', self::SERVICE_SECRET, 'agent.delegate');

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('invalid_scope', $response->getParameter('error'));
    }

    public function testADisabledServiceClientGetsNothing(): void
    {
        $client = $this->createServiceClient('disabled-1', 'runtime');
        $client->status = ServiceClients::STATUS_DISABLED;
        $this->assertTrue($client->save(), $this->messagesOf($client));

        $this->expectException(\Gaia\Exception\UnAuthorized::class);
        $this->expectExceptionMessage(ServiceClientGuard::CLIENT_NOT_SERVICE_ERROR);

        $this->tokenResponse(self::SERVICE_PREFIX . 'disabled-1', self::SERVICE_SECRET);
    }

    public function testAnActiveServiceClientsTokenIsAccepted(): void
    {
        $this->createServiceClient('token-ok', 'runtime');
        $token = $this->requestToken(self::SERVICE_PREFIX . 'token-ok', self::SERVICE_SECRET);

        $client = ServiceClientGuard::assertServiceTokenAllowed($token);

        $this->assertSame(self::SERVICE_PREFIX . 'token-ok', (string) $client->clientId);
        $this->assertSame('runtime', (string) $client->kind);
    }

    public function testATokenOfAClientDisabledSinceItWasIssuedIsRefused(): void
    {
        $this->createServiceClient('token-disabled', 'runtime');
        $token = $this->requestToken(self::SERVICE_PREFIX . 'token-disabled', self::SERVICE_SECRET);
        // Disable without the behaviour, which would also delete the token.
        \Phalcon\Di::getDefault()->get('db')->execute(
            'UPDATE serviceclients SET status = ? WHERE "clientId" = ?',
            [ServiceClients::STATUS_DISABLED, self::SERVICE_PREFIX . 'token-disabled']
        );

        $this->expectException(\Gaia\Exception\UnAuthorized::class);
        $this->expectExceptionMessage(ServiceClientGuard::CLIENT_NOT_SERVICE_ERROR);

        ServiceClientGuard::assertServiceTokenAllowed($token);
    }

    public function testATokenWithoutAClientIsRefused(): void
    {
        $this->expectException(\Gaia\Exception\UnAuthorized::class);

        ServiceClientGuard::assertServiceTokenAllowed(new Oauthaccesstoken());
    }

    public function testADeletedServiceClientGetsNothing(): void
    {
        $client = $this->createServiceClient('deleted-1', 'runtime');
        $this->assertTrue($client->delete());

        $this->expectException(\Gaia\Exception\UnAuthorized::class);
        $this->expectExceptionMessage(ServiceClientGuard::CLIENT_NOT_SERVICE_ERROR);

        $this->tokenResponse(self::SERVICE_PREFIX . 'deleted-1', self::SERVICE_SECRET);
    }

    public function testAnAppClientGetsNothing(): void
    {
        $this->insertOauthClient(self::SERVICE_PREFIX . 'app-1', ClientKind::APP, null);

        $this->expectException(\Gaia\Exception\UnAuthorized::class);
        $this->expectExceptionMessage(AgentLoginGuard::CLIENT_NOT_AGENT_ERROR);

        $this->tokenResponse(self::SERVICE_PREFIX . 'app-1', self::SERVICE_SECRET);
    }

    public function testAServiceClientNeverGetsAUsersToken(): void
    {
        $agent = (new AgentLifecycle())->create(['name' => 'Unit Grant Bound', 'email' => 'unit-agent-grant-bound@gaia.test']);
        $this->createServiceClient('bound-1', 'runtime', $agent->userId);

        $this->expectException(\Gaia\Exception\UnAuthorized::class);
        $this->expectExceptionMessage(ServiceClientGuard::CLIENT_NOT_SERVICE_ERROR);

        $this->tokenResponse(self::SERVICE_PREFIX . 'bound-1', self::SERVICE_SECRET);
    }

    public function testTheAgentGuardRefusesAServiceClientBoundToAnAgent(): void
    {
        $agent = (new AgentLifecycle())->create(['name' => 'Unit Grant Bound2', 'email' => 'unit-agent-grant-bound2@gaia.test']);
        $this->insertOauthClient(self::SERVICE_PREFIX . 'bound-2', ClientKind::SERVICE, $agent->userId);

        $this->expectException(\Gaia\Exception\UnAuthorized::class);
        $this->expectExceptionMessage(AgentLoginGuard::CLIENT_NOT_AGENT_ERROR);

        AgentLoginGuard::assertClientCredentialsAllowed(self::SERVICE_PREFIX . 'bound-2');
    }

    public function testAnAgentClientNeverGetsAServiceToken(): void
    {
        $agent = (new AgentLifecycle())->create(['name' => 'Unit Grant Both', 'email' => 'unit-agent-grant-both@gaia.test']);
        $issued = (new AgentCredentials())->issue($agent->id);
        $clientId = $issued['credential']->clientId;
        $this->saveServiceClient($clientId, 'runtime');

        $token = $this->requestToken($clientId, $issued['secret']);

        $this->assertSame('unit-agent-grant-both@gaia.test', $token->user_id);
    }

    public function testAHermesClientDefaultsToTokenIntrospection(): void
    {
        $client = $this->createServiceClient('hermes-2', 'hermes');

        $this->assertSame('["token.introspect"]', $client->scopes);
        $this->assertSame(ServiceClients::STATUS_ACTIVE, $client->status);
    }

    public function testDisablingAClientDeletesItsTokens(): void
    {
        $client = $this->createServiceClient('revoke-1', 'runtime');
        $response = $this->tokenResponse(self::SERVICE_PREFIX . 'revoke-1', self::SERVICE_SECRET);
        $this->assertNotEmpty($this->storedToken($response));

        $client->status = ServiceClients::STATUS_DISABLED;
        $this->assertTrue($client->save(), $this->messagesOf($client));

        $this->assertEmpty(Oauthaccesstoken::findFirst([
            'conditions' => 'client_id = :clientId:',
            'bind' => ['clientId' => self::SERVICE_PREFIX . 'revoke-1'],
        ]));
    }

    /**
     * A service client with its oauth client.
     *
     * @param  string      $suffix
     * @param  string      $kind
     * @param  string|null $userId User the oauth client names (none for a real service client).
     * @return Serviceclient
     */
    private function createServiceClient($suffix, $kind, $userId = null)
    {
        $clientId = self::SERVICE_PREFIX . $suffix;
        $this->insertOauthClient($clientId, ClientKind::SERVICE, $userId);

        return $this->saveServiceClient($clientId, $kind);
    }

    /**
     * @param  string $clientId
     * @param  string $kind
     * @return Serviceclient
     */
    private function saveServiceClient($clientId, $kind)
    {
        $client = $this->newServiceClient($clientId, $kind);
        $this->assertTrue($client->save(), $this->messagesOf($client));

        return Serviceclient::findFirstById($client->id);
    }

    /**
     * @param  string $clientId
     * @param  string $kind
     * @return Serviceclient
     */
    private function newServiceClient($clientId, $kind)
    {
        $client = new Serviceclient();
        $client->id = 'unit-svc-' . bin2hex(random_bytes(8));
        $client->name = 'Unit ' . $clientId;
        $client->kind = $kind;
        $client->clientId = $clientId;

        return $client;
    }

    /**
     * @param  string      $clientId
     * @param  string      $kind
     * @param  string|null $userId
     * @return void
     */
    private function insertOauthClient($clientId, $kind, $userId)
    {
        \Phalcon\Di::getDefault()->get('db')->execute(
            'INSERT INTO oauth_clients (id, client_id, client_secret, redirect_uri, grant_types, scope, user_id, kind)
             VALUES (?, ?, ?, \'\', \'client_credentials\', NULL, ?, ?)',
            [$clientId, $clientId, password_hash(self::SERVICE_SECRET, PASSWORD_DEFAULT), $userId, $kind]
        );
    }

    /**
     * @param  string $suffix
     * @return Serviceclient
     */
    private function serviceClient($suffix)
    {
        return Serviceclient::findFirst([
            'conditions' => 'clientId = :clientId:',
            'bind' => ['clientId' => self::SERVICE_PREFIX . $suffix],
        ]);
    }

    /**
     * The stored access token a successful request issued.
     *
     * @param  string $clientId
     * @param  string $secret
     * @return Oauthaccesstoken
     */
    private function requestToken($clientId, $secret)
    {
        $response = $this->tokenResponse($clientId, $secret);
        $this->assertSame(200, $response->getStatusCode(), json_encode($response->getParameters()));

        return $this->storedToken($response);
    }

    /**
     * @param  \OAuth2\Response $response
     * @return Oauthaccesstoken|false
     */
    private function storedToken($response)
    {
        return Oauthaccesstoken::findFirst([
            'conditions' => 'access_token = :token:',
            'bind' => ['token' => (string) $response->getParameter('access_token')],
        ]);
    }

    /**
     * A client_credentials token request through bshaffer's server with the grant.
     *
     * @param  string      $clientId
     * @param  string      $secret
     * @param  string|null $scope
     * @return \OAuth2\Response
     */
    private function tokenResponse($clientId, $secret, $scope = null)
    {
        $storage = new Pdo(\Phalcon\Di::getDefault()->get('db')->getInternalHandler(), ['user_table' => 'users']);
        $server = new \OAuth2\Server($storage, ['access_lifetime' => 3600]);
        $server->addGrantType(new ClientCredentialsGrant($storage));

        $body = ['grant_type' => 'client_credentials', 'client_id' => $clientId, 'client_secret' => $secret];
        if ($scope !== null) {
            $body['scope'] = $scope;
        }

        return $server->handleTokenRequest(
            new \OAuth2\Request([], $body, [], [], [], ['REQUEST_METHOD' => 'POST'])
        );
    }

    /**
     * @param  \Phalcon\Mvc\Model $model
     * @return string
     */
    private function messagesOf($model)
    {
        $messages = [];
        foreach ($model->getMessages() as $message) {
            $messages[] = $message->getMessage();
        }

        return implode(' ', $messages);
    }

    private static function purgeServiceClients()
    {
        $db = \Phalcon\Di::getDefault()->get('db');
        $db->execute("DELETE FROM oauth_access_tokens WHERE client_id LIKE 'unit-svc-%' OR client_id LIKE 'agent-unit-grant%'");
        $db->execute("DELETE FROM serviceclients WHERE \"clientId\" LIKE 'unit-svc-%' OR \"clientId\" LIKE 'agent-unit-grant%'");
        $db->execute("DELETE FROM oauth_clients WHERE client_id LIKE 'unit-svc-%'");
    }
}
