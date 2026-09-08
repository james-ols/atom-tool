<?php
/**
 * Main dashboard.
 *
 * Expected variables:
 *   string $username        Logged-in username.
 *   string $customerCode    Customer code (e.g. "gb166", "dev").
 *   string $engineVersion   Engine version string.
 */
declare(strict_types=1);

/** @var string $username */
/** @var string $customerCode */
/** @var string $engineVersion */

$title = 'Dashboard';
$bodyClass = 'dashboard-body';

$pipelines = [
    ['key' => 'description',     'icon' => 'description',     'label' => 'Description'],
    ['key' => 'accession',       'icon' => 'inventory_2',     'label' => 'Accession'],
    ['key' => 'authority',       'icon' => 'person',          'label' => 'Authority Record'],
    ['key' => 'authority_rel',   'icon' => 'hub',             'label' => 'Authority Record Relationships'],
    ['key' => 'events',          'icon' => 'event',           'label' => 'Events'],
    ['key' => 'institutions',    'icon' => 'account_balance', 'label' => 'Archival Institutions'],
];

ob_start();
?>
<div class="app-shell">

    <header class="app-topbar">
        <div class="app-topbar__brand">
            <img src="/assets/img/logo.png" alt="" class="app-topbar__logo">
            <span class="app-topbar__wordmark">
                <strong>AtoM Tool</strong>
                <span class="app-topbar__by">by Orangeleaf Systems</span>
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
            <button type="button" class="btn btn-ols">
                <span class="material-symbols-rounded">upload</span> Upload CALM file
            </button>
            <button type="button" class="btn btn-outline-secondary" disabled>
                <span class="material-symbols-rounded">play_arrow</span> Run
            </button>
            <button type="button" class="btn btn-outline-secondary" disabled>
                <span class="material-symbols-rounded">delete</span> Clear
            </button>
        </div>

        <div class="app-centre__table">
            <table class="table align-middle">
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
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            No files uploaded yet.
                        </td>
                    </tr>
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