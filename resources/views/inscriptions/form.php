<div class="page-head">
    <div>
        <h1>Nouvelle inscription</h1>
        <p class="lead-sm">Un étudiant ne peut avoir qu'une inscription par année académique.</p>
    </div>
    <a href="<?= e(url('inscriptions')) ?>" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Retour</a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger small">
        <?php foreach ($errors as $message): ?><div><?= e($message) ?></div><?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="post" action="<?= e(url('inscriptions.store')) ?>" class="card">
    <?= csrf_field() ?>
    <div class="card-body">
        <div class="form-section-title">Dossier</div>
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <label class="form-label" for="etudiant_id">Étudiant *</label>
                <select class="form-select <?= isset($errors['etudiant_id']) ? 'is-invalid' : '' ?>" id="etudiant_id" name="etudiant_id" required>
                    <option value="">— Sélectionner —</option>
                    <?php foreach ($etudiants as $e): ?>
                        <option value="<?= e($e['id']) ?>" <?= (string) old('etudiant_id', $pre_etudiant) === (string) $e['id'] ? 'selected' : '' ?>>
                            <?= e($e['prenom'] . ' ' . $e['nom']) ?> (<?= e($e['matricule']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="formation_id">Formation *</label>
                <select class="form-select <?= isset($errors['formation_id']) ? 'is-invalid' : '' ?>" id="formation_id" name="formation_id" required>
                    <option value="">— Sélectionner —</option>
                    <?php foreach ($formations as $f): ?>
                        <option value="<?= e($f['id']) ?>" data-frais="<?= e($f['frais_formation']) ?>" <?= old('formation_id') === (string) $f['id'] ? 'selected' : '' ?>>
                            <?= e($f['nom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="annee_academique_id">Année académique *</label>
                <select class="form-select <?= isset($errors['annee_academique_id']) ? 'is-invalid' : '' ?>" id="annee_academique_id" name="annee_academique_id" required>
                    <option value="">— Sélectionner —</option>
                    <?php foreach ($annees as $a): ?>
                        <option value="<?= e($a['id']) ?>" <?= old('annee_academique_id') === (string) $a['id'] ? 'selected' : '' ?>><?= e($a['libelle']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="date_inscription">Date d'inscription *</label>
                <input class="form-control" type="date" id="date_inscription" name="date_inscription" value="<?= e(old('date_inscription', date('Y-m-d'))) ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="mode_paiement">Mode de paiement *</label>
                <select class="form-select" id="mode_paiement" name="mode_paiement" required>
                    <option value="libre" <?= old('mode_paiement', 'libre') === 'libre' ? 'selected' : '' ?>>Paiement libre</option>
                    <option value="tranches" <?= old('mode_paiement') === 'tranches' ? 'selected' : '' ?>>Paiement par tranches</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="montant_total">Montant total convenu (FCFA) *</label>
                <input class="form-control <?= isset($errors['montant_total']) ? 'is-invalid' : '' ?>" type="number" id="montant_total" name="montant_total" min="0" step="0.01" value="<?= e(old('montant_total')) ?>">
                <div class="form-text" id="frais_help">Sélectionnez une formation.</div>
            </div>
            <div class="col-12">
                <label class="form-label" for="observation">Observation</label>
                <textarea class="form-control" id="observation" name="observation" rows="2"><?= e(old('observation')) ?></textarea>
            </div>
        </div>

        <div id="echeancier_panel" class="d-none">
            <div class="form-section-title d-flex justify-content-between align-items-center">
                <span>Échéancier (tranches)</span>
                <button class="btn btn-sm btn-outline-primary" type="button" id="ajouter_tranche">
                    <i class="bi bi-plus"></i> Ajouter une tranche
                </button>
            </div>
            <div class="table-responsive mb-2">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>Libellé</th>
                            <th style="width:160px;">Montant (FCFA)</th>
                            <th style="width:180px;">Date limite</th>
                            <th style="width:40px;"></th>
                        </tr>
                    </thead>
                    <tbody id="echeancier_lignes"></tbody>
                </table>
            </div>
            <div id="echeancier_difference" class="small"></div>
        </div>
    </div>
    <div class="card-footer d-flex justify-content-end gap-2">
        <a href="<?= e(url('inscriptions')) ?>" class="btn btn-light">Annuler</a>
        <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg me-1"></i>Créer l'inscription</button>
    </div>
</form>

<template id="echeancier_modele">
    <tr class="echeance-ligne">
        <td><input class="form-control form-control-sm echeance-libelle" type="text" name="echeance_libelle[]" placeholder="Tranche 1"></td>
        <td><input class="form-control form-control-sm echeance-montant" type="number" name="echeance_montant[]" min="0" step="0.01" required></td>
        <td><input class="form-control form-control-sm echeance-date" type="date" name="echeance_date[]" required></td>
        <td><button class="btn btn-sm btn-outline-danger btn-supprimer-tranche" type="button"><i class="bi bi-x"></i></button></td>
    </tr>
</template>
