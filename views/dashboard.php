<?php
/**
 * Main dashboard.
 *
 * Expected variables:
 *   string $username         Logged-in username.
 *   string $customerCode     Customer code (e.g. "gb166", "dev").
 *   string $engineVersion    Engine version string.
 *   list<StoredFile> $files  Uploaded CALM files, most-recent first.
 */
declare(strict_types=1);

use AtomTool\Storage\StoredFile;

/** @var string $username */
/** @var string $customerCode */
/** @var string $engineVersion */
/** @var list<StoredFile> $files */

$title = 'Dashboard';
$bodyClass = 'dashboard-body';

$pipelines = [
    ['key' => 'description',   'icon' => 'description',     'label' => 'Description'],
    ['key' => 'accession',     'icon' => 'inventory_2',     'label' => 'Accession'],
    ['key' => 'authority',     'icon' => 'person',          'label' => 'Authority Record'],
    ['key' => 'authority_rel', 'icon' => 'hub',             'label' => 'Authority Record Relationships'],
    ['key' => 'events',        'icon' => 'event',           'label' => 'Events'],
    ['key' => 'institutions',  'icon' => 'account_balance', 'label' => 'Archival Institutions'],
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
                <input type="hidden" name="name" id="run-name" value="">
                <input type="hidden" name="pipeline" id="run-pipeline" value="">
                <button type="submit" id="btn-run" class="btn btn-outline-secondary" disabled>
                    <span class="material-symbols-rounded">play_arrow</span> Run
                </button>
            </form>

            <form method="post" action="/delete" id="form-delete" class="d-inline-block"
                  onsubmit="return confirm('Delete the selected file and any generated output for it?');">
                <input type="hidden" name="name" id="delete-name" value="">
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
                        <th scope="col">Run date</th>
                        <th scope="col">Pipeline</th>
                        <th scope="col" class="col-icon"></th>
                        <th scope="col" class="col-icon"></th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($files === []): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            No files uploaded yet.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($files as $f): ?>
                        <tr data-filename="<?= htmlspecialchars($f->name, ENT_QUOTES, 'UTF-8') ?>">
                            <td class="col-select">
                                <input type="radio" name="selected_file"
                                       value="<?= htmlspecialchars($f->name, ENT_QUOTES, 'UTF-8') ?>">
                            </td>
                            <td class="filename"><?= htmlspecialchars($f->name, ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="size text-muted"><?= htmlspecialchars($formatSize($f->sizeBytes), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="run-date text-muted">—</td>
                            <td class="pipeline">
                                <select class="form-select pipeline-select">
                                    <option value="">Select a pipeline…</option>
                                    <?php foreach ($pipelines as $p): ?>
                                        <option value="<?= htmlspecialchars($p['key'], ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars($p['label'], ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
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
            Hover a row's preflight icon to see coverage and validation.
        </div>
    </aside>

</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';