<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Security;

use PHPUnit\Framework\TestCase;
use Gaia\Libraries\Agents\AgentCredentials;
use Gaia\Libraries\Agents\AgentLifecycle;
use Gaia\Libraries\Authorization\AgentDelegationGrant;
use Gaia\Libraries\Authorization\ClientKind;
use Gaia\Libraries\Authorization\OAuthServer;
use Gaia\Libraries\Authorization\ServiceClients;
use Gaia\Libraries\Security\AgentDelegationGuard;
use Gaia\Libraries\Security\TokenScope;
use Gaia\MVC\Models\Agent;
use Gaia\MVC\Models\Oauthaccesstoken;
use Gaia\MVC\Models\Serviceclient;
use Gaia\MVC\Models\User;

/**
 * Tests the agent-delegation grant (D-067): a runtime service client with
 * `agent.delegate` gets a short-lived token acting as an agent, held to the
 * agent's delegation scopes, and every other request is refused.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class AgentDelegationGrantTest extends TestCase
{
    /**
     * Human user that creates the agents and service clients.
     */
    public const ADMIN_ID = 'test-user-delegation-admin';

    /**
     * Prefix of every service client id these tests create.
     */
    private const CLIENT_PREFIX = 'unit-dlg-';

    /**
     * The secret every service client in these tests has.
     */
    private const SECRET = 'p4m_cs_unit-delegation-secret';

    public static function setUpBeforeClass(): void
    {
        AgentTestFixtures::ensureHumanUser(self::ADMIN_ID, 'delegation-admin@gaia.test');
    }

    public static function tearDownAfterClass(): void
    {
        self::purgeClients();
        AgentTestFixtures::purge();
        AgentTestFixtures::deleteUser(self::ADMIN_ID);
    }

    protected function setUp(): void
    {
        global $currentUser;

        $currentUser = User::findFirstById(self::ADMIN_ID);
        self::purgeClients();
        AgentTestFixtures::purge();
    }

    public function testARuntimeGetsATokenActingAsTheAgentWithAllItsDelegationScopes(): void
    {
        $this->createRuntime('rt-1');
        $agent = $this->createAgent('happy', ['issue.*', 'comment.get']);

        $before = time();
        $response = $this->tokenResponse(self::CLIENT_PREFIX . 'rt-1', self::SECRET, $agent->id);

        $this->assertSame(200, $response->getStatusCode(), json_encode($response->getParameters()));
        $this->assertNull($response->getParameter('refresh_token'));
        $this->assertSame(3600, (int) $response->getParameter('expires_in'));

        $token = $this->storedToken($response);
        $this->assertSame('unit-agent-dlg-happy@gaia.test', $token->user_id);
        $this->assertSame(self::CLIENT_PREFIX . 'rt-1', $token->client_id);
        $this->assertSame('issue.* comment.get', $token->scope);
        $expires = strtotime($token->expires . ' UTC');
        $this->assertGreaterThanOrEqual($before + 3600 - 5, $expires);
        $this->assertLessThanOrEqual(time() + 3600 + 5, $expires);
        $this->assertNotNull($this->serviceClient('rt-1')->lastUsedAt);
    }

    public function testARuntimeMayAskForPartOfTheAgentsDelegationScopes(): void
    {
        $this->createRuntime('rt-2');
        $agent = $this->createAgent('part', ['issue.*', 'comment.get']);

        $response = $this->tokenResponse(self::CLIENT_PREFIX . 'rt-2', self::SECRET, $agent->id, 'issue.get comment.get');

        $this->assertSame(200, $response->getStatusCode(), json_encode($response->getParameters()));
        $this->assertSame('issue.get comment.get', $this->storedToken($response)->scope);
    }

    public function testADelegatedTokenReachesNothingOutsideItsScopes(): void
    {
        $this->createRuntime('rt-3');
        $agent = $this->createAgent('reach', ['*.*']);

        $response = $this->tokenResponse(self::CLIENT_PREFIX . 'rt-3', self::SECRET, $agent->id, 'issue.get');
        $scope = new TokenScope($this->storedToken($response)->scope);

        $this->assertTrue($scope->isScoped());
        $this->assertTrue($scope->covers('issue.get'));
        $this->assertFalse($scope->covers('issue.update'));
        $this->assertFalse($scope->covers('project.get'));

        // Even all of `*.*` never reaches the hard exclusions (D-064).
        $response = $this->tokenResponse(self::CLIENT_PREFIX . 'rt-3', self::SECRET, $agent->id);
        $scope = new TokenScope($this->storedToken($response)->scope);
        $this->assertFalse($scope->covers('agent.update'));
        $this->assertFalse($scope->covers('agentcredential.create'));
    }

    public function testTheTokenRowsAreKept(): void
    {
        $this->createRuntime('rt-4');
        $agent = $this->createAgent('kept', ['issue.get']);

        $this->tokenResponse(self::CLIENT_PREFIX . 'rt-4', self::SECRET, $agent->id);
        $this->tokenResponse(self::CLIENT_PREFIX . 'rt-4', self::SECRET, $agent->id);

        $this->assertCount(2, Oauthaccesstoken::find([
            'conditions' => 'client_id = :clientId: AND user_id = :userId:',
            'bind' => ['clientId' => self::CLIENT_PREFIX . 'rt-4', 'userId' => 'unit-agent-dlg-kept@gaia.test'],
        ]));
    }

    /**
     * @dataProvider uncoveredScopes
     */
    public function testARuntimeCannotAskForScopesTheAgentDoesNotHave($requested): void
    {
        $this->createRuntime('rt-5');
        $agent = $this->createAgent('uncovered', ['issue.*']);

        $response = $this->tokenResponse(self::CLIENT_PREFIX . 'rt-5', self::SECRET, $agent->id, $requested);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('invalid_scope', $response->getParameter('error'));
        $this->assertNull($response->getParameter('access_token'));
    }

    public static function uncoveredScopes(): array
    {
        return [
            'another module' => ['project.get'],
            'a wider pattern' => ['*.get'],
            'one of two' => ['issue.get comment.get'],
            'a word that does not narrow' => ['application'],
        ];
    }

    public function testAnAgentWithoutDelegationScopesCannotBeDelegated(): void
    {
        $this->createRuntime('rt-6');
        $agent = $this->createAgent('none', []);

        $this->expectRefusal(AgentDelegationGuard::AGENT_NOT_DELEGABLE_ERROR);

        $this->tokenResponse(self::CLIENT_PREFIX . 'rt-6', self::SECRET, $agent->id);
    }

    public function testAnInactiveAgentCannotBeDelegated(): void
    {
        $this->createRuntime('rt-7');
        $agent = $this->createAgent('inactive', ['issue.get']);
        $user = User::findFirstById($agent->userId);
        $user->accountStatus = 'Inactive';
        $this->assertTrue($user->save(), $this->messagesOf($user));

        $this->expectRefusal(AgentDelegationGuard::AGENT_NOT_DELEGABLE_ERROR);

        $this->tokenResponse(self::CLIENT_PREFIX . 'rt-7', self::SECRET, $agent->id);
    }

    public function testADeletedAgentCannotBeDelegated(): void
    {
        $this->createRuntime('rt-8');
        $agent = $this->createAgent('deleted', ['issue.get']);
        $this->assertTrue($agent->delete());

        $this->expectRefusal(AgentDelegationGuard::AGENT_NOT_DELEGABLE_ERROR);

        $this->tokenResponse(self::CLIENT_PREFIX . 'rt-8', self::SECRET, $agent->id);
    }

    public function testAnUnknownOrMissingAgentCannotBeDelegated(): void
    {
        $this->createRuntime('rt-9');

        $this->expectRefusal(AgentDelegationGuard::AGENT_NOT_DELEGABLE_ERROR);

        $this->tokenResponse(self::CLIENT_PREFIX . 'rt-9', self::SECRET, '');
    }

    public function testAServiceClientWithoutAgentDelegateCannotDelegate(): void
    {
        $this->createRuntime('hermes-1', 'hermes');
        $agent = $this->createAgent('nodelegate', ['issue.get']);

        $this->expectRefusal(AgentDelegationGuard::CLIENT_NOT_RUNTIME_ERROR);

        $this->tokenResponse(self::CLIENT_PREFIX . 'hermes-1', self::SECRET, $agent->id);
    }

    public function testAPatternDoesNotStandInForAgentDelegate(): void
    {
        $client = $this->createRuntime('rt-10');
        $client->scopes = json_encode(['agent.*']);
        $this->assertTrue($client->save(), $this->messagesOf($client));
        $agent = $this->createAgent('pattern', ['issue.get']);

        $this->expectRefusal(AgentDelegationGuard::CLIENT_NOT_RUNTIME_ERROR);

        $this->tokenResponse(self::CLIENT_PREFIX . 'rt-10', self::SECRET, $agent->id);
    }

    public function testADisabledRuntimeCannotDelegate(): void
    {
        $client = $this->createRuntime('rt-11');
        $client->status = ServiceClients::STATUS_DISABLED;
        $this->assertTrue($client->save(), $this->messagesOf($client));
        $agent = $this->createAgent('disabled', ['issue.get']);

        $this->expectRefusal(AgentDelegationGuard::CLIENT_NOT_RUNTIME_ERROR);

        $this->tokenResponse(self::CLIENT_PREFIX . 'rt-11', self::SECRET, $agent->id);
    }

    public function testAnAgentClientCannotDelegate(): void
    {
        $agent = $this->createAgent('self', ['issue.get']);
        $issued = (new AgentCredentials())->issue($agent->id);

        $this->expectRefusal(AgentDelegationGuard::CLIENT_NOT_RUNTIME_ERROR);

        $this->tokenResponse($issued['credential']->clientId, $issued['secret'], $agent->id);
    }

    public function testAWrongSecretGetsNothing(): void
    {
        $this->createRuntime('rt-12');
        $agent = $this->createAgent('secret', ['issue.get']);

        $response = $this->tokenResponse(self::CLIENT_PREFIX . 'rt-12', 'p4m_cs_wrong', $agent->id);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('invalid_client', $response->getParameter('error'));
    }

    public function testTheStorageLetsAKindUseTheGrantsConfiguredForIt(): void
    {
        $storage = new \Gaia\Libraries\Oauth\Storage\Pdo(
            \Phalcon\Di::getDefault()->get('db')->getInternalHandler(),
            ['user_table' => 'users', 'kind_grant_types' => [ClientKind::SERVICE => [AgentDelegationGrant::GRANT_TYPE]]]
        );
        $this->insertOauthClient(self::CLIENT_PREFIX . 'app-1', ClientKind::APP);
        $this->insertOauthClient(self::CLIENT_PREFIX . 'svc-1', ClientKind::SERVICE);

        $this->assertFalse($storage->checkRestrictedGrantType(self::CLIENT_PREFIX . 'app-1', AgentDelegationGrant::GRANT_TYPE));
        $this->assertTrue($storage->checkRestrictedGrantType(self::CLIENT_PREFIX . 'svc-1', AgentDelegationGrant::GRANT_TYPE));
        $this->assertFalse($storage->checkRestrictedGrantType(self::CLIENT_PREFIX . 'svc-1', 'password'));
    }

    /**
     * @param  string $message
     * @return void
     */
    private function expectRefusal($message)
    {
        $this->expectException(\Gaia\Exception\UnAuthorized::class);
        $this->expectExceptionMessage($message);
    }

    /**
     * An agent with the given delegation scopes.
     *
     * @param  string   $handle
     * @param  string[] $scopes
     * @return Agent
     */
    private function createAgent($handle, array $scopes)
    {
        $agent = (new AgentLifecycle())->create([
            'name' => 'Unit Delegation ' . $handle,
            'email' => 'unit-agent-dlg-' . $handle . '@gaia.test',
            'delegationScopes' => json_encode($scopes),
        ]);

        return Agent::findFirstById($agent->id);
    }

    /**
     * A service client with its oauth client.
     *
     * @param  string $suffix
     * @param  string $kind
     * @return Serviceclient
     */
    private function createRuntime($suffix, $kind = 'runtime')
    {
        $clientId = self::CLIENT_PREFIX . $suffix;
        $this->insertOauthClient($clientId, ClientKind::SERVICE);

        $client = new Serviceclient();
        $client->id = 'unit-dlg-' . bin2hex(random_bytes(8));
        $client->name = 'Unit ' . $clientId;
        $client->kind = $kind;
        $client->clientId = $clientId;
        $this->assertTrue($client->save(), $this->messagesOf($client));

        return Serviceclient::findFirstById($client->id);
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
             VALUES (?, ?, ?, \'\', \'client_credentials\', NULL, NULL, ?)',
            [$clientId, $clientId, password_hash(self::SECRET, PASSWORD_DEFAULT), $kind]
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
            'bind' => ['clientId' => self::CLIENT_PREFIX . $suffix],
        ]);
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
     * A delegation token request through `OAuthServer`, as the token endpoint makes it.
     *
     * @param  string      $clientId
     * @param  string      $secret
     * @param  string      $agentId
     * @param  string|null $scope
     * @return \OAuth2\Response
     */
    private function tokenResponse($clientId, $secret, $agentId, $scope = null)
    {
        $body = [
            'grant_type' => AgentDelegationGrant::GRANT_TYPE,
            'client_id' => $clientId,
            'client_secret' => $secret,
            'agent_id' => $agentId,
        ];
        if ($scope !== null) {
            $body['scope'] = $scope;
        }
        $request = new \OAuth2\Request([], $body, [], [], [], ['REQUEST_METHOD' => 'POST']);

        $oauthServer = new OAuthServer($request);
        $oauthServer->addGrantType();

        return $oauthServer->getServer()->handleTokenRequest($request);
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

    private static function purgeClients()
    {
        $db = \Phalcon\Di::getDefault()->get('db');
        $db->execute("DELETE FROM oauth_access_tokens WHERE client_id LIKE 'unit-dlg-%' OR client_id LIKE 'agent-unit-delegation%'");
        $db->execute("DELETE FROM serviceclients WHERE \"clientId\" LIKE 'unit-dlg-%'");
        $db->execute("DELETE FROM oauth_clients WHERE client_id LIKE 'unit-dlg-%'");
    }
}
