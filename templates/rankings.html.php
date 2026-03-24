<div class="rankings-page">
    <div class="filters">
        <form method="GET" action="/">
            <label for="annee">Annee :</label>
            <select name="annee" id="annee" onchange="this.form.submit()">
                <?php foreach ($availableYears as $year): ?>
                    <option value="<?= $year ?>" <?= $year === $selectedYear ? 'selected' : '' ?>>
                        <?= $year ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="categorie">Categorie :</label>
            <select name="categorie" id="categorie" onchange="this.form.submit()">
                <option value="general" <?= $selectedCategory === 'general' ? 'selected' : '' ?>>
                    Toutes les categories
                </option>
                <?php foreach ($allCategories as $cat): ?>
                    <option value="<?= $cat ?>" <?= $selectedCategory === $cat ? 'selected' : '' ?>>
                        <?= $cat ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <?php if (empty($rankings)): ?>
        <p class="empty-state">Aucun classement disponible pour cette annee<?= $selectedCategory !== 'general' ? ' et cette categorie' : '' ?>.</p>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="rankings-table">
                <thead>
                    <tr>
                        <th>Rang</th>
                        <th>Nom</th>
                        <th>Cat.</th>
                        <th>Total</th>
                        <?php foreach ($tournaments as $t): ?>
                            <th colspan="2">
                                <a href="/tournoi?id=<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></a>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                    <?php if (!empty($tournaments)): ?>
                    <tr class="sub-header">
                        <th></th>
                        <th></th>
                        <th></th>
                        <th></th>
                        <?php foreach ($tournaments as $t): ?>
                            <th>Cl.</th>
                            <th>Pts</th>
                        <?php endforeach; ?>
                    </tr>
                    <?php endif; ?>
                </thead>
                <tbody>
                    <?php foreach ($rankings as $row): ?>
                        <tr>
                            <td class="rank"><?= $row['rank'] ?></td>
                            <td class="name"><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
                            <td class="category"><?= htmlspecialchars($row['category']) ?></td>
                            <td class="total"><?= number_format((float) $row['total_points'], 1) ?></td>
                            <?php foreach ($tournaments as $t): ?>
                                <?php $res = $row['tournament_results'][$t['id']] ?? null; ?>
                                <?php if ($res): ?>
                                    <td class="tour-rank"><?= $res['score_position'] ?></td>
                                    <td class="tour-points"><?= number_format((float) $res['circuit_points'], 1) ?></td>
                                <?php else: ?>
                                    <td class="tour-rank">—</td>
                                    <td class="tour-points">—</td>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
