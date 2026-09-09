<?php
/**
 * Main dashboard.
 *
 * Expected variables:
 *   string $username         Logged-in username.
 *   string $customerCode     Customer code (e.g. "gb166", "dev").
 *   string $engineVersion    Engine version string.
 *   list<array{
 *     uploadId:string,
 *     originalName:string,
 *     sizeBytes:int,
 *     latestRun: array<string,mixed>|null
 *   }> $uploads              Uploaded CALM files (manifest-shaped), newest first.
 */
declare(strict_types=1);

/** @var string $username */
/** @var string $customerCode */
/** @var string $engineVersion */
/** @var list<array<string,mixed>> $uploads */

$title = 'Dashboard';
$bodyClass = 'dashboard-body';

$pipelines = [
        ['key' => 'description',   'icon' => 'description',     'label' => 'Description',                    'version' => 'v1.2', 'date' => '2024-03-15', 'approver' => 'JG'],
        ['key' => 'accession',     'icon' => 'inventory_2',     'label' => 'Accession',                      'version' => 'v1.1', 'date' => '2024-03-10', 'approver' => 'JG'],
        ['key' => 'authority',     'icon' => 'person',          'label' => 'Authority Record',               'version' => 'v1.0', 'date' => '2024-03-01', 'approver' => 'JG'],
        ['key' => 'authority_rel', 'icon' => 'hub',             'label' => 'Authority Relationships',        'version' => 'v1.0', 'date' => '2024-03-01', 'approver' => 'JG'],
        ['key' => 'events',        'icon' => 'event',           'label' => 'Events',                         'version' => 'v1.0', 'date' => '2024-02-28', 'approver' => 'JG'],
        ['key' => 'institutions',  'icon' => 'account_balance', 'label' => 'Archival Institutions',          'version' => 'v1.0', 'date' => '2024-02-28', 'approver' => 'JG'],
];

$formatSize = static function (int $bytes): string {
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    $units = ['KB', 'MB', 'GB', 'TB'];
    $value = $bytes / 1024;
    $i = 0;
    while ($value >= 1024 && $i < count($units) - 1) {
        $value /= 1024;
        $i++;
    }
    return number_format($value, $value >= 10 ? 0 : 1) . ' ' . $units[$i];
};

$formatRunDate = static function (?array $run): string {
    if ($run === null || empty($run['ranAt'])) {
        return '—';
    }
    $ts = strtotime((string) $run['ranAt']);
    return $ts === false ? '—' : date('Y-m-d H:i', $ts);
};

