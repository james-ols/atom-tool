<?php
/**
 * Shared page frame.
 *
 * Expected variables:
 *   string $title   Page title (plain text, will be escaped here).
 *   string $content Body content (HTML — the view is responsible for escaping).
 *   string $bodyClass Optional body class for page-specific styling.
 */
declare(strict_types=1);

/** @var string $title */
/** @var string $content */
/** @var string $bodyClass */
$bodyClass = $bodyClass ?? '';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> — AtoM Tool</title>

    <link rel="icon" type="image/png" href="/assets/img/logo.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" rel="stylesheet">

    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" rel="stylesheet">

    <link href="/assets/css/app.css" rel="stylesheet">
</head>
<body class="<?= htmlspecialchars($bodyClass, ENT_QUOTES, 'UTF-8') ?>">

<?= $content ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"
        integrity="sha512-7Pi/otdlbbCR+LnW+F7PwFcSDJOuUJB3OxtEHbg4vSMvzvJjde4Po1v4BR9Gdc9aXNUNFVUY+SK51wWT8WF0Gg=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="/assets/js/app.js" defer></script>
</body>
</html>