<?php

namespace Gaia\Tests\Libraries\Utils\Http;

use Gaia\Core\MVC\Http\SubRequestRunner;
use Gaia\Tests\Libraries\Utils\Http\Fake\ProbeController;
use Phalcon\Di\FactoryDefault;
use Phalcon\Http\Request;
use Phalcon\Http\Response;
use Phalcon\Mvc\Router;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/fixtures/ProbeController.php';

/**
 * Tests the in-process sub-request runner against a fake controller and routes.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class SubRequestRunnerTest extends TestCase
{
    /**
     * @var FactoryDefault
     */
    private $di;

    /**
     * @var array outer state saved before each test
     */
    private $saved;

    /**
     * @var Request the outer request
     */
    private $outerRequest;

    /**
     * @var Response the outer response
     */
    private $outerResponse;

    protected function setUp(): void
    {
        $this->saved = [
            'server' => $_SERVER,
            'get' => $_GET,
            'request' => $_REQUEST,
            'hasUser' => array_key_exists('currentUser', $GLOBALS),
            'user' => $GLOBALS['currentUser'] ?? null,
        ];

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/v1/bulk';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer outer';
        $_GET = ['outer' => '1'];
        $_REQUEST = ['outer' => '1'];
        $GLOBALS['currentUser'] = 'outer-user';

        $this->di = new FactoryDefault();
        $this->outerRequest = new Request();
        $this->outerResponse = new Response();
        $this->di->setShared('request', $this->outerRequest);
        $this->di->setShared('response', $this->outerResponse);
        ProbeController::$initializations = 0;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->saved['server'];
        $_GET = $this->saved['get'];
        $_REQUEST = $this->saved['request'];
        if ($this->saved['hasUser']) {
            $GLOBALS['currentUser'] = $this->saved['user'];
        } else {
            unset($GLOBALS['currentUser']);
        }
    }

    /**
     * A runner with routes to the probe controller only.
     *
     * @return SubRequestRunner
     */
    private function runner(): SubRequestRunner
    {
        return new SubRequestRunner($this->di, static function () {
            $router = new Router(false);
            $router->setDefaultNamespace('Gaia\\Tests\\Libraries\\Utils\\Http\\Fake');
            $router->removeExtraSlashes(true);
            foreach (['GET', 'POST', 'PATCH'] as $method) {
                $router->add('/api/v1/probe/{id}', ['controller' => 'probe', 'action' => 'echo'], $method);
                $router->add('/api/v1/probe', ['controller' => 'probe', 'action' => 'echo'], $method);
            }
            $router->addPatch('/api/v1/forbidden/{id}', ['controller' => 'probe', 'action' => 'forbidden']);
            $router->addGet('/api/v1/boom', ['controller' => 'probe', 'action' => 'boom']);
            $router->addPost('/api/v1/send', ['controller' => 'probe', 'action' => 'send']);
            return $router;
        });
    }

    /**
     * Assert the outer request's state is what setUp() installed.
     *
     * @return void
     */
    private function assertOuterStateRestored(): void
    {
        $this->assertSame($this->outerRequest, $this->di->getShared('request'));
        $this->assertSame($this->outerResponse, $this->di->getShared('response'));
        $this->assertSame('POST', $_SERVER['REQUEST_METHOD']);
        $this->assertSame('/api/v1/bulk', $_SERVER['REQUEST_URI']);
        $this->assertSame('Bearer outer', $_SERVER['HTTP_AUTHORIZATION']);
        $this->assertSame(['outer' => '1'], $_GET);
        $this->assertSame(['outer' => '1'], $_REQUEST);
        $this->assertSame('outer-user', $GLOBALS['currentUser']);
        $this->assertFalse($this->di->has('acl'));
    }

    public function testSuccessCapturesStatusAndBodyWithQueryAndBody(): void
    {
        $result = $this->runner()->run(
            'patch',
            '/api/v1/probe/abc?query=((Issue.id : 1))&page=2',
            ['data' => ['type' => 'issue', 'id' => 'abc']],
            'Bearer inner'
        );

        $this->assertSame(200, $result->status());
        $this->assertSame([
            'method' => 'PATCH',
            'uri' => '/api/v1/probe/abc?query=%28%28Issue.id+%3A+1%29%29&page=2',
            'query' => '((Issue.id : 1))',
            'page' => 2,
            'body' => ['data' => ['type' => 'issue', 'id' => 'abc']],
            'user' => 'Bearer inner',
            'oauthAuthorization' => 'Bearer inner',
            'id' => 'abc',
            'cookies' => [],
        ], $result->body());
        $this->assertGreaterThanOrEqual(0, $result->durationMs());
        $this->assertOuterStateRestored();
    }

    public function testGetWithoutBodyHasNoJsonBody(): void
    {
        $result = $this->runner()->run('GET', '/api/v1/probe', null, 'Bearer inner');

        $this->assertSame(200, $result->status());
        $this->assertFalse($result->body()['body']);
        $this->assertNull($result->body()['query']);
    }

    public function testUnknownRouteIsNotFound(): void
    {
        $result = $this->runner()->run('GET', '/api/v1/nothing/here', null, 'Bearer inner');

        $this->assertSame(404, $result->status());
        $this->assertSame('resourceNotFound', $result->body()['error']);
        $this->assertOuterStateRestored();
    }

    public function testGaiaExceptionKeepsItsStatusAndBody(): void
    {
        $result = $this->runner()->run('PATCH', '/api/v1/forbidden/abc', ['data' => []], 'Bearer inner');

        $this->assertSame(403, $result->status());
        $this->assertSame(['error' => 'Access Denied'], $result->body());
        $this->assertOuterStateRestored();
    }

    public function testFailedAuthenticationInInitializeIsReturned(): void
    {
        $result = $this->runner()->run('GET', '/api/v1/probe', null, 'Bearer denied');

        $this->assertSame(403, $result->status());
        $this->assertSame(['error' => 'Invalid Token'], $result->body());
        $this->assertOuterStateRestored();
    }

    public function testUnexpectedExceptionIsGeneric500WithoutDetails(): void
    {
        $result = $this->runner()->run('GET', '/api/v1/boom', null, 'Bearer inner');

        $this->assertSame(500, $result->status());
        $this->assertSame([
            'error' => 'internalServerError',
            'error_description' => 'An internal server error occurred',
        ], $result->body());
        $this->assertOuterStateRestored();
    }

    public function testFailureBeforeDispatchIs500AndLeavesOuterStateAlone(): void
    {
        $runner = new SubRequestRunner($this->di, static function () {
            throw new \RuntimeException('no routes');
        });

        $result = $runner->run('GET', '/api/v1/probe', null, 'Bearer inner');

        $this->assertSame(500, $result->status());
        $this->assertSame('internalServerError', $result->body()['error']);
        $this->assertOuterStateRestored();
    }

    public function testResponseIsCapturedNotSent(): void
    {
        $result = $this->runner()->run('POST', '/api/v1/send', ['x' => 1], 'Bearer inner');

        $this->assertSame(201, $result->status());
        $this->assertSame(['sent' => false], $result->body());
        $this->assertFalse($this->outerResponse->isSent());
        $this->assertEmpty($this->outerResponse->getContent());
        $this->assertNull($this->outerResponse->getStatusCode());
    }

    public function testConsecutiveCallsAuthenticateFreshAndDoNotLeakState(): void
    {
        $runner = $this->runner();

        $first = $runner->run('GET', '/api/v1/probe?query=a', null, 'Bearer first');
        $denied = $runner->run('GET', '/api/v1/probe', null, 'Bearer denied');
        $third = $runner->run('POST', '/api/v1/probe', ['n' => 3], 'Bearer third');

        $this->assertSame(3, ProbeController::$initializations);
        $this->assertSame('Bearer first', $first->body()['user']);
        $this->assertSame(403, $denied->status());
        $this->assertSame(200, $third->status());
        $this->assertSame('Bearer third', $third->body()['user']);
        $this->assertNull($third->body()['query']);
        $this->assertSame(['n' => 3], $third->body()['body']);
        $this->assertFalse($this->di->has(ProbeController::class));
        $this->assertOuterStateRestored();
    }

    public function testOuterControllerInstanceIsRestoredAfterASubCallToItsClass(): void
    {
        $outerController = new ProbeController();
        $this->di->setShared(ProbeController::class, $outerController);

        $result = $this->runner()->run('GET', '/api/v1/probe', null, 'Bearer inner');

        $this->assertSame(200, $result->status());
        $this->assertSame(1, ProbeController::$initializations);
        $this->assertSame($outerController, $this->di->getShared(ProbeController::class));
        $this->assertOuterStateRestored();
    }

    public function testOuterCookiesAreHiddenFromTheSubCallAndRestored(): void
    {
        $_COOKIE = ['outerSession' => 'secret'];
        try {
            $result = $this->runner()->run('GET', '/api/v1/probe', null, 'Bearer inner');

            $this->assertSame(200, $result->status());
            $this->assertSame([], $result->body()['cookies']);
            $this->assertSame(['outerSession' => 'secret'], $_COOKIE);
        } finally {
            $_COOKIE = [];
        }
    }
}
