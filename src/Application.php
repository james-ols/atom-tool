<?php
declare(strict_types=1);

namespace AtomTool;

use AtomTool\Auth\Session;
use AtomTool\Http\Request;
use AtomTool\Http\Response;
use AtomTool\Http\Router;

/**
 * Top-level engine facade. Builds the Router, dispatches the current Request,
 * and sends the Response.
 */
final class Application
{
    public function __construct(
        private readonly Config $config,
    ) {
    }

    public function run(): void
    {
        $request = Request::fromGlobals();
        $session = new Session($this->config, $request);
        $router = $this->buildRouter($session);
        $response = $router->dispatch($request);
        $response->send();
    }

    public function config(): Config
    {
        return $this->config;
    }

    private function buildRouter(Session $session): Router
    {
        $router = new Router();

        $router->get('/login', function (Request $request) use ($session): Response {
            if ($session->isAuthenticated()) {
                return Response::redirect('/');
            }
            return Response::html($this->render('login', ['error' => null, 'username' => '']));
        });

        $router->post('/login', function (Request $request) use ($session): Response {
            $username = trim((string) $request->postParam('username', ''));
            $password = (string) $request->postParam('password', '');

            if ($session->attemptLogin($username, $password)) {
                return $session->issueCookie(Response::redirect('/'));
            }
            $html = $this->render('login', [
                'error' => 'Invalid username or password.',
                'username' => $username,
            ]);
            return Response::html($html, 401);
        });

        $router->get('/logout', function (Request $request) use ($session): Response {
            return $session->clearCookie(Response::redirect('/login'));
        });

        // Everything below this line requires auth.
        $requireAuth = function (callable $handler) use ($session): callable {
            return function (Request $request) use ($handler, $session): Response {
                if (!$session->isAuthenticated()) {
                    return Response::redirect('/login');
                }
                return $handler($request, $session);
            };
        };

        $router->get('/', $requireAuth(function (Request $request, Session $session): Response {
            $version = trim((string) @file_get_contents($this->config->engineRoot() . '/VERSION'));
            return Response::html($this->render('dashboard', [
                'username' => (string) $session->username(),
                'customerCode' => $this->config->customerCode,
                'engineVersion' => $version,
            ]));
        }));

        return $router;
    }

    /**
     * Render a view file from the engine's views/ directory with the given
     * variables in scope, returning the produced HTML.
     *
     * @param array<string, mixed> $vars
     */
    private function render(string $view, array $vars = []): string
    {
        $path = $this->config->engineRoot() . '/views/' . $view . '.php';
        extract($vars, EXTR_SKIP);
        ob_start();
        require $path;
        return (string) ob_get_clean();
    }
}