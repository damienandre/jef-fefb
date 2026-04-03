<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Circuit JEF - FEFB') ?></title>
    <link rel="stylesheet" href="<?= $basePath ?>/css/style.css">
</head>
<body>
    <header>
        <div class="header-brand">
            <div class="header-content">
                <?php if (!empty($fefbUrl)): ?><a href="<?= htmlspecialchars($fefbUrl) ?>" target="_blank" rel="noopener"><?php endif; ?>
                <?php if (!empty($logoPath)): ?>
                    <img src="<?= $basePath ?>/uploads/<?= htmlspecialchars($logoPath) ?>" alt="FEFB" class="logo">
                <?php else: ?>
                    <span class="logo-text">FEFB</span>
                <?php endif; ?>
                <?php if (!empty($fefbUrl)): ?></a><?php endif; ?>
                <div class="header-titles">
                    <h1>Circuit JEF</h1>
                    <p class="subtitle">Classement du circuit jeunes</p>
                </div>
            </div>
        </div>
        <nav>
            <div class="nav-content">
                <a href="<?= $basePath ?>/">Classement</a>
                <a href="<?= $basePath ?>/etapes">Étapes</a>
            </div>
        </nav>
    </header>
    <main>
        <?= $content ?>
    </main>
    <footer>
        <p>&copy; <?= date('Y') ?> FEFB &mdash; Fédération Échiquéenne Francophone de Belgique</p>
    </footer>
</body>
</html>
