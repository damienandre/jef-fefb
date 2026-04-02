<h2>Joueurs</h2>

<p>
    <?php if ($showAll): ?>
        <a href="/admin/players">Afficher uniquement les joueurs sans ID FIDE</a>
    <?php else: ?>
        <a href="/admin/players?all">Afficher tous les joueurs</a>
    <?php endif; ?>
</p>

<?php if (empty($players)): ?>
    <p>Aucun joueur trouvé.</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Nom</th>
                <th>Prénom</th>
                <th>Date de naissance</th>
                <th>ID FIDE</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($players as $player): ?>
                <tr>
                    <td><?= htmlspecialchars($player['last_name']) ?></td>
                    <td><?= htmlspecialchars($player['first_name']) ?></td>
                    <td><?= $player['birth_date'] ? date('d/m/Y', strtotime($player['birth_date'])) : '—' ?></td>
                    <?php if ($player['fide_id'] && $player['fide_id'] > 0): ?>
                        <td><?= intval($player['fide_id']) ?></td>
                        <td></td>
                    <?php else: ?>
                        <td colspan="2">
                            <form method="POST" action="/admin/players" style="display:flex;gap:0.5rem;align-items:center">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                <input type="hidden" name="player_id" value="<?= intval($player['id']) ?>">
                                <input type="number" name="fide_id" min="1" max="999999999" placeholder="ID FIDE" required style="width:10rem">
                                <button type="submit" class="btn">Attribuer</button>
                            </form>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<p style="margin-top:1rem"><a href="/admin">Retour au tableau de bord</a></p>