ob_start();
?>
    <div class="app-shell">

        <header class="app-topbar">
            <div class="app-topbar__brand">
                <img src="/assets/img/logo.png" alt="" class="app-topbar__logo">
                <span class="app-topbar__wordmark">
                <strong>AtoM Tool</strong>
                <span class="app-topbar__by">by Orange Leaf Systems</span>
            </span>
            </div>
            <div class="app-topbar__user">
                <span class="app-topbar__username"><?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?></span>
                <a href="/logout" class="app-topbar__logout" title="Sign out" aria-label="Sign out">
                    <span class="material-symbols-rounded">logout</span>
                </a>
            </div>
        </header>

        <div class="app-banner" aria-hidden="true"></div>

        <nav class="app-rail" aria-label="Pipelines">
            <ul>
                <?php foreach ($pipelines as $p): ?>
                    <li>
                        <button type="button"
                                class="app-rail__icon"
                                title="<?= htmlspecialchars($p['label'], ENT_QUOTES, 'UTF-8') ?>"
                                aria-label="<?= htmlspecialchars($p['label'], ENT_QUOTES, 'UTF-8') ?>"
                                data-pipeline="<?= htmlspecialchars($p['key'], ENT_QUOTES, 'UTF-8') ?>">
                            <span class="material-symbols-rounded"><?= htmlspecialchars($p['icon'], ENT_QUOTES, 'UTF-8') ?></span>
                        </button>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>

        <main class="app-centre">
            <div class="app-centre__actions">
                <form method="post" action="/upload" enctype="multipart/form-data" class="d-inline-block">
                    <label class="btn btn-ols mb-0">
                        <span class="material-symbols-rounded">upload</span> Upload CALM file
                        <input type="file" name="calm_file" accept=".xml,application/xml,text/xml" hidden onchange="this.form.submit()">
                    </label>
                </form>

                <form method="post" action="/run" id="form-run" class="d-inline-block">
                    <input type="hidden" name="uploadId" id="run-upload-id" value="">
                    <input type="hidden" name="pipeline" id="run-pipeline" value="">
                    <button type="submit" id="btn-run" class="btn btn-outline-secondary" disabled>
                        <span class="material-symbols-rounded">play_arrow</span> Run
                    </button>
                </form>

                <form method="post" action="/delete" id="form-delete" class="d-inline-block"
                      onsubmit="return confirm('Delete the selected file and any generated output for it?');">
                    <input type="hidden" name="uploadId" id="delete-upload-id" value="">
                    <button type="submit" id="btn-clear" class="btn btn-outline-secondary" disabled>
                        <span class="material-symbols-rounded">delete</span> Clear
                    </button>
                </form>
            </div>

            <div class="app-centre__table">
                <table class="table align-middle" id="files-table">
                    <thead>
                    <tr>
                        <th scope="col" class="col-select"></th>
                        <th scope="col">Filename</th>
                        <th scope="col">Size</th>
                        <th scope="col">Last run</th>
                        <th scope="col">Coverage</th>
                        <th scope="col">Pipeline</th>
                        <th scope="col" class="col-icon"></th>
                        <th scope="col" class="col-icon"></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($uploads === []): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                No files uploaded yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($uploads as $u): ?>
                            <?php
                            $latest = is_array($u['latestRun'] ?? null) ? $u['latestRun'] : null;
                            $pct = $latest !== null && isset($latest['coverage']['percentMapped'])
                                    ? (float) $latest['coverage']['percentMapped']
                                    : null;
                            ?>
                            <tr data-upload-id="<?= htmlspecialchars((string) $u['uploadId'], ENT_QUOTES, 'UTF-8') ?>">
                                <td class="col-select">
                                    <input type="radio" name="selected_file"
                                           value="<?= htmlspecialchars((string) $u['uploadId'], ENT_QUOTES, 'UTF-8') ?>">
                                </td>
                                <td class="filename"><?= htmlspecialchars((string) $u['originalName'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="size text-muted"><?= htmlspecialchars($formatSize((int) $u['sizeBytes']), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="run-date text-muted"><?= htmlspecialchars($formatRunDate($latest), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="coverage text-muted">
                                    <?= $pct === null ? '—' : htmlspecialchars(number_format($pct, 1) . '%', ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td class="pipeline">
                                    <div class="pipeline-selector">
                                        <button type="button" class="pipeline-selector__trigger form-select">
                                            <span class="pipeline-selector__placeholder">Select a pipeline…</span>
                                        </button>
                                        <div class="pipeline-selector__dropdown" hidden>
                                            <?php foreach ($pipelines as $p): ?>
                                                <button type="button"
                                                        class="pipeline-selector__option"
                                                        data-value="<?= htmlspecialchars($p['key'], ENT_QUOTES, 'UTF-8') ?>">
                                                    <div class="pipeline-selector__label">
                                                        <?= htmlspecialchars($p['label'], ENT_QUOTES, 'UTF-8') ?>
                                                    </div>
                                                    <div class="pipeline-selector__meta">
                                                        <?= htmlspecialchars($p['version'], ENT_QUOTES, 'UTF-8') ?>
                                                        &middot;
                                                        <?= htmlspecialchars($p['date'], ENT_QUOTES, 'UTF-8') ?>
                                                        &middot;
                                                        Approved by <?= htmlspecialchars($p['approver'], ENT_QUOTES, 'UTF-8') ?>
                                                    </div>
                                                </button>
                                            <?php endforeach; ?>
                                        </div>
                                        <input type="hidden" class="pipeline-select" value="">
                                    </div>
                                </td>
                                <td class="col-icon text-center">
                                    <span class="material-symbols-rounded text-muted" title="Download (available after transformation)">download</span>
                                </td>
                                <td class="col-icon text-center">
                                    <span class="material-symbols-rounded text-muted" title="Preflight (available after transformation)">flight</span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <footer class="app-centre__footer">
                <small class="text-muted">
                    Customer: <strong><?= htmlspecialchars($customerCode, ENT_QUOTES, 'UTF-8') ?></strong>
                    &middot; Engine <?= htmlspecialchars($engineVersion, ENT_QUOTES, 'UTF-8') ?>
                </small>
            </footer>
        </main>

        <aside class="app-preflight" aria-label="Preflight report">
            <header class="app-preflight__header">
                <span class="material-symbols-rounded">flight</span>
                <span>Preflight</span>
            </header>
            <div class="app-preflight__body app-preflight__body--empty">
                Select a file to see coverage and unmapped fields.
            </div>
        </aside>

    </div>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';