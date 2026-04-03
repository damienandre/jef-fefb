<h2>Paramètres</h2>

<form method="POST" action="<?= $basePath ?>/admin/settings" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

    <h3>Logo FEFB</h3>

    <?php if ($currentLogo): ?>
        <p>Logo actuel :</p>
        <img src="<?= $basePath ?>/uploads/<?= htmlspecialchars($currentLogo) ?>" alt="Logo FEFB" style="max-height:80px;margin-bottom:1rem">
    <?php else: ?>
        <p>Aucun logo configuré. Le texte "FEFB" est affiché par défaut.</p>
    <?php endif; ?>

    <div class="form-group">
        <label for="logo">Nouveau logo (PNG ou JPG, max 2 Mo)</label>
        <input type="file" name="logo" id="logo" accept=".png,.jpg,.jpeg">
    </div>

    <h3>URL du site FEFB</h3>

    <div class="form-group">
        <label for="fefb_url">Lien vers le site de la FEFB (clic sur le logo)</label>
        <input type="url" name="fefb_url" id="fefb_url" value="<?= htmlspecialchars($currentFefbUrl) ?>" placeholder="https://www.fefb.be">
    </div>

    <button type="submit" class="btn">Enregistrer</button>
</form>
