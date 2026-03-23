<h2>Parametres</h2>

<h3>Logo FEFB</h3>

<?php if ($currentLogo): ?>
    <p>Logo actuel :</p>
    <img src="/uploads/<?= htmlspecialchars($currentLogo) ?>" alt="Logo FEFB" style="max-height:80px;margin-bottom:1rem">
<?php else: ?>
    <p>Aucun logo configure. Le texte "FEFB" est affiche par defaut.</p>
<?php endif; ?>

<form method="POST" action="/admin/settings" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

    <div class="form-group">
        <label for="logo">Nouveau logo (PNG, JPG ou SVG, max 2 Mo)</label>
        <input type="file" name="logo" id="logo" accept=".png,.jpg,.jpeg,.svg" required>
    </div>

    <button type="submit" class="btn">Enregistrer</button>
</form>
