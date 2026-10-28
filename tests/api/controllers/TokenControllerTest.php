<?php

namespace Gaia\Tests\Controller;

use OAuth2\Request;
use Phalcon\Http\Response;
use PHPUnit\Framework\TestCase;
use Gaia\MVC\REST\Controllers\TokenController;
use Gaia\Core\MVC\REST\Controllers\RestController;
use Gaia\MVC\REST\Controllers\UserController;
use Gaia\Libraries\Authorization\OAuthServer;
use Gaia\MVC\Models\User;

/**
 * This class contains unit tests for the TokenController. It tests various functionalities
 * such as token generation, token expiry, remember me functionality, and session validity.
 *
 * @author   Rana Nouman <ranamnouman@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class TokenControllerTest extends TestCase
{
    /**
     * @var Request $request The OAuth2 request object.
     */
    protected $request;

    /**
     * @var Response $response The Phalcon HTTP response object.
     */
    protected $response;

    /**
     * @var TokenController $controller The TokenController mock.
     */
    protected $controller;

    /**
     * This method is called before the first test of this test class is run.
     * It creates a test user.
     */
    public static function setUpBeforeClass(): void
    {
        self::createTestUser();
    }

    /**
     * This method is called before each test. It initializes the TokenController mock.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->controller = $this->getTokenControllerMock();
    }

    /**
     * This test verifies that a valid OAuth request returns a 200 status code.
     *
     * @return void
     */
    public function testPostActionValidRequest(): void
    {
        $this->mockOAuthRequest();
        $this->mockOAuthServer();
        $response = $this->getAccessToken();
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * This test verifies that an expired token results in an unauthorized exception.
     *
     * @return void
     */
    public function testTokenExpiry(): void
    {
        $this->mockOAuthRequest();
        $this->mockOAuthServer(['access_lifetime' => -1]);

        $response = $this->getAccessToken();
        $accessToken = (json_decode($response->getContent()))->access_token;

        list($userControllerReflection, $userMock) = $this->getUser();

        $request = new Request();
        $request->headers = ['AUTHORIZATION' => "Bearer $accessToken"];
        $userMock->method('getOAuthRequest')->willReturn($request);

        $authorize = $userControllerReflection->getMethod('authorize');
        $authorize->setAccessible(true);
        $this->expectException(\Gaia\Exception\UnAuthorized::class);
        $this->expectExceptionMessage('Invalid Token');
        $authorize->invoke($userMock);
    }

    /**
     * A sign-in without Remember Me opens a session window of `sessionTimeout`
     * days on its refresh token.
     *
     * @return void
     */
    public function testSessionValidity(): void
    {
        $refreshToken = $this->signIn(false);
        $row = $this->getRefreshTokenRow($refreshToken);

        $this->assertSame(0, (int) $row['rememberMe']);
        $this->assertSessionExpiresInDays(1, $row['sessionExpires']);
    }

    /**
     * A sign-in with Remember Me opens a session window of
     * `sessionTimeoutForRememberMe` days on its refresh token.
     *
     * @return void
     */
    public function testRememberMeSessionValidity(): void
    {
        $refreshToken = $this->signIn(true);
        $row = $this->getRefreshTokenRow($refreshToken);

        $this->assertSame(1, (int) $row['rememberMe']);
        $this->assertSessionExpiresInDays(14, $row['sessionExpires']);
    }

    /**
     * Refreshing is rejected once the session window of the token has ended.
     *
     * @return void
     * @throws \Gaia\Exception\UnAuthorized If the session is expired
     */
    public function testSessionExpiry()
    {
        $refreshToken = $this->signIn(false);
        $this->setSessionExpires($refreshToken, gmdate('Y-m-d H:i:s', time() - 60));

        $this->expectException(\Gaia\Exception\UnAuthorized::class);
        $this->expectExceptionMessage('Your session is expired');
        $this->getRefreshToken($refreshToken);
    }

    /**
     * A later sign-in without Remember Me doesn't shorten an existing Remember
     * Me session: once the later session has ended (a day on), the Remember Me
     * session still refreshes, and it stops once its own window has ended.
     *
     * @return void
     */
    public function testLaterSignInDoesNotShortenRememberMeSession(): void
    {
        $rememberMeToken = $this->signIn(true);
        $laterToken = $this->signIn(false);

        // A day on: the later session (and the user-level expiry an older
        // build would have written) has ended.
        $this->setSessionExpires($laterToken, gmdate('Y-m-d H:i:s', time() - 60));
        $this->setUserSessionExpires(gmdate('Y-m-d H:i:s', time() - 60));

        $response = $this->getRefreshToken($rememberMeToken);
        $this->assertEquals(200, $response->getStatusCode());
        $rotatedToken = (json_decode($response->getContent()))->refresh_token;

        // Fourteen days without a refresh: the Remember Me session ends too.
        $this->setSessionExpires($rotatedToken, gmdate('Y-m-d H:i:s', time() - 60));
        $this->expectException(\Gaia\Exception\UnAuthorized::class);
        $this->expectExceptionMessage('Your session is expired');
        $this->getRefreshToken($rotatedToken);
    }

    /**
     * An expired session can't borrow another session's open window, nor the
     * user's: refreshing it fails while the Remember Me session still refreshes.
     *
     * @return void
     */
    public function testExpiredTokenCannotUseAnotherValidSession(): void
    {
        $rememberMeToken = $this->signIn(true);
        $laterToken = $this->signIn(false);

        $this->setUserSessionExpires(gmdate('Y-m-d H:i:s', time() + 86400));
        $this->setSessionExpires($laterToken, gmdate('Y-m-d H:i:s', time() - 60));

        $response = $this->getRefreshToken($rememberMeToken);
        $this->assertEquals(200, $response->getStatusCode());

        $this->expectException(\Gaia\Exception\UnAuthorized::class);
        $this->expectExceptionMessage('Your session is expired');
        $this->getRefreshToken($laterToken);
    }

    /**
     * The session window slides: each refresh keeps the session's Remember Me
     * flag and opens a new full window from the time of the refresh.
     *
     * @return void
     */
    public function testRefreshSlidesSessionWindow(): void
    {
        $refreshToken = $this->signIn(true);

        // Most of the window has passed.
        $this->setSessionExpires($refreshToken, gmdate('Y-m-d H:i:s', time() + 3600));

        $response = $this->getRefreshToken($refreshToken);
        $this->assertEquals(200, $response->getStatusCode());
        $row = $this->getRefreshTokenRow((json_decode($response->getContent()))->refresh_token);

        $this->assertSame(1, (int) $row['rememberMe']);
        $this->assertSessionExpiresInDays(14, $row['sessionExpires']);
    }

    /**
     * A refresh token issued before sessions were stored per token falls back
     * to the user's session expiry.
     *
     * @return void
     */
    public function testLegacyRefreshTokenUsesUserSessionExpiry(): void
    {
        $refreshToken = $this->signIn(false);
        $this->setSessionExpires($refreshToken, null);
        $this->setUserSessionExpires(gmdate('Y-m-d H:i:s', time() - 60));

        $this->expectException(\Gaia\Exception\UnAuthorized::class);
        $this->expectExceptionMessage('Your session is expired');
        $this->getRefreshToken($refreshToken);
    }

    /**
     * A legacy Remember Me session keeps Remember Me after its first refresh.
     *
     * @return void
     */
    public function testLegacyRememberMeRefreshKeepsRememberMe(): void
    {
        $refreshToken = $this->signIn(false);
        $this->setSessionExpires($refreshToken, null);
        $this->setUserSessionExpires(gmdate('Y-m-d H:i:s', time() + 10 * 86400));

        $response = $this->getRefreshToken($refreshToken);
        $this->assertEquals(200, $response->getStatusCode());
        $row = $this->getRefreshTokenRow((json_decode($response->getContent()))->refresh_token);

        $this->assertSame(1, (int) $row['rememberMe']);
        $this->assertSessionExpiresInDays(14, $row['sessionExpires']);
    }

    /**
     * Test the incorrect password with exceeding login attempts.
     *
     * @return void
     */
    public function testIncorrectPasswordWithExceedingAttempts()
    {
        $this->mockOAuthRequest(
            false, [
            'password' => 'incorrect-password'
            ]
        );
        $this->mockOAuthServer();

        $user = \Gaia\MVC\Models\User::findFirstByEmail('test@gmail.com');

        $oauthConfig = $this->controller->config->get('oauth');
        $failedLoginAttemptsLimit = $oauthConfig['failedLoginAttemptsLimit'];
        $user->failedLoginAttempts = $failedLoginAttemptsLimit;
        $user->save();

        $this->expectException(\Gaia\Exception\ResourceLocked::class);
        $this->expectExceptionMessage('User is locked. Please try again later.');
        $response = $this->getAccessToken();
    }

    /**
     * Test the incorrect password with exceeding login attempts and exceeding lock time.
     *
     * @return void
     */
    public function testIncorrectPasswordWithExceedingAttemptsAndExceedingLockTime()
    {
        $this->mockOAuthRequest(
            false, [
            'password' => 'incorrect-password'
            ]
        );
        $this->mockOAuthServer();

        $user = \Gaia\MVC\Models\User::findFirstByEmail('test@gmail.com');

        $oauthConfig = $this->controller->config->get('oauth');
        $failedLoginAttemptsLimit = $oauthConfig['failedLoginAttemptsLimit'];
        $user->failedLoginAttempts = $failedLoginAttemptsLimit;
        $user->lastFailedAttempt = gmdate('Y-m-d H:i:s', strtotime('-2 day'));
        $user->save();

        $response = $this->getAccessToken();

        // Check if the user login attempts are reset to 0
        $oauthTestUser = \Gaia\MVC\Models\User::findFirstByEmail('test@gmail.com');
        $errorDescription = json_decode($response->getContent())->error_description;
        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals("Invalid email and password combination", $errorDescription);
        $this->assertEquals(0, $oauthTestUser->failedLoginAttempts, "User login attempts should be reset to 0");
    }

    /**
     * Inactive users must not receive a password-grant access token.
     *
     * @return void
     */
    public function testInactiveUserCannotGetAccessToken(): void
    {
        $user = User::findFirstByEmail('test@gmail.com');
        $previousStatus = $user->accountStatus;
        $user->accountStatus = 'inactive';
        $user->save();

        try {
            $this->mockOAuthRequest();
            $this->mockOAuthServer();
            $this->expectException(\Gaia\Exception\UnAuthorized::class);
            $this->expectExceptionMessage(\Gaia\Libraries\Security\AclLockoutGuard::ACCOUNT_INACTIVE_ERROR);
            $this->getAccessToken();
        } finally {
            $user = User::findFirstByEmail('test@gmail.com');
            $user->accountStatus = $previousStatus ?: 'Active';
            $user->save();
        }
    }

    /**
     * Disabled users must not receive a password-grant access token.
     *
     * @return void
     */
    public function testDisabledUserCannotGetAccessToken(): void
    {
        $user = User::findFirstByEmail('test@gmail.com');
        $previousStatus = $user->accountStatus;
        $user->accountStatus = 'disabled';
        $user->save();

        try {
            $this->mockOAuthRequest();
            $this->mockOAuthServer();
            $this->expectException(\Gaia\Exception\UnAuthorized::class);
            $this->expectExceptionMessage(\Gaia\Libraries\Security\AclLockoutGuard::ACCOUNT_INACTIVE_ERROR);
            $this->getAccessToken();
        } finally {
            $user = User::findFirstByEmail('test@gmail.com');
            $user->accountStatus = $previousStatus ?: 'Active';
            $user->save();
        }
    }

    /**
     * Invited users may still authenticate (first-login promotion path).
     *
     * @return void
     */
    public function testInvitedUserCanGetAccessToken(): void
    {
        $user = User::findFirstByEmail('test@gmail.com');
        $previousStatus = $user->accountStatus;
        $user->accountStatus = 'invited';
        $user->save();

        try {
            $this->mockOAuthRequest();
            $this->mockOAuthServer();
            $response = $this->getAccessToken();
            $this->assertEquals(200, $response->getStatusCode());
        } finally {
            $user = User::findFirstByEmail('test@gmail.com');
            $user->accountStatus = $previousStatus ?: 'Active';
            $user->save();
        }
    }

    /**
     * Refresh is rejected when the account has become inactive.
     *
     * @return void
     */
    public function testInactiveUserCannotRefreshToken(): void
    {
        $this->mockOAuthRequest(false);
        $this->mockOAuthServer();
        $response = $this->getAccessToken();
        $refreshToken = (json_decode($response->getContent()))->refresh_token;

        $user = User::findFirstByEmail('test@gmail.com');
        $previousStatus = $user->accountStatus;
        $user->accountStatus = 'inactive';
        $user->save();

        try {
            $this->expectException(\Gaia\Exception\UnAuthorized::class);
            $this->expectExceptionMessage(\Gaia\Libraries\Security\AclLockoutGuard::ACCOUNT_INACTIVE_ERROR);
            $this->getRefreshToken($refreshToken);
        } finally {
            $user = User::findFirstByEmail('test@gmail.com');
            $user->accountStatus = $previousStatus ?: 'Active';
            $user->save();
        }
    }

    /**
     * A refresh token carrying SQL is treated as data: it matches no row and
     * no token is issued.
     *
     * @return void
     */
    public function testInjectionPayloadCannotRefreshToken(): void
    {
        $this->mockOAuthRequest(false);
        $this->mockOAuthServer();
        $this->getAccessToken();

        $this->expectException(\Gaia\Exception\UnAuthorized::class);
        $this->expectExceptionMessage('Invalid Token');
        $this->getRefreshToken("' OR '1'='1");
    }

    /**
     * Bearer API auth rejects inactive users even if a token row still exists.
     *
     * @return void
     */
    public function testInactiveUserCannotAuthorizeApiRequest(): void
    {
        $this->mockOAuthRequest();
        $this->mockOAuthServer();
        $response = $this->getAccessToken();
        $accessToken = (json_decode($response->getContent()))->access_token;

        $user = User::findFirstByEmail('test@gmail.com');
        $previousStatus = $user->accountStatus;
        $user->accountStatus = 'inactive';
        $user->save();

        try {
            list($userControllerReflection, $userMock) = $this->getUser();

            $request = new Request();
            $request->headers = ['AUTHORIZATION' => "Bearer $accessToken"];

            $setUser = $userControllerReflection->getMethod('setUser');
            $setUser->setAccessible(true);
            $this->expectException(\Gaia\Exception\UnAuthorized::class);
            $this->expectExceptionMessage(\Gaia\Libraries\Security\AclLockoutGuard::ACCOUNT_INACTIVE_ERROR);
            $setUser->invoke($userMock, $request);
        } finally {
            $user = User::findFirstByEmail('test@gmail.com');
            $user->accountStatus = $previousStatus ?: 'Active';
            $user->save();
        }
    }

    /**
     * Signs the test user in with a password grant on a fresh controller.
     *
     * @param  bool $rememberMe
     * @return string The refresh token issued.
     */
    protected function signIn($rememberMe)
    {
        $this->controller = $this->getTokenControllerMock();
        $this->mockOAuthRequest($rememberMe);
        $this->mockOAuthServer();
        $response = $this->getAccessToken();
        $this->assertEquals(200, $response->getStatusCode());

        return (json_decode($response->getContent()))->refresh_token;
    }

    /**
     * Reads the stored row of a refresh token.
     *
     * @param  string $refreshToken
     * @return array
     */
    protected function getRefreshTokenRow($refreshToken)
    {
        $row = \Phalcon\Di::getDefault()->get('db')->fetchOne(
            'SELECT "rememberMe", "sessionExpires" FROM oauth_refresh_tokens WHERE refresh_token = ?',
            \Phalcon\Db\Enum::FETCH_ASSOC,
            [$refreshToken]
        );
        $this->assertNotEmpty($row, 'refresh token row not found');

        return $row;
    }

    /**
     * Sets the session expiry of a refresh token, to stand in for time passing.
     *
     * @param  string      $refreshToken
     * @param  string|null $sessionExpires
     * @return void
     */
    protected function setSessionExpires($refreshToken, $sessionExpires)
    {
        \Phalcon\Di::getDefault()->get('db')->execute(
            'UPDATE oauth_refresh_tokens SET "sessionExpires" = ? WHERE refresh_token = ?',
            [$sessionExpires, $refreshToken]
        );
    }

    /**
     * Sets the user-level session expiry of the test user.
     *
     * @param  string|null $sessionExpires
     * @return void
     */
    protected function setUserSessionExpires($sessionExpires)
    {
        \Phalcon\Di::getDefault()->get('db')->execute(
            'UPDATE users SET "sessionExpires" = ? WHERE email = ?',
            [$sessionExpires, 'test@gmail.com']
        );
    }

    /**
     * Asserts that a session expiry is about `$days` days from now.
     *
     * @param  int    $days
     * @param  string $sessionExpires UTC, `Y-m-d H:i:s`.
     * @return void
     */
    protected function assertSessionExpiresInDays($days, $sessionExpires)
    {
        $expected = time() + $days * 86400;
        $actual = strtotime(substr((string) $sessionExpires, 0, 19) . ' UTC');

        $this->assertLessThan(120, abs($actual - $expected), "sessionExpires $sessionExpires is not $days days from now");
    }

    /**
     * Get the access token.
     *
     * This method sends a POST request to the controller's postAction() method
     * to retrieve the access token.
     *
     * @return Response The response from the postAction() method.
     */
    protected function getAccessToken()
    {
        $response = $this->controller->postAction();
        return $response;
    }

    /**
     * Get the refresh token.
     *
     * This method sets up the necessary mocks and sends a POST request to the
     * controller's postAction() method to retrieve the refresh token.
     *
     * @param  String $refreshToken The refresh token to be used for the request.
     * @return Response The response from the postAction() method.
     */
    protected function getRefreshToken($refreshToken)
    {
        $this->controller = $this->getTokenControllerMock();
        $this->mockOAuthRequestRefreshTokenGrant($refreshToken);
        $this->mockOAuthServer();
        $response = $this->controller->postAction();
        return $response;
    }

    /**
     * Get the user controller reflection and mock object.
     *
     * @return array An array containing the user controller reflection and mock object.
     */
    public function getUser()
    {
        $userMock = $this->getMockBuilder(UserController::class)
            ->onlyMethods(['getOAuthRequest'])->getMock();
        $userControllerReflection = new \ReflectionClass($userMock);

        // Set response property
        $response = $userControllerReflection->getProperty('response');
        $response->setAccessible(true);
        $response->setValue($userMock, new \Phalcon\Http\Response());

        return [$userControllerReflection, $userMock];
    }

    /**
     * Mocks an OAuth request with the refresh token grant.
     *
     * @param  string $refreshToken The refresh token.
     * @param  bool   $rememberMe   Whether to remember the user or not.
     * @return void
     */
    protected function mockOAuthRequestRefreshTokenGrant($refreshToken, $rememberMe = false)
    {
        $request = new Request();
        $request->request = [
            'grant_type' => 'refresh_token',
            'client_id' => "projects4me",
            "client_secret" => "06110fb83488715ca69057f4a7cedf93",
            'refresh_token' => "$refreshToken",
            "remember_me" => $rememberMe
        ];
        $request->server = [
            'REQUEST_METHOD' => 'POST'
        ];
        $this->request = $request;

        $this->controller->method('getOAuthRequest')
            ->willReturn($request);
    }

    /**
     * Mocks the OAuth request with the given rememberMe flag.
     *
     * @param  bool  $rememberMe    Flag indicating whether to remember the user or not.
     * @param  array $requestConfig Additional configuration for the request.
     * @return void
     */
    protected function mockOAuthRequest($rememberMe = false, $requestConfig = [])
    {
        $request = new Request();

        $client = \Gaia\MVC\Models\Oauthclient::findFirst(
            [
            'conditions' => 'client_id = :client_id:',
            'bind' => [
                'client_id' => 'projects4me'
            ]
            ]
        );

        // Retrieve the OAuth client
        if ($client) {
            $request->request = array_merge(
                [
                'grant_type' => 'password',
                'email' => 'test@gmail.com',
                'password' => 'unit-testing',
                'client_id' => $client->client_id,
                "client_secret" => $client->client_secret,
                "remember_me" => $rememberMe
                ], $requestConfig
            );
        } else {
            throw new \Exception('Client not found');
        }

        $request->server = [
            'REQUEST_METHOD' => 'POST'
        ];
        $this->request = $request;

        $this->controller->method('getOAuthRequest')
            ->willReturn($request);
    }

    /**
     * Mocks the OAuth server with the given configuration.
     *
     * @param  array $config Configuration options for the OAuth server.
     * @return void
     */
    protected function mockOAuthServer($config = [])
    {
        $oAuthServer = new OAuthServer($this->request, $config);
        $this->controller->method('getOAuthServer')->willReturn($oAuthServer);
    }

    /**
     * Returns a mock instance of the TokenController class with the necessary dependencies mocked.
     *
     * @return TokenController The mock instance of TokenController.
     */
    protected function getTokenControllerMock()
    {
        $tokenMock = $this->getMockBuilder(TokenController::class)
            ->onlyMethods(['getOAuthRequest', 'getOAuthServer'])->getMock();
        $tokenControllerReflection = new \ReflectionClass($tokenMock);
        $response = $tokenControllerReflection->getProperty('response');
        $response->setAccessible(true);
        $response->setValue($tokenMock, new \Phalcon\Http\Response());
        return $tokenMock;
    }

    /**
     * Creates a test user for unit testing purposes.
     *
     * @return void
     */
    private static function createTestUser()
    {
        global $currentUser;

        $db = \Phalcon\Di::getDefault()->get('db');
        $passwordHash = password_hash('unit-testing', PASSWORD_DEFAULT);
        $existing = $db->fetchOne(
            "SELECT id FROM users WHERE id = 'test-user-oauth1' OR email = 'test@gmail.com' LIMIT 1"
        );

        if ($existing) {
            $db->execute(
                "UPDATE users
                 SET deleted = 0,
                     \"accountStatus\" = 'Active',
                     email = 'test@gmail.com',
                     password = ?,
                     \"failedLoginAttempts\" = 0,
                     \"sessionExpires\" = NULL
                 WHERE id = ?",
                [$passwordHash, $existing['id']]
            );
        } else {
            $db->execute(
                "INSERT INTO users (
                    id, password, email, name, deleted,
                    \"createdUser\", \"modifiedUser\", \"createdUserName\", \"modifiedUserName\",
                    \"accountStatus\", \"failedLoginAttempts\"
                 ) VALUES (
                    'test-user-oauth1', ?, 'test@gmail.com', 'test oauth user', 0,
                    'test-user-oauth1', 'test-user-oauth1', 'testUser', 'testUser',
                    'Active', 0
                 )",
                [$passwordHash]
            );
        }

        $currentUser = User::findFirstByEmail('test@gmail.com');
    }

    /**
     * Delete the test user after the test class has finished.
     *
     * @return void
     */
    public static function tearDownAfterClass(): void
    {
        $user = User::findFirstByEmail('test@gmail.com');
        if ($user) {
            $user->delete();
        }
    }
}
