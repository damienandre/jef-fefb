<h2>Ajouter une étape</h2>

<form method="POST" action="/admin/add-stage">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

    <div class="form-group">
        <label for="season_year">Année de la saison</label>
        <input type="number" name="season_year" id="season_year"
               value="<?= htmlspecialchars($old['season_year'] ?? (string) $currentYear) ?>" min="2000" max="2100" required>
    </div>

    <div class="form-group">
        <label for="sort_order">Numéro de l'étape</label>
        <input type="number" name="sort_order" id="sort_order"
               value="<?= htmlspecialchars($old['sort_order'] ?? '1') ?>" min="1" max="20" required>
    </div>

    <div class="form-group">
        <label for="name">Nom de l'étape</label>
        <input type="text" name="name" id="name" value="<?= htmlspecialchars($old['name'] ?? '') ?>" maxlength="200" required>
    </div>

    <div class="form-group">
        <label for="date_start">Date de début</label>
        <input type="date" name="date_start" id="date_start" value="<?= htmlspecialchars($old['date_start'] ?? '') ?>" required>
    </div>

    <div class="form-group">
        <label for="date_end">Date de fin</label>
        <input type="date" name="date_end" id="date_end" value="<?= htmlspecialchars($old['date_end'] ?? '') ?>">
    </div>

    <div class="form-group">
        <label for="location">Lieu</label>
        <input type="text" name="location" id="location" value="<?= htmlspecialchars($old['location'] ?? '') ?>" maxlength="200">
    </div>

    <div class="form-group">
        <label for="organizer">Organisateur (club)</label>
        <input type="text" name="organizer" id="organizer" value="<?= htmlspecialchars($old['organizer'] ?? '') ?>" maxlength="200">
    </div>

    <div class="form-group">
        <label for="address">Adresse</label>
        <input type="text" name="address" id="address" value="<?= htmlspecialchars($old['address'] ?? '') ?>" maxlength="500">
    </div>

    <div class="form-group">
        <label for="info_url">URL d'information</label>
        <input type="url" name="info_url" id="info_url" value="<?= htmlspecialchars($old['info_url'] ?? '') ?>" maxlength="500" placeholder="https://...">
    </div>

    <div class="form-group">
        <label for="registration_url">URL d'inscription</label>
        <input type="url" name="registration_url" id="registration_url" value="<?= htmlspecialchars($old['registration_url'] ?? '') ?>" maxlength="500" placeholder="https://...">
    </div>

    <button type="submit" class="btn">Ajouter</button>
    <a href="/admin" style="margin-left:1rem">Annuler</a>
</form>
