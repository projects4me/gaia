<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Security;

use PHPUnit\Framework\TestCase;
use Gaia\Exception\UnAuthorized;
use Gaia\Libraries\Agents\AgentCredentials;
use Gaia\Libraries\Authorization\ClientCredentialsGrant;
use Gaia\Libraries\Authorization\ClientKind;
use Gaia\Libraries\Authorization\ServiceClientSecrets;
use Gaia\Libraries\Oauth\Storage\Pdo;
use Gaia\Libraries\Security\ServiceAcl;
use Gaia\Libraries\Security\TokenScope;
use Gaia\MVC\Models\Serviceclient;
use Gaia\MVC\Models\User;

/**
 * Tests that a service rotates its own secret (D-067): only its own
 * Serviceclient record, only `rotateSecret`, only with the
 * `serviceclient.rotate` scope; the new secret works at once and the previous
 * one only for the grace period.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ServiceClientRotationTest extends TestCase
{
    /**
     * Human user that creates the service clients.
     */
    public const ADMIN_ID = 'test-user-rotation-admin';

    /**
     * Prefix of every service client id these tests create.
     */
    private const PREFIX = 'unit-rot-';

    /**
     * The secret every service client starts with.
     */
    private const SECRET = 'p4m_cs_unit-rotation-secret';

    public static function setUpBeforeClass(): void
    {
        AgentTestFixtures::ensureHumanUser(self::ADMIN_ID, 'rotation-admin@gaia.test');
    }

    public static function tearDownAfterClass(): void
    {
        self::purge();
        AgentTestFixtures::purge();
        AgentTestFixtures::deleteUser(self::ADMIN_ID);
    }

    protected function setUp(): void
    {
        global $currentUser;

        $currentUser = User::findFirstById(self::ADMIN_ID);
        self::purge();
        AgentTestFixtures::purge();
    }

    public function testTheNewSecretWorksAndTheOldOneStaysValidForTheGracePeriod(): void
    {
        $clientId = $this->createServiceClient('grace')->clientId;
        $before = time();

        $rotated = (new ServiceClientSecrets())->rotate($clientId);

        $this->assertStringStartsWith(AgentCredentials::SECRET_PREFIX, $rotated['secret']);
        $this->assertNotSame(self::SECRET, $rotated['secret']);
        $expiresAt = strtotime($rotated['previousSecretExpiresAt'] . ' UTC');
        $this->assertGreaterThanOrEqual($before + ServiceClientSecrets::GRACE_SECONDS, $expiresAt);
        $this->assertLessThanOrEqual(time() + ServiceClientSecrets::GRACE_SECONDS, $expiresAt);

        $this->assertSame(200, $this->tokenStatus($clientId, $rotated['secret']));
        $this->assertSame(200, $this->tokenStatus($clientId, self::SECRET));
        $this->assertNotSame(200, $this->tokenStatus($clientId, 'p4m_cs_not-the-secret'));
    }

    public function testTheOldSecretIsRefusedOnceTheGracePeriodEnds(): void
    {
        $clientId = $this->createServiceClient('expired')->clientId;
        $rotated = (new ServiceClientSecrets())->rotate($clientId);

        $this->db()->execute(
            'UPDATE oauth_clients SET previous_secret_expires = ? WHERE client_id = ?',
            [gmdate('Y-m-d H:i:s', time() - 1), $clientId]
        );

        $this->assertNotSame(200, $this->tokenStatus($clientId, self::SECRET));
        $this->assertSame(200, $this->tokenStatus($clientId, $rotated['secret']));
    }

    public function testASecondRotationRetiresTheFirstSecret(): void
    {
        $clientId = $this->createServiceClient('twice')->clientId;
        $secrets = new ServiceClientSecrets();
        $first = $secrets->rotate($clientId);
        $second = $secrets->rotate($clientId);

        $this->assertNotSame(200, $this->tokenStatus($clientId, self::SECRET));
        $this->assertSame(200, $this->tokenStatus($clientId, $first['secret']));
        $this->assertSame(200, $this->tokenStatus($clientId, $second['secret']));
    }

    public function testOnlyAServiceClientsSecretIsRotated(): void
    {
        $clientId = self::PREFIX . 'agent';
        $this->db()->execute(
            'INSERT INTO oauth_clients (id, client_id, client_secret, redirect_uri, grant_types, scope, user_id, kind)
             VALUES (?, ?, ?, \'\', \'client_credentials\', NULL, ?, ?)',
            [$clientId, $clientId, password_hash(self::SECRET, PASSWORD_DEFAULT), self::ADMIN_ID, ClientKind::AGENT]
        );

        $this->expectException(\Gaia\Exception\Permission::class);
        $this->expectExceptionMessage(ServiceClientSecrets::NOT_SERVICE_ERROR);

        (new ServiceClientSecrets())->rotate($clientId);
    }

    public function testAServiceMayRotateOnlyItsOwnRecord(): void
    {
        $own = $this->createServiceClient('own');
        $other = $this->createServiceClient('other');
        $acl = $this->serviceAcl($own->clientId, 'serviceclient.rotate');

        $acl->authorizeAction('serviceclient.update', null, 'Serviceclient');
        $acl->assertWriteInScope('Serviceclient', ['rotateSecret' => true], $own->id);

        $this->expectException(UnAuthorized::class);
        $acl->assertWriteInScope('Serviceclient', ['rotateSecret' => true], $other->id);
    }

    public function testAServiceMayWriteOnlyRotateSecret(): void
    {
        $own = $this->createServiceClient('fields');
        $acl = $this->serviceAcl($own->clientId, 'serviceclient.rotate');

        $written = $acl->authorizeWritableFields(
            'Serviceclient',
            ['id' => $own->id, 'rotateSecret' => true, 'status' => 'disabled', 'scopes' => '["*.*"]'],
            'update'
        );

        $this->assertArrayHasKey('rotateSecret', $written);
        $this->assertArrayNotHasKey('status', $written);
        $this->assertArrayNotHasKey('scopes', $written);
    }

    public function testRotationNeedsTheRotateScope(): void
    {
        $own = $this->createServiceClient('scope');

        foreach (['*.*', 'serviceclient.*', 'serviceclient.update', 'agent.read'] as $scope) {
            $this->assertFalse(
                $this->serviceAcl($own->clientId, $scope)->isModelActionAllowed('Serviceclient', 'update'),
                $scope
            );
        }
    }

    public function testEveryKindDefaultsToTheRotateScope(): void
    {
        foreach (['runtime', 'hermes'] as $kind) {
            $client = $this->createServiceClient('kind-' . $kind, $kind);
            $this->assertContains('serviceclient.rotate', json_decode($client->scopes, true), $kind);
        }
    }

    /**
     * @param  string $clientId
     * @param  string $scope
     * @return ServiceAcl
     */
    private function serviceAcl($clientId, $scope)
    {
        $acl = new ServiceAcl(\Phalcon\Di::getDefault(), 'runtime', null, $clientId);
        $acl->setTokenScope(new TokenScope($scope));

        return $acl;
    }

    /**
     * A service client with the test secret.
     *
     * @param  string $suffix
     * @param  string $kind
     * @return Serviceclient
     */
    private function createServiceClient($suffix, $kind = 'runtime')
    {
        $clientId = self::PREFIX . $suffix;
        $this->db()->execute(
            'INSERT INTO oauth_clients (id, client_id, client_secret, redirect_uri, grant_types, scope, user_id, kind)
             VALUES (?, ?, ?, \'\', \'client_credentials\', NULL, NULL, ?)',
            [$clientId, $clientId, password_hash(self::SECRET, PASSWORD_DEFAULT), ClientKind::SERVICE]
        );

        $client = new Serviceclient();
        $client->id = self::PREFIX . bin2hex(random_bytes(8));
        $client->name = 'Unit ' . $clientId;
        $client->kind = $kind;
        $client->clientId = $clientId;
        $this->assertTrue($client->save(), implode(' ', array_map(function ($message) {
            return $message->getMessage();
        }, (array) $client->getMessages())));

        return Serviceclient::findFirstById($client->id);
    }

    /**
     * The HTTP status of a client_credentials token request, 401 when the
     * grant rejects the client.
     *
     * @param  string $clientId
     * @param  string $secret
     * @return int
     */
    private function tokenStatus($clientId, $secret)
    {
        $storage = new Pdo($this->db()->getInternalHandler(), ['user_table' => 'users']);
        $server = new \OAuth2\Server($storage, ['access_lifetime' => 3600]);
        $server->addGrantType(new ClientCredentialsGrant($storage));

        $body = ['grant_type' => 'client_credentials', 'client_id' => $clientId, 'client_secret' => $secret];
        try {
            return (int) $server->handleTokenRequest(
                new \OAuth2\Request([], $body, [], [], [], ['REQUEST_METHOD' => 'POST'])
            )->getStatusCode();
        } catch (UnAuthorized $e) {
            return 401;
        }
    }

    /**
     * @return \Phalcon\Db\Adapter\AdapterInterface
     */
    private function db()
    {
        return \Phalcon\Di::getDefault()->get('db');
    }

    private static function purge()
    {
        $db = \Phalcon\Di::getDefault()->get('db');
        $db->execute("DELETE FROM oauth_access_tokens WHERE client_id LIKE 'unit-rot-%'");
        $db->execute("DELETE FROM serviceclients WHERE \"clientId\" LIKE 'unit-rot-%'");
        $db->execute("DELETE FROM oauth_clients WHERE client_id LIKE 'unit-rot-%'");
    }
}
