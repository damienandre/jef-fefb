<a href="/" class="back-link">&larr; Retour au classement</a>

<div class="tournament-header">
    <h2><?= htmlspecialchars($tournament['name']) ?></h2>
    <p class="tournament-meta">
        <?php if ($tournament['location']): ?>
            <?= htmlspecialchars($tournament['location']) ?> &mdash;
        <?php endif; ?>
        <?= htmlspecialchars($tournament['date_start']) ?>
        <?php if ($tournament['date_end'] && $tournament['date_end'] !== $tournament['date_start']): ?>
            au <?= htmlspecialchars($tournament['date_end']) ?>
        <?php endif; ?>
        &mdash; <?= $tournament['player_count'] ?> joueurs &mdash; <?= $tournament['round_count'] ?> rondes
    </p>
    <?php if ($tournament['organizer'] || $tournament['address'] || $tournament['info_url'] || (!$tournament['is_completed'] && $tournament['registration_url'])): ?>
        <div class="tournament-info">
            <?php if ($tournament['organizer']): ?>
                <span>Organisé par : <?= htmlspecialchars($tournament['organizer']) ?></span>
            <?php endif; ?>
            <?php if ($tournament['address']): ?>
                <span>Adresse : <?= htmlspecialchars($tournament['address']) ?></span>
            <?php endif; ?>
            <?php if ($tournament['info_url']): ?>
                <span><a href="<?= htmlspecialchars($tournament['info_url']) ?>" target="_blank" rel="noopener">Plus d'informations</a></span>
            <?php endif; ?>
            <?php if (!$tournament['is_completed'] && $tournament['registration_url']): ?>
                <a href="<?= htmlspecialchars($tournament['registration_url']) ?>" target="_blank" rel="noopener" class="btn-register">S'inscrire</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php if (empty($players)): ?>
    <p class="empty-state">Aucun résultat disponible pour ce tournoi.</p>
<?php else: ?>
    <div class="table-wrapper">
        <table class="rankings-table">
            <thead>
                <tr>
                    <th>Cl.</th>
                    <th>Nom</th>
                    <th>Pts</th>
                    <?php for ($r = 1; $r <= (int) $tournament['round_count']; $r++): ?>
                        <th>R<?= $r ?></th>
                    <?php endfor; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($players as $player): ?>
                    <tr>
                        <td class="rank"><?= $player['final_rank'] ?? '—' ?></td>
                        <td class="name"><?= htmlspecialchars($player['first_name'] . ' ' . $player['last_name']) ?></td>
                        <td class="total"><?= number_format((float) $player['points'], 1) ?></td>
                        <?php for ($r = 1; $r <= (int) $tournament['round_count']; $r++): ?>
                            <?php $round = $player['rounds_by_num'][$r] ?? null; ?>
                            <td>
                                <?php if ($round && $round['result']): ?>
                                    <?php
                                    $resultClass = match ($round['result']) {
                                        '1', '+', 'w' => 'result-win',
                                        '=', 'd' => 'result-draw',
                                        '0', '-', 'l' => 'result-loss',
                                        default => '',
                                    };
                                    $resultSymbol = match ($round['result']) {
                                        '1', 'w' => '1',
                                        '0', 'l' => '0',
                                        '=', 'd' => '=',
                                        '+' => '+',
                                        '-' => '-',
                                        'h' => '=',
                                        'f' => '1F',
                                        default => $round['result'],
                                    };
                                    $opponentName = '';
                                    if (!empty($round['opponent_rank'])) {
                                        $opponentName = $playerNamesByRank[$round['opponent_rank']] ?? "#{$round['opponent_rank']}";
                                    }
                                    $colorIndicator = match ($round['color'] ?? '') {
                                        'w' => 'B',
                                        'b' => 'N',
                                        default => '',
                                    };
                                    ?>
                                    <span class="<?= $resultClass ?>"><?= htmlspecialchars($resultSymbol) ?></span>
                                    <?php if ($colorIndicator): ?>
                                        <span class="color-<?= $round['color'] === 'w' ? 'white' : 'black' ?>">(<?= $colorIndicator ?>)</span>
                                    <?php endif; ?>
                                    <?php if ($opponentName): ?>
                                        <br><small><?= htmlspecialchars($opponentName) ?></small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                        <?php endfor; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
