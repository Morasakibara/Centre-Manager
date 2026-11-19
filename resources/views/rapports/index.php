<div class="page-head">
    <div>
        <h1>Rapports</h1>
        <p class="lead-sm">Filtres par période, formation, année, mode et statut financier.</p>
    </div>
</div>

<form method="get" action="<?= e(base_url() . '/index.php') ?>" class="card">
    <input type="hidden" name="r" value="rapports.generer">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Type de rapport</label>
                <select class="form-select" name="type">
                    <?php foreach ($options['types'] as $k => $v): ?>
                        <option value="<?= e($k) ?>" <?= ($filtres['type'] ?? 'general') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Du</label>
                <input class="form-control" type="date" name="debut" value="<?= e($filtres['debut']) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Au</label>
                <input class="form-control" type="date" name="fin" value="<?= e($filtres['fin']) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Année académique</label>
                <select class="form-select" name="annee_id">
                    <option value="">Toutes</option>
                    <?php foreach ($options['annees'] as $a): ?>
                        <option value="<?= e($a['id']) ?>" <?= ($filtres['annee_id'] ?? '') === (string) $a['id'] ? 'selected' : '' ?>><?= e($a['libelle']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Formation</label>
                <select class="form-select" name="formation_id">
                    <option value="">Toutes</option>
                    <?php foreach ($options['formations'] as $f): ?>
                        <option value="<?= e($f['id']) ?>" <?= ($filtres['formation_id'] ?? '') === (string) $f['id'] ? 'selected' : '' ?>><?= e($f['nom']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Étudiant (rapport individuel)</label>
                <select class="form-select" name="etudiant_id">
                    <option value="">—</option>
                    <?php foreach ($options['etudiants'] as $e): ?>
                        <option value="<?= e($e['id']) ?>" <?= ($filtres['etudiant_id'] ?? '') === (string) $e['id'] ? 'selected' : '' ?>><?= e($e['prenom'] . ' ' . $e['nom']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Mode d'inscription</label>
                <select class="form-select" name="mode_inscription">
                    <option value="">Tous</option>
                    <option value="libre" <?= ($filtres['mode_inscription'] ?? '') === 'libre' ? 'selected' : '' ?>>Libre</option>
                    <option value="tranches" <?= ($filtres['mode_inscription'] ?? '') === 'tranches' ? 'selected' : '' ?>>Tranches</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Mode de paiement</label>
                <select class="form-select" name="mode_paiement">
                    <option value="">Tous</option>
                    <option value="especes">Espèces</option>
                    <option value="orange_money">Orange Money</option>
                    <option value="mtn_mobile_money">MTN Mobile Money</option>
                    <option value="virement">Virement</option>
                    <option value="autre">Autre</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Statut financier</label>
                <select class="form-select" name="statut_financier">
                    <option value="">Tous</option>
                    <option value="solde">Soldé</option>
                    <option value="en_retard">En retard</option>
                    <option value="partiel">Partiellement réglé</option>
                    <option value="a_jour">À jour</option>
                    <option value="a_venir">À venir</option>
                    <option value="non_solde">Non soldé</option>
                </select>
            </div>
        </div>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <span class="small text-muted">Les exports PDF/Excel sont générés à partir des données réelles filtrées.</span>
        <button class="btn btn-primary" type="submit"><i class="bi bi-bar-chart-line me-1"></i>Générer le rapport</button>
    </div>
</form>
