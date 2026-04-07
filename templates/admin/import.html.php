<h2>Importer un fichier TRF</h2>

<form method="POST" action="<?= $basePath ?>/admin/import" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

    <div class="form-group">
        <label for="season_year">Année de la saison</label>
        <input type="number" name="season_year" id="season_year"
               value="<?= $currentYear ?>" min="2000" max="2100" required>
    </div>

    <div class="form-group">
        <label for="sort_order">Numéro de la manche</label>
        <input type="number" name="sort_order" id="sort_order"
               value="1" min="1" max="20" required>
    </div>

    <div class="form-group">
        <label for="trf_file">Fichier TRF</label>
        <input type="file" name="trf_file" id="trf_file" accept=".trf,.txt" required>
    </div>

    <button type="submit" class="btn">Importer</button>
</form>

<script>
(function() {
    var stagesByYear = <?= json_encode($stagesByYear, JSON_FORCE_OBJECT) ?>;
    var seasonInput = document.getElementById('season_year');
    var sortOrderInput = document.getElementById('sort_order');
    var form = sortOrderInput.closest('form');

    function getStages(year) {
        return stagesByYear[year] || [];
    }

    function nextAvailableSortOrder(year) {
        var stages = getStages(year);
        var used = {};
        for (var i = 0; i < stages.length; i++) {
            used[stages[i].sort_order] = true;
        }
        var n = 1;
        while (used[n]) n++;
        return n;
    }

    function findStage(year, sortOrder) {
        var stages = getStages(year);
        for (var i = 0; i < stages.length; i++) {
            if (stages[i].sort_order === sortOrder) return stages[i];
        }
        return null;
    }

    function updateDefault() {
        sortOrderInput.value = nextAvailableSortOrder(seasonInput.value);
    }

    seasonInput.addEventListener('change', updateDefault);

    form.addEventListener('submit', function(e) {
        var stage = findStage(seasonInput.value, parseInt(sortOrderInput.value, 10));
        if (stage) {
            var msg = 'La manche ' + stage.sort_order
                + ' (' + stage.name + ', ' + stage.player_count + ' joueurs)'
                + ' a d\u00e9j\u00e0 \u00e9t\u00e9 import\u00e9e. Voulez-vous la r\u00e9importer ?';
            if (!confirm(msg)) {
                e.preventDefault();
            }
        }
    });

    updateDefault();
})();
</script>
