<h2>Modifier le joueur</h2>

<form method="POST" action="<?= $basePath ?>/admin/player?id=<?= intval($player['id']) ?>">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

    <div class="form-group">
        <label for="last_name">Nom</label>
        <input type="text" name="last_name" id="last_name" value="<?= htmlspecialchars($player['last_name']) ?>" maxlength="100" required>
    </div>

    <div class="form-group">
        <label for="first_name">Prénom</label>
        <input type="text" name="first_name" id="first_name" value="<?= htmlspecialchars($player['first_name']) ?>" maxlength="100" required>
    </div>

    <div class="form-group">
        <label for="birth_date">Date de naissance</label>
        <input type="date" name="birth_date" id="birth_date" value="<?= htmlspecialchars($player['birth_date'] ?? '') ?>" max="<?= date('Y-m-d') ?>">
        <small>Une modification recalcule les classements des saisons concernées.</small>
    </div>

    <div class="form-group">
        <label for="fide_id">ID FIDE</label>
        <input type="number" name="fide_id" id="fide_id" value="<?= $player['fide_id'] ? intval($player['fide_id']) : '' ?>" min="1" max="999999999">
    </div>

    <div class="form-group">
        <label for="belgian_id">ID belge</label>
        <input type="number" name="belgian_id" id="belgian_id" value="<?= $player['belgian_id'] ? intval($player['belgian_id']) : '' ?>" min="1" max="999999999">
    </div>

    <?php if (!empty($participations)): ?>
        <h3>Club par tournoi</h3>
        <p><small>Attention : la commande <code>enrich-players</code> remplace ces valeurs par le club actuel du joueur à la FRBE.</small></p>
        <table>
            <thead>
                <tr>
                    <th>Saison</th>
                    <th>Tournoi</th>
                    <th>Date</th>
                    <th>Club</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($participations as $tp): ?>
                    <tr>
                        <td><?= intval($tp['season_year']) ?></td>
                        <td><?= htmlspecialchars($tp['tournament_name']) ?></td>
                        <td><?= date('d/m/Y', strtotime($tp['date_start'])) ?></td>
                        <td>
                            <select name="clubs[<?= intval($tp['tp_id']) ?>]">
                                <option value="">—</option>
                                <?php foreach ($clubs as $club): ?>
                                    <option value="<?= intval($club['idclub']) ?>" <?= (int) $tp['club_id'] === (int) $club['idclub'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($club['name']) ?> (<?= intval($club['idclub']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <button type="submit" class="btn">Enregistrer</button>
    <a href="<?= $basePath ?>/admin/players?all" style="margin-left:1rem">Retour à la liste</a>
</form>
