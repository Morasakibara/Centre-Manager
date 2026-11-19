<div class="page-head">
    <div>
        <h1>Enregistrer un paiement</h1>
        <p class="lead-sm">Le paiement est validé uniquement si le montant ne dépasse pas le solde.</p>
    </div>
    <a href="<?= e(url('paiements')) ?>" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Retour</a>
</div>

<?php if ($inscriptions === []): ?>
    <div class="alert alert-info">
        Aucune inscription active avec un solde restant à encaisser. <a href="<?= e(url('inscriptions')) ?>">Voir les inscriptions</a>.
    </div>
<?php else: ?>
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger small">
            <?php foreach ($errors as $message): ?><div><?= e($message) ?></div><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('paiements.store')) ?>" class="card">
        <?= csrf_field() ?>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label" for="inscription_id">Inscription *</label>
                    <select class="form-select <?= isset($errors['inscription_id']) ? 'is-invalid' : '' ?>" id="inscription_id" name="inscription_id" required>
                        <option value="">— Sélectionner —</option>
                        <?php foreach ($inscriptions as $ins): ?>
                            <?php $sit = $situations[$ins['id']] ?? null; ?>
                            <option value="<?= e($ins['id']) ?>"
                                    data-solde="<?= e($sit['reste'] ?? 0) ?>"
                                    <?= old('inscription_id', $pre_inscription) === (string) $ins['id'] ? 'selected' : '' ?>>
                                <?= e($ins['etudiant_prenom'] . ' ' . $ins['etudiant_nom']) ?> — <?= e($ins['formation_nom']) ?> (<?= e($ins['annee_libelle']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text" id="solde_info"></div>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="montant_paiement">Montant (FCFA) *</label>
                    <input class="form-control <?= isset($errors['montant']) ? 'is-invalid' : '' ?>" type="number" id="montant_paiement" name="montant" min="1" step="0.01" value="<?= e(old('montant')) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="mode_paiement">Mode de paiement *</label>
                    <select class="form-select" id="mode_paiement" name="mode_paiement" required>
                        <?php foreach ($modes as $k => $v): ?>
                            <option value="<?= e($k) ?>" <?= old('mode_paiement', 'especes') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="date_paiement">Date et heure *</label>
                    <input class="form-control" type="datetime-local" id="date_paiement" name="date_paiement" value="<?= e(old('date_paiement')) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="reference">Référence de transaction</label>
                    <input class="form-control" type="text" id="reference" name="reference" value="<?= e(old('reference')) ?>" placeholder="Orange Money, MTN, virement…">
                </div>
                <div class="col-12">
                    <label class="form-label" for="observation">Observation</label>
                    <textarea class="form-control" id="observation" name="observation" rows="2"><?= e(old('observation')) ?></textarea>
                </div>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-end gap-2">
            <a href="<?= e(url('paiements')) ?>" class="btn btn-light">Annuler</a>
            <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg me-1"></i>Enregistrer le paiement</button>
        </div>
    </form>
<?php endif; ?>
