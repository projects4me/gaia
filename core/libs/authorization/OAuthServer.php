<?php

namespace Gaia\Libraries\Authorization;

use OAuth2;
use Phalcon\Di;
use Gaia\MVC\Models\Oauthrefreshtoken;
use Gaia\MVC\Models\User;
use Gaia\Exception\UnAuthorized;

/**
 * OAuthServer class to handle OAuth2 server setup and token management.
 *
 * @author   Rana Nouman <ranamnouman@gmail.com>
 * @package  Foundation
 * @category Libraries
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class OAuthServer
{
    /**
     * The oauth server object.
     *
     * @var OAuth2\Server
     */
    private $server;

    /**
     * The PDO storage object.
     *
     * @var OAuth2\Storage\StorageInterface
     */
    private $storage;

    /**
     * A map of grant types to handler functions.
     *
     * @var array
     */
    private $grantTypeMap = [
        'password'        => 'addPasswordGrant',
        'refresh_token'   => 'addRefreshTokenGrant',
    ];

    /**
     * OAuth2 request object.
     *
     * @var \OAuth2\Request
     */
    private $request;

    /**
     * OAuthServer constructor.
     *
     * @param \OAuth2\Request $request  OAuth request object.
     * @param array                                             $config The configuration settings for the OAuth server.
     */
    public function __construct($request, $config = [])
    {
        $pdo = \Phalcon\Di::getDefault()->get('db')->getInternalHandler();
        $config = array_merge(
            [
            'access_lifetime' => 3600,
            ], $config
        );


        $this->storage = new \Gaia\Libraries\Oauth\Storage\Pdo($pdo, ['user_table' => 'users']);
        $this->server = new OAuth2\Server($this->storage, $config);
        $this->request = $request;
    }

    /**
     * Adds the User Credentials grant type to the OAuth2 server.
     *
     * bshaffer's UserCredentials grant reads the "username" field from the
     * request. We copy the incoming "email" value into "username" here so the
     * library can forward it to our getUser() override in the custom Pdo
     * storage, which then performs the lookup by email column.
     *
     * @method addPasswordGrant
     */
    protected function addPasswordGrant()
    {
        if (!empty($this->request->request['email'])) {
            $this->request->request['username'] = $this->request->request['email'];
        }

        $this->server->addGrantType(new OAuth2\GrantType\UserCredentials($this->storage));
    }

    /**
     * Adds the Refresh Token grant type if the token is valid.
     *
     * @method addRefreshTokenGrant
     * @throws UnAuthorized if the refresh token is invalid.
     */
    protected function addRefreshTokenGrant()
    {
        $refreshToken = $this->request->request('refresh_token');
        $refreshTokenModel = Oauthrefreshtoken::findFirst("refresh_token = '$refreshToken'");

        if ($refreshTokenModel) {
            $email = $refreshTokenModel->user_id;
            $user = User::findFirst("email = '$email'");
            $currentDate = gmdate('Y-m-d H:i:s');

            // If session is expired, then refresh token will not be issued
            if ($user->sessionExpires > $currentDate) {
                $this->server->addGrantType(
                    new OAuth2\GrantType\RefreshToken(
                        $this->storage,
                        ['always_issue_new_refresh_token' => true,
                        ]
                    )
                );
            } else {
                throw new UnAuthorized("Your session is expired");
            }
        } else {
            throw new UnAuthorized("Invalid Token");
        }
    }

    /**
     * Handles the specific grant type based on the request.
     *
     * @method handleGrantType
     */
    public function addGrantType()
    {
        $grantType = $this->request->request('grant_type');

        if (isset($this->grantTypeMap[$grantType])) {
            $handlerMethod = $this->grantTypeMap[$grantType];
            if (method_exists($this, $handlerMethod)) {
                return $this->$handlerMethod();
            }
        }

        throw new UnAuthorized("Invalid or unsupported grant type: $grantType");
    }

    /**
     * Returns bshaffer's OAuth server object.
     *
     * @return \OAuth2\Server
     */
    public function getServer()
    {
        return $this->server;
    }
}
