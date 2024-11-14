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
     * This test verifies that the remember me functionality sets the correct expiration time.
     *
     * @return void
     */
    public function testSetRememberMe(): void
    {
        $this->mockOAuthRequest(true);
        $this->mockOAuthServer();
        $this->getAccessToken();
        $user = \Gaia\MVC\Models\User::findFirstByUsername('testUserOAuth');
        $this->assertGreaterThan(gmdate('Y-m-d H:i:s'), $user->rememberMe);
    }

    /**
     * This test verifies that the remember me functionality expires correctly.
     *
     * @return void
     */
    public function testRememberMeExpiry(): void
    {
        $this->mockOAuthRequest(false);
        $this->mockOAuthServer();
        $this->getAccessToken();
        $user = \Gaia\MVC\Models\User::findFirstByUsername('testUserOAuth');
        $this->assertLessThan(gmdate('Y-m-d H:i:s'), $user->rememberMe);
    }

    /**
     * This test verifies that the session validity is set correctly.
     *
     * @return void
     */
    public function testSessionValidity(): void
    {
        $this->mockOAuthRequest(false);
        $this->mockOAuthServer();
        $response = $this->getAccessToken();

        $user = \Gaia\MVC\Models\User::findFirstByUsername('testUserOAuth');

        $config = new \Phalcon\Config(['sessionTimeout' => '1']);

        $tokenControllerReflection = new \ReflectionClass($this->controller);
        $sessionExpirationReflection = $tokenControllerReflection->getMethod('setSessionExpiration');
        $sessionExpirationReflection->setAccessible(true);
        $sessionExpirationReflection->invokeArgs($this->controller, [$user, $config]);

        $this->assertGreaterThan(gmdate('Y-m-d H:i:s'), $user->sessionExpires);
    }

    /**
     * Test the session expiry functionality.
     *
     * This method tests the session expiry functionality by mocking the OAuth request,
     * setting a custom session timeout, and attempting to refresh the access token.
     * If the session is expired, an exception is expected to be thrown.
     *
     * @return void
     * @throws \Gaia\Exception\UnAuthorized If the session is expired
     */
    public function testSessionExpiry()
    {
        $this->mockOAuthRequest(false);
        $this->mockOAuthServer();
        $response = $this->getAccessToken();
        $refreshToken = (json_decode($response->getContent()))->refresh_token;

        // Get user
        $user = \Gaia\MVC\Models\User::findFirstByUsername('testUserOAuth');

        // Custom config for setting session timeout
        $config = new \Phalcon\Config(['sessionTimeout' => '-1']);

        // Create token reflection class and set session timeout expiry to future
        $tokenControllerReflection = new \ReflectionClass($this->controller);
        $sessionExpirationReflection = $tokenControllerReflection->getMethod('setSessionExpiration');
        $sessionExpirationReflection->setAccessible(true);
        $sessionExpirationReflection->invokeArgs(
            $this->controller, [
            $user,
            $config
            ]
        );

        // save user
        $user->save();

        // Request a refresh token, if session is expired then user will be prompted with exception
        $this->expectException(\Gaia\Exception\UnAuthorized::class);
        $this->expectExceptionMessage('Your session is expired');
        $this->getRefreshToken($refreshToken);
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
     * @param  bool $rememberMe Flag indicating whether to remember the user or not.
     * @return void
     */
    protected function mockOAuthRequest($rememberMe = false)
    {
        $request = new Request();
        $request->request = [
            'grant_type' => 'password',
            'username' => 'testUserOAuth',
            'password' => 'unit-testing',
            'client_id' => "projects4me",
            "client_secret" => "06110fb83488715ca69057f4a7cedf93",
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
        $userReflection = new \ReflectionClass(User::class);
        $instance = $userReflection->newInstanceWithoutConstructor();

        // For now just explicitly generate hash password, in future use behavior to generate hash password.
        $passwordHash = password_hash('unit-testing', PASSWORD_DEFAULT);

        $constuct = $userReflection->getMethod('__construct');
        $constuct->invoke($instance);

        $values = [
            'id' => 'test-user-oauth1',
            'username' => 'testUserOAuth',
            'name' => 'test oauth user',
            'password' => $passwordHash,
            'accountStatus' => 'Active',
            'email' => 'test@gmail.com',
            'createdUserName' => 'testUser',
            'modifiedUserName' => 'testUser',
            'createdUser' => 'test-user',
            'modifiedUser' => 'test-user'
        ];
        $userReflection->getMethod('assign')->invoke($instance, $values);
        $userReflection->getMethod('save')->invoke($instance);
    }

    /**
     * Delete the test user after the test class has finished.
     *
     * @return void
     */
    public static function tearDownAfterClass(): void
    {
        $user = User::findFirstByUsername('testUserOAuth');
        $user->delete();
    }
}
