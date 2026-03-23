<h2>Importer un fichier TRF</h2>

<form method="POST" action="/admin/import" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

    <div class="form-group">
        <label for="season_year">Annee de la saison</label>
        <input type="number" name="season_year" id="season_year"
               value="<?= $currentYear ?>" min="2000" max="2100" required>
    </div>

    <div class="form-group">
        <label for="sort_order">Numero de la manche</label>
        <input type="number" name="sort_order" id="sort_order"
               value="1" min="1" max="20" required>
    </div>

    <div class="form-group">
        <label for="trf_file">Fichier TRF</label>
        <input type="file" name="trf_file" id="trf_file" accept=".trf,.txt" required>
    </div>

    <button type="submit" class="btn">Importer</button>
</form>
