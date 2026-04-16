<?php

declare(strict_types=1);

namespace tests\api;

use app\model\User;
use app\model\Session;
use PHPUnit\Framework\TestCase;
use think\App;
use think\Request;

/**
 * Base class for HTTP-level endpoint tests.
 *
 * Uses ThinkPHP's Http::run() with a synthetic Request object so every test
 * exercises the full middleware + routing + controller pipeline against the
 * in-memory SQLite database created by bootstrap.php.
 *
 * No real HTTP server is required.
 */
abstract class HttpTestCase extends TestCase
{
    protected string $token = '';
    protected static App $app;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        // The bootstrap already called new App() and initialize(), so getInstance()
        // returns the same singleton — routes are registered on first dispatch.
        self::$app = App::getInstance();
    }

    // ------------------------------------------------------------------
    // Core HTTP helpers
    // ------------------------------------------------------------------

    /**
     * Dispatch a synthetic HTTP request through the full ThinkPHP pipeline.
     *
     * @param  string $method  HTTP verb (GET, POST, PUT, DELETE, …)
     * @param  string $path    Absolute path, e.g. "/api/v1/ping"
     * @param  array  $body    Will be JSON-encoded into the request body
     * @param  array  $extra   Additional headers (key => value)
     * @return array{status:int, body:array<mixed>}
     */
    protected function request(
        string $method,
        string $path,
        array  $body  = [],
        array  $extra = []
    ): array {
        $req = new Request();
        $req->setMethod(strtoupper($method));

        // Strip query string from path before setting pathinfo; inject params via withGet
        $pathParts   = explode('?', $path, 2);
        $cleanPath   = $pathParts[0];
        $queryString = $pathParts[1] ?? '';

        // setPathinfo wants the path WITHOUT a leading slash
        $req->setPathinfo(ltrim($cleanPath, '/'));

        if ($queryString !== '') {
            parse_str($queryString, $getParams);
            $req->withGet($getParams);
        }

        $headers = array_merge([
            'host'         => 'localhost',
            'content-type' => 'application/json',
            'accept'       => 'application/json',
        ], $extra);

        if ($this->token !== '') {
            $headers['authorization'] = 'Bearer ' . $this->token;
        }

        $req->withHeader($headers);

        // Set HTTP_ACCEPT in server vars so Request::isJson() returns true,
        // which causes ThinkPHP to render exceptions as JSON (not HTML).
        $req->withServer([
            'HTTP_ACCEPT'       => 'application/json',
            'HTTP_HOST'         => 'localhost',
            'REMOTE_ADDR'       => '127.0.0.1',
            'REQUEST_METHOD'    => strtoupper($method),
            'REQUEST_URI'       => $path,
        ]);

        if (!empty($body)) {
            $req->withInput(json_encode($body));
        }

        // ThinkPHP accumulates route/controller middleware into a shared queue across
        // Http::run() calls in the same process (Dispatch::doRouteAfter() appends to
        // $app->middleware->queue['route']).  Without clearing it, AuthMiddleware from
        // a previous request bleeds into the next one (e.g. login after /activities),
        // causing all authenticated tests to receive 401.
        $mwRef = new \ReflectionProperty(self::$app->middleware, 'queue');
        $queue = $mwRef->getValue(self::$app->middleware);
        $queue['route']      = [];
        $queue['controller'] = [];
        $mwRef->setValue(self::$app->middleware, $queue);

        $response = self::$app->http->run($req);
        $raw      = $response->getContent();

        return [
            'status' => $response->getCode(),
            'body'   => json_decode($raw, true) ?? [],
        ];
    }

    protected function get(string $path, array $extra = []): array
    {
        return $this->request('GET', $path, [], $extra);
    }

    protected function post(string $path, array $body = [], array $extra = []): array
    {
        return $this->request('POST', $path, $body, $extra);
    }

    protected function put(string $path, array $body = [], array $extra = []): array
    {
        return $this->request('PUT', $path, $body, $extra);
    }

    protected function delete(string $path, array $extra = []): array
    {
        return $this->request('DELETE', $path, [], $extra);
    }

    // ------------------------------------------------------------------
    // Authentication helpers
    // ------------------------------------------------------------------

    /**
     * Create (or reuse) an admin user and obtain a valid bearer token via
     * the login endpoint.  Sets $this->token so all subsequent requests
     * are authenticated.
     */
    protected function loginAsAdmin(string $username = 'http-test-admin'): void
    {
        $this->ensureUser($username, 'administrator');
        $result = $this->post('/api/v1/auth/login', [
            'username' => $username,
            'password' => 'HttpTest1!Pass',
        ]);
        $this->token = $result['body']['data']['access_token'] ?? '';
    }

    protected function loginAsRole(string $role, string $username = ''): void
    {
        if ($username === '') {
            $username = 'http-test-' . str_replace('_', '-', $role);
        }
        $this->ensureUser($username, $role);
        $result = $this->post('/api/v1/auth/login', [
            'username' => $username,
            'password' => 'HttpTest1!Pass',
        ]);
        $this->token = $result['body']['data']['access_token'] ?? '';
    }

    protected function logout(): void
    {
        if ($this->token !== '') {
            $this->post('/api/v1/auth/logout');
            $this->token = '';
        }
    }

    // ------------------------------------------------------------------
    // Assertion helpers
    // ------------------------------------------------------------------

    protected function assertStatus(int $expected, array $response, string $message = ''): void
    {
        $this->assertSame(
            $expected,
            $response['status'],
            $message ?: "Expected HTTP {$expected}, got {$response['status']}. Body: " . json_encode($response['body'])
        );
    }

    protected function assertSuccess(array $response): void
    {
        $this->assertTrue(
            $response['body']['success'] ?? false,
            'Expected success=true. Body: ' . json_encode($response['body'])
        );
    }

    protected function assertUnauthorized(array $response): void
    {
        $this->assertStatus(401, $response);
    }

    protected function assertForbidden(array $response): void
    {
        $this->assertStatus(403, $response);
    }

    protected function assertNotFound(array $response): void
    {
        $this->assertStatus(404, $response);
    }

    // ------------------------------------------------------------------
    // DB helpers
    // ------------------------------------------------------------------

    protected function ensureUser(string $username, string $role): User
    {
        $user = User::where('username', $username)->find();
        if (!$user) {
            $user = new User();
            $user->username = $username;
        }
        $user->role   = $role;
        $user->status = 'active';
        $user->failed_attempts = 0;
        $user->locked_until    = null;
        $user->setPassword('HttpTest1!Pass');
        $user->save();
        return $user;
    }

    protected function cleanupUser(string $username): void
    {
        $user = User::where('username', $username)->find();
        if ($user) {
            Session::where('user_id', $user->id)->delete();
            $user->delete();
        }
    }

    protected function cleanupUsersLike(string $pattern): void
    {
        $users = User::where('username', 'like', $pattern)->select();
        foreach ($users as $user) {
            Session::where('user_id', $user->id)->delete();
        }
        User::where('username', 'like', $pattern)->delete();
    }
}
