<div class="stages-page">
    <div class="filters">
        <form method="GET" action="/etapes">
            <label for="annee">Année :</label>
            <select name="annee" id="annee" onchange="this.form.submit()">
                <?php foreach ($availableYears as $year): ?>
                    <option value="<?= $year ?>" <?= $year === $selectedYear ? 'selected' : '' ?>>
                        <?= $year ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <?php if (empty($stages)): ?>
        <p class="empty-state">Aucune étape disponible pour cette année.</p>
    <?php else: ?>
        <?php foreach ($stages as $stage): ?>
            <div class="stage-card">
                <h3>
                    <a href="/tournoi?id=<?= intval($stage['id']) ?>">
                        Étape <?= intval($stage['sort_order']) ?> &mdash; <?= htmlspecialchars($stage['name']) ?>
                    </a>
                    <?php if ($stage['is_completed']): ?>
                        <span class="stage-badge stage-badge-done">Terminé</span>
                    <?php elseif (($stage['date_end'] ?? $stage['date_start']) < $today): ?>
                        <span class="stage-badge stage-badge-past">En attente des résultats</span>
                    <?php else: ?>
                        <span class="stage-badge stage-badge-upcoming">À venir</span>
                    <?php endif; ?>
                </h3>

                <dl class="stage-meta">
                    <dt>Date</dt>
                    <dd>
                        <?= htmlspecialchars($stage['date_start']) ?>
                        <?php if ($stage['date_end'] && $stage['date_end'] !== $stage['date_start']): ?>
                            au <?= htmlspecialchars($stage['date_end']) ?>
                        <?php endif; ?>
                    </dd>

                    <?php if ($stage['location']): ?>
                        <dt>Lieu</dt>
                        <dd><?= htmlspecialchars($stage['location']) ?></dd>
                    <?php endif; ?>

                    <?php if ($stage['organizer']): ?>
                        <dt>Organisateur</dt>
                        <dd><?= htmlspecialchars($stage['organizer']) ?></dd>
                    <?php endif; ?>

                    <?php if ($stage['address']): ?>
                        <dt>Adresse</dt>
                        <dd><?= htmlspecialchars($stage['address']) ?></dd>
                    <?php endif; ?>

                    <?php if ($stage['info_url']): ?>
                        <dt>Informations</dt>
                        <dd><a href="<?= htmlspecialchars($stage['info_url']) ?>" target="_blank" rel="noopener">Plus d'informations</a></dd>
                    <?php endif; ?>

                    <?php if (!$stage['is_completed'] && $stage['registration_url']): ?>
                        <dt>Inscription</dt>
                        <dd><a href="<?= htmlspecialchars($stage['registration_url']) ?>" target="_blank" rel="noopener" class="btn-register">S'inscrire</a></dd>
                    <?php endif; ?>
                </dl>

                <?php if ($stage['is_completed'] && $stage['player_count']): ?>
                    <p class="stage-result-link">
                        <a href="/tournoi?id=<?= intval($stage['id']) ?>"><?= intval($stage['player_count']) ?> joueurs &mdash; Voir les résultats</a>
                    </p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
