<?php
$config = require __DIR__ . '/../config.php';
$baseUrl = rtrim($config['base_url'], '/');

$db = \Jef\Database::get();
$logoStmt = $db->prepare("SELECT `value` FROM jef_settings WHERE `key` = ?");
$logoStmt->execute(['logo_path']);
$logoPath = $logoStmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Circuit JEF - FEFB') ?></title>
    <link rel="stylesheet" href="<?= $baseUrl ?>/css/style.css">
</head>
<body>
    <header>
        <div class="header-content">
            <?php if ($logoPath): ?>
                <img src="<?= $baseUrl ?>/uploads/<?= htmlspecialchars($logoPath) ?>" alt="FEFB" class="logo">
            <?php else: ?>
                <span class="logo-text">FEFB</span>
            <?php endif; ?>
            <h1>Circuit JEF</h1>
            <p class="subtitle">Classement du circuit jeunes</p>
        </div>
        <nav>
            <a href="<?= $baseUrl ?>/">Classement</a>
        </nav>
    </header>
    <main>
        <?= $content ?>
    </main>
    <footer>
        <p>&copy; <?= date('Y') ?> FEFB &mdash; Federation Echiquenne Francophone de Belgique</p>
    </footer>
</body>
</html>
