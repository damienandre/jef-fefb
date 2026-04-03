<h2>Modifier le tournoi</h2>

<form method="POST" action="<?= $basePath ?>/admin/tournament?id=<?= intval($tournament['id']) ?>">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

    <div class="form-group">
        <label for="name">Nom du tournoi</label>
        <input type="text" name="name" id="name" value="<?= htmlspecialchars($tournament['name']) ?>" maxlength="200" required>
    </div>

    <div class="form-group">
        <label for="location">Lieu</label>
        <input type="text" name="location" id="location" value="<?= htmlspecialchars($tournament['location'] ?? '') ?>" maxlength="200">
    </div>

    <div class="form-group">
        <label for="organizer">Organisateur (club)</label>
        <input type="text" name="organizer" id="organizer" value="<?= htmlspecialchars($tournament['organizer'] ?? '') ?>" maxlength="200">
    </div>

    <div class="form-group">
        <label for="address">Adresse</label>
        <input type="text" name="address" id="address" value="<?= htmlspecialchars($tournament['address'] ?? '') ?>" maxlength="500">
    </div>

    <div class="form-group">
        <label for="info_url">URL d'information</label>
        <input type="url" name="info_url" id="info_url" value="<?= htmlspecialchars($tournament['info_url'] ?? '') ?>" maxlength="500" placeholder="https://...">
    </div>

    <div class="form-group">
        <label for="registration_url">URL d'inscription</label>
        <input type="url" name="registration_url" id="registration_url" value="<?= htmlspecialchars($tournament['registration_url'] ?? '') ?>" maxlength="500" placeholder="https://...">
    </div>

    <button type="submit" class="btn">Enregistrer</button>
    <a href="<?= $basePath ?>/admin" style="margin-left:1rem">Annuler</a>
</form>
