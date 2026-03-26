<h2>Modifier le tournoi</h2>

<form method="POST" action="/admin/tournament?id=<?= $tournament['id'] ?>">
    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

    <div class="form-group">
        <label for="name">Nom du tournoi</label>
        <input type="text" name="name" id="name" value="<?= htmlspecialchars($tournament['name']) ?>" maxlength="200" required>
    </div>

    <button type="submit" class="btn">Enregistrer</button>
    <a href="/admin" style="margin-left:1rem">Annuler</a>
</form>
