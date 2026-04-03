<h2>Tableau de bord</h2>

<?php if (empty($seasons)): ?>
    <p>Aucune saison. <a href="<?= $basePath ?>/admin/add-stage">Ajouter une étape</a> ou <a href="<?= $basePath ?>/admin/import">importer un fichier TRF</a> pour commencer.</p>
<?php else: ?>
    <?php foreach ($seasons as $season): ?>
        <h3>Saison <?= $season['year'] ?> (<?= $season['status'] === 'active' ? 'en cours' : 'terminée' ?>)</h3>

        <?php if (empty($tournaments[$season['id']])): ?>
            <p>Aucun tournoi importé.</p>
        <?php else: ?>
            <table class="rankings-table" style="margin-bottom:1.5rem">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Tournoi</th>
                        <th>Date</th>
                        <th>Joueurs</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tournaments[$season['id']] as $t): ?>
                        <tr>
                            <td><?= $t['sort_order'] ?></td>
                            <td><?= htmlspecialchars($t['name']) ?></td>
                            <td><?= $t['date_start'] ?></td>
                            <td><?= $t['player_count'] ?></td>
                            <td><a href="<?= $basePath ?>/admin/tournament?id=<?= intval($t['id']) ?>">Modifier</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php endforeach; ?>
<?php endif; ?>
