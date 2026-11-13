<?php

declare(strict_types=1);

use Jef\Auth;
use Jef\Database;
use Jef\PlayerEditor;
use Jef\View;

Auth::requireAuth();

$db = Database::get();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    \Jef\Url::redirect('/admin/players?all');
}

$stmt = $db->prepare(
    "SELECT id, last_name, first_name, birth_date, fide_id, belgian_id
     FROM jef_players WHERE id = ?"
);
$stmt->execute([$id]);
$player = $stmt->fetch();

if (!$player) {
    $_SESSION['flash_error'] = 'Joueur introuvable.';
    \Jef\Url::redirect('/admin/players?all');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Session invalide. Veuillez réessayer.';
        \Jef\Url::redirect('/admin/player?id=' . $id);
    }

    [$data, $errors] = PlayerEditor::validate($_POST);
    if ($errors === []) {
        $errors = PlayerEditor::update($db, $id, $data);
    }

    if ($errors !== []) {
        $_SESSION['flash_error'] = implode(' ', $errors);
        // Keep what the admin typed so the form can be corrected, not re-entered.
        $_SESSION['player_form'] = [
            'player_id' => $id,
            'input' => array_intersect_key($_POST, array_flip(
                ['last_name', 'first_name', 'birth_date', 'fide_id', 'belgian_id', 'clubs']
            )),
        ];
        \Jef\Url::redirect('/admin/player?id=' . $id);
    }

    $_SESSION['flash_success'] = 'Joueur mis à jour.';
    \Jef\Url::redirect('/admin/player?id=' . $id);
}

$stmt = $db->prepare(
    "SELECT tp.id AS tp_id, tp.club_id, t.name AS tournament_name, t.date_start, s.year AS season_year
     FROM jef_tournament_players tp
     JOIN jef_tournaments t ON t.id = tp.tournament_id
     JOIN jef_seasons s ON s.id = t.season_id
     WHERE tp.player_id = ?
     ORDER BY t.date_start DESC, t.sort_order DESC"
);
$stmt->execute([$id]);
$participations = $stmt->fetchAll();

// Restore the submitted values after a failed save.
$oldForm = $_SESSION['player_form'] ?? null;
unset($_SESSION['player_form']);
if (is_array($oldForm) && ($oldForm['player_id'] ?? null) === $id) {
    $old = $oldForm['input'];
    foreach (['last_name', 'first_name', 'birth_date', 'fide_id', 'belgian_id'] as $field) {
        if (isset($old[$field]) && is_string($old[$field])) {
            $player[$field] = $old[$field];
        }
    }
    $oldClubs = is_array($old['clubs'] ?? null) ? $old['clubs'] : [];
    foreach ($participations as &$tp) {
        if (array_key_exists($tp['tp_id'], $oldClubs) && is_string($oldClubs[$tp['tp_id']])) {
            $tp['club_id'] = $oldClubs[$tp['tp_id']] === '' ? null : $oldClubs[$tp['tp_id']];
        }
    }
    unset($tp);
}

$clubs = $db->query(
    "SELECT idclub, COALESCE(name_short, name_long) AS name
     FROM jef_clubs
     ORDER BY name"
)->fetchAll();

$csrfToken = Auth::generateCsrfToken();

View::render('admin/player.html.php', [
    'pageTitle' => 'Modifier le joueur - Administration JEF',
    'player' => $player,
    'participations' => $participations,
    'clubs' => $clubs,
    'csrfToken' => $csrfToken,
], 'admin/layout.php');
