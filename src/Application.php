<?php
declare(strict_types=1);

namespace AtomTool;

use AtomTool\Auth\Session;
use AtomTool\Http\Request;
use AtomTool\Http\Response;
use AtomTool\Http\Router;
use AtomTool\Mapping\Mapping;
use AtomTool\Parser\CalmStreamParser;
use AtomTool\Report\Coverage;
use AtomTool\Report\MappingDiagram;
use AtomTool\Storage\LocalStorage;
use AtomTool\Storage\Manifest;
use AtomTool\Storage\Storage;
use AtomTool\Support\Uuid;

/**
 * Top-level engine facade. Builds the Router, dispatches the current Request,
 * and sends the Response.
 */
final class Application
{
    private Storage $storage;
    private Manifest $manifest;

    public function __construct(
        private readonly Config $config,
    ) {
        $this->storage = new LocalStorage($this->config->storageRoot());
        $this->manifest = new Manifest($this->storage);
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

            // Build the dashboard rows from per-upload manifests (friendly names,
            // GUID identity, latest run's coverage), not raw directory listings.
            $uploads = [];
            foreach ($this->manifest->listUploads() as $m) {
                $latest = $this->manifest->latestRun((string) $m['uploadId']);

                // Attach the latest run's full preflight report (decoded) so the
                // Preflight panel can render it on hover/pin. Null if never run.
                $report = null;
                if ($latest !== null && !empty($latest['report'])) {
                    $reportName = (string) $latest['report'];
                    if ($this->storage->get(Storage::AREA_REPORTS, $reportName) !== null) {
                        $stream = $this->storage->openRead(Storage::AREA_REPORTS, $reportName);
                        try {
                            $json = stream_get_contents($stream);
                        } finally {
                            fclose($stream);
                        }
                        $decoded = json_decode((string) $json, true);
                        $report = is_array($decoded) ? $decoded : null;
                    }
                }

                $uploads[] = [
                    'uploadId'     => (string) $m['uploadId'],
                    'originalName' => (string) ($m['originalName'] ?? $m['uploadId']),
                    'sizeBytes'    => (int) ($m['sizeBytes'] ?? 0),
                    'latestRun'    => $latest,  // array|null
                    'report'       => $report,  // array|null (full preflight report)
                ];
            }

            return Response::html($this->render('dashboard', [
                'username'      => (string) $session->username(),
                'customerCode'  => $this->config->customerCode,
                'engineVersion' => $version,
                'uploads'       => $uploads,
            ]));
        }));

        $router->get('/download', $requireAuth(function (Request $request, Session $session): Response {
            $runId = (string) $request->queryParam('runId', '');
            if ($runId === '') {
                return Response::notFound('Missing run identifier.');
            }

            // The output is stored flat as <runId>.csv. Guard the name so a
            // crafted runId can't escape the outputs area.
            if (!preg_match('/^[0-9a-fA-F-]{36}$/', $runId)) {
                return Response::notFound('Invalid run identifier.');
            }

            $outputName = $runId . '.csv';
            if ($this->storage->get(Storage::AREA_OUTPUTS, $outputName) === null) {
                return Response::notFound('Output not found.');
            }

            $stream = $this->storage->openRead(Storage::AREA_OUTPUTS, $outputName);
            try {
                $csv = (string) stream_get_contents($stream);
            } finally {
                fclose($stream);
            }

            return (new Response())
                ->header('Content-Type', 'text/csv; charset=utf-8')
                ->header('Content-Disposition', 'attachment; filename="' . $outputName . '"')
                ->header('Content-Length', (string) strlen($csv))
                ->body($csv);
        }));

        $router->get('/diagram', $requireAuth(function (Request $request, Session $session): Response {
            $pipeline = (string) $request->queryParam('pipeline', '');
            if ($pipeline === '') {
                return Response::notFound('Missing pipeline.');
            }

            $mappingFile = $this->config->mappingPath();
            if (!is_file($mappingFile)) {
                return Response::notFound('No mapping file.');
            }
            /** @var array<string,mixed> $allPipelines */
            $allPipelines = require $mappingFile;

            // Some pipelines may not be defined in mapping.php yet.
            if (!isset($allPipelines[$pipeline]) || !is_array($allPipelines[$pipeline])) {
                $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1280 800" width="1280" height="800">'
                    . '<rect width="1280" height="800" fill="#ffffff"/>'
                    . '<text x="640" y="400" font-size="20" fill="#6b7280" text-anchor="middle" '
                    . 'font-family="Inter, system-ui, sans-serif">No mapping defined for this pipeline yet.</text>'
                    . '</svg>';
                return (new Response())
                    ->header('Content-Type', 'image/svg+xml; charset=utf-8')
                    ->body($svg);
            }

            $mapping = Mapping::fromCustomerMapping($allPipelines, $pipeline);
            $block = $allPipelines[$pipeline];
            /** @var array<string,string> $fields */
            $fields = is_array($block['fields'] ?? null) ? $block['fields'] : [];

            $diagram = new MappingDiagram();
            $svg = $diagram->render(
                $fields,
                ucfirst($pipeline),
                $mapping->version(),
                $mapping->versionDate(),
                $mapping->authorisedBy()
            );

            return (new Response())
                ->header('Content-Type', 'image/svg+xml; charset=utf-8')
                ->body($svg);
        }));

        $router->post('/upload', $requireAuth(function (Request $request, Session $session): Response {
            $file = $request->files['calm_file'] ?? null;
            if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                return Response::redirect('/');
            }

            $originalName = (string) ($file['name'] ?? '');
            $tmp  = (string) ($file['tmp_name'] ?? '');
            if (!is_uploaded_file($tmp)) {
                return Response::redirect('/');
            }

            if (strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION)) !== 'xml') {
                return Response::redirect('/');
            }

            // Assign a GUID identity; store bytes as uploads/<uploadId>.xml.
            $uploadId = Uuid::v4();
            $handle = fopen($tmp, 'rb');
            if ($handle === false) {
                return Response::redirect('/');
            }
            try {
                $stored = $this->storage->storeStream(Storage::AREA_UPLOADS, $uploadId . '.xml', $handle);
            } finally {
                fclose($handle);
            }

            // Write the per-upload manifest alongside the XML.
            $this->manifest->createUpload($uploadId, $originalName, $stored->sizeBytes);

            return Response::redirect('/');
        }));

        $router->post('/delete', $requireAuth(function (Request $request, Session $session): Response {
            $uploadId = (string) $request->postParam('uploadId', '');
            if ($uploadId === '') {
                return Response::redirect('/');
            }

            // Manifest-driven, exact delete: the upload, its manifest, and every
            // run's output + report. Flat keys, no pattern matching.
            $this->manifest->deleteUpload($uploadId);

            return Response::redirect('/');
        }));

        $router->post('/run', $requireAuth(function (Request $request, Session $session): Response {
            $uploadId = (string) $request->postParam('uploadId', '');
            $pipeline = (string) $request->postParam('pipeline', '');

            if ($uploadId === '' || $pipeline === '') {
                return Response::redirect('/');
            }

            $uploadManifest = $this->manifest->load($uploadId);
            if ($uploadManifest === null) {
                return Response::redirect('/');
            }
            $xmlName = $uploadId . '.xml';
            if ($this->storage->get(Storage::AREA_UPLOADS, $xmlName) === null) {
                return Response::redirect('/');
            }

            // Load the customer's mapping.php and build the Mapping for this pipeline.
            $mappingFile = $this->config->mappingPath();
            if (!is_file($mappingFile)) {
                return Response::redirect('/');
            }
            /** @var array<string,mixed> $allPipelines */
            $allPipelines = require $mappingFile;
            $mapping = Mapping::fromCustomerMapping($allPipelines, $pipeline);

            // The authoritative column order comes from the AtoM template CSV.
            $templateFile = $this->config->templatesPath() . '/atom_' . $pipeline . '.csv';
            if (!is_file($templateFile)) {
                return Response::redirect('/');
            }
            $templateHandle = fopen($templateFile, 'rb');
            if ($templateHandle === false) {
                return Response::redirect('/');
            }
            try {
                $header = fgetcsv($templateHandle, escape: '');
            } finally {
                fclose($templateHandle);
            }
            if (!is_array($header) || $header === []) {
                return Response::redirect('/');
            }
            /** @var list<string> $header */

            // Every run gets its own GUID; artefacts are flat, GUID-named.
            $runId = Uuid::v4();
            $csvName = $runId . '.csv';
            $reportName = $runId . '.preflight.txt';

            // Stream CALM -> map -> build CSV; observe coverage as we go.
            $csv = fopen('php://temp', 'r+b');
            $coverage = new Coverage();

            fputcsv($csv, $header, escape: '');

            $parser = new CalmStreamParser();
            $inStream = $this->storage->openRead(Storage::AREA_UPLOADS, $xmlName);
            try {
                foreach ($parser->parse($inStream) as $record) {
                    $coverage->observe($record);
                    $mapped = $mapping->mapRecord($record);
                    $line = [];
                    foreach ($header as $column) {
                        $line[] = $mapped[$column] ?? '';
                    }
                    fputcsv($csv, $line, escape: '');
                }
            } finally {
                fclose($inStream);
            }

            rewind($csv);
            try {
                $this->storage->storeStream(Storage::AREA_OUTPUTS, $csvName, $csv);
            } finally {
                fclose($csv);
            }

            // Preflight coverage report (JSON inside a .preflight.txt file).
            $summary = $coverage->summarise($mapping->sourceKeys());
            $ranAt = date('c');
            $report = [
                'runId'        => $runId,
                'uploadId'     => $uploadId,
                'generatedAt'  => $ranAt,
                'source'       => (string) ($uploadManifest['originalName'] ?? $uploadId),
                'pipeline'     => $pipeline,
                'output'       => $csvName,
                'records'      => $summary['records'],
                'coverage'     => [
                    'presentCount'  => $summary['presentCount'],
                    'mappedCount'   => $summary['mappedCount'],
                    'unmappedCount' => $summary['unmappedCount'],
                    'percentMapped' => $summary['percentMapped'],
                ],
                'mapped'       => $summary['mapped'],
                'unmapped'     => $summary['unmapped'],
                'mapping'      => [
                    'version'      => $mapping->version(),
                    'versionDate'  => $mapping->versionDate(),
                    'authorisedBy' => $mapping->authorisedBy(),
                ],
            ];
            $this->storage->storeString(
                Storage::AREA_REPORTS,
                $reportName,
                (string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );

            // Record the run in the upload's manifest (with a coverage summary
            // so the dashboard needn't open every report file).
            $this->manifest->addRun($uploadId, [
                'runId'    => $runId,
                'pipeline' => $pipeline,
                'ranAt'    => $ranAt,
                'output'   => $csvName,
                'report'   => $reportName,
                'coverage' => [
                    'percentMapped' => $summary['percentMapped'],
                    'unmappedCount' => $summary['unmappedCount'],
                    'records'       => $summary['records'],
                ],
            ]);

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