<?php
$config = require __DIR__ . '/../../config.php';
$baseUrl = rtrim($config['base_url'], '/');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Administration - Circuit JEF') ?></title>
    <link rel="stylesheet" href="<?= $baseUrl ?>/css/style.css">
</head>
<body>
    <header>
        <div class="header-content">
            <span class="logo-text">JEF</span>
            <h1>Administration</h1>
        </div>
    </header>
    <main>
        <nav class="admin-nav">
            <a href="/admin">Tableau de bord</a>
            <a href="/admin/import">Importer TRF</a>
            <a href="/admin/settings">Parametres</a>
            <form method="POST" action="/admin/logout" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= \Jef\Auth::generateCsrfToken() ?>">
                <button type="submit" style="background:none;border:none;color:var(--color-primary);cursor:pointer;font:inherit;text-decoration:underline;">Deconnexion</button>
            </form>
        </nav>
        <?php if (!empty($_SESSION['flash_success'])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($_SESSION['flash_success']) ?></div>
            <?php unset($_SESSION['flash_success']); ?>
        <?php endif; ?>
        <?php if (!empty($_SESSION['flash_error'])): ?>
            <div class="alert alert-error"><?= htmlspecialchars($_SESSION['flash_error']) ?></div>
            <?php unset($_SESSION['flash_error']); ?>
        <?php endif; ?>
        <?= $content ?>
    </main>
    <footer>
        <p>&copy; <?= date('Y') ?> FEFB &mdash; Administration Circuit JEF</p>
    </footer>
</body>
</html>
