<?php
$formation = $formation ?? null;
$estEdition = $formation !== null && isset($formation['id']);
?>
<div class="page-head">
    <div>
        <h1><?= e($titre) ?></h1>
        <p class="lead-sm">Le tarif est copié dans chaque inscription au moment de sa création.</p>
    </div>
    <a href="<?= e(url('formations')) ?>" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Retour</a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger small">
        <?php foreach ($errors as $message): ?>
            <div><?= e($message) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="post" action="<?= e($estEdition ? url('formations.update') : url('formations.store')) ?>" class="card">
    <?= csrf_field() ?>
    <?php if ($estEdition): ?>
        <input type="hidden" name="id" value="<?= e($formation['id']) ?>">
    <?php endif; ?>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-8">
                <label class="form-label" for="nom">Nom de la formation *</label>
                <input class="form-control <?= isset($errors['nom']) ? 'is-invalid' : '' ?>" type="text" id="nom" name="nom" value="<?= e(old('nom', $formation['nom'] ?? '')) ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="statut">Statut *</label>
                <select class="form-select" id="statut" name="statut" required>
                    <option value="actif" <?= old('statut', $formation['statut'] ?? 'actif') === 'actif' ? 'selected' : '' ?>>Actif</option>
                    <option value="inactif" <?= old('statut', $formation['statut'] ?? '') === 'inactif' ? 'selected' : '' ?>>Inactif</option>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label" for="description">Description</label>
                <textarea class="form-control" id="description" name="description" rows="3"><?= e(old('description', $formation['description'] ?? '')) ?></textarea>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="frais_formation">Frais de formation (FCFA) *</label>
                <input class="form-control <?= isset($errors['frais_formation']) ? 'is-invalid' : '' ?>" type="number" id="frais_formation" name="frais_formation" min="0" step="0.01" value="<?= e(old('frais_formation', $formation['frais_formation'] ?? '0')) ?>" required>
                <div class="form-text">Modification du tarif : les inscriptions existantes ne changent pas.</div>
            </div>
        </div>
    </div>
    <div class="card-footer d-flex justify-content-end gap-2">
        <a href="<?= e(url('formations')) ?>" class="btn btn-light">Annuler</a>
        <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg me-1"></i>Enregistrer</button>
    </div>
</form>
