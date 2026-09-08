<?php
/**
 * Login form. Renders inside layout.php.
 *
 * Expected variables:
 *   ?string $error       Error message to display, or null.
 *   string  $username    Previously submitted username (for redisplay).
 */
declare(strict_types=1);

/** @var ?string $error */
/** @var string $username */

$title = 'Sign in';
$bodyClass = 'login-body';

ob_start();
?>
<main class="login-shell">
    <div class="login-card">
        <img src="/assets/img/logo.png" alt="Orangeleaf Systems" class="login-logo">
        <h1>AtoM Tool</h1>
        <p class="subtitle">by Orangeleaf Systems</p>

        <?php if ($error !== null): ?>
            <div class="login-error" role="alert">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form method="post" action="/login" novalidate>
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" class="form-control" id="username" name="username"
                       value="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>"
                       autocomplete="username" autofocus required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password"
                       autocomplete="current-password" required>
            </div>
            <button type="submit" class="btn btn-ols w-100">Sign in</button>
        </form>
    </div>
</main>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';