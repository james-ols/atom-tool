<?php
declare(strict_types=1);

namespace AtomTool;

use AtomTool\Auth\Session;
use AtomTool\Http\Request;
use AtomTool\Http\Response;
use AtomTool\Http\Router;
use AtomTool\Storage\LocalStorage;
use AtomTool\Storage\Storage;

/**
 * Top-level engine facade. Builds the Router, dispatches the current Request,
 * and sends the Response.
 */
final class Application
{
    private Storage $storage;

    public function __construct(
        private readonly Config $config,
    ) {
        $this->storage = new LocalStorage($this->config->storageRoot());
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
                'username'      => (string) $session->username(),
                'customerCode'  => $this->config->customerCode,
                'engineVersion' => $version,
                'files'         => $this->storage->list(Storage::AREA_UPLOADS),
            ]));
        }));

        $router->post('/upload', $requireAuth(function (Request $request, Session $session): Response {
            $file = $request->files['calm_file'] ?? null;
            if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                return Response::redirect('/');
            }

            $name = (string) ($file['name'] ?? '');
            $tmp  = (string) ($file['tmp_name'] ?? '');
            if (!is_uploaded_file($tmp)) {
                return Response::redirect('/');
            }

            if (strtolower((string) pathinfo($name, PATHINFO_EXTENSION)) !== 'xml') {
                return Response::redirect('/');
            }

            $handle = fopen($tmp, 'rb');
            if ($handle === false) {
                return Response::redirect('/');
            }
            try {
                $this->storage->storeStream(Storage::AREA_UPLOADS, $name, $handle);
            } finally {
                fclose($handle);
            }

            return Response::redirect('/');
        }));

        $router->post('/delete', $requireAuth(function (Request $request, Session $session): Response {
            $name = (string) $request->postParam('name', '');
            if ($name === '') {
                return Response::redirect('/');
            }

            $this->storage->delete(Storage::AREA_UPLOADS, $name);

            $base = pathinfo($name, PATHINFO_FILENAME);
            foreach ($this->storage->list(Storage::AREA_OUTPUTS) as $out) {
                if (pathinfo($out->name, PATHINFO_FILENAME) === $base) {
                    $this->storage->delete(Storage::AREA_OUTPUTS, $out->name);
                }
            }
            foreach ($this->storage->list(Storage::AREA_REPORTS) as $rep) {
                if (pathinfo($rep->name, PATHINFO_FILENAME) === $base) {
                    $this->storage->delete(Storage::AREA_REPORTS, $rep->name);
                }
            }

            return Response::redirect('/');
        }));

        // Stub: real transformation lands in Step 7 (streams parser + mapping).
        // For now, verify the file exists and the pipeline is known, then redirect.
        $router->post('/run', $requireAuth(function (Request $request, Session $session): Response {
            $name = (string) $request->postParam('name', '');
            $pipeline = (string) $request->postParam('pipeline', '');

            if ($name === '' || $pipeline === '') {
                return Response::redirect('/');
            }
            if ($this->storage->get(Storage::AREA_UPLOADS, $name) === null) {
                return Response::redirect('/');
            }
            // TODO Step 7: dispatch to PipelineRegistry, stream the XML,
            // write outputs/<base>.csv and reports/<base>.json.
            return Response::redirect('/');
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