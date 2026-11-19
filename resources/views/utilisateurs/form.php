<div class="page-head">
    <div>
        <h1><?= e($titre) ?></h1>
        <p class="lead-sm">Gestion des comptes et rôles.</p>
    </div>
    <a href="<?= e(url('utilisateurs')) ?>" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Retour</a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger small">
        <?php foreach ($errors as $message): ?><div><?= e($message) ?></div><?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="post" action="<?= e($estEdition ? url('utilisateurs.update') : url('utilisateurs.store')) ?>" class="card">
    <?= csrf_field() ?>
    <?php if ($estEdition): ?>
        <input type="hidden" name="id" value="<?= e($utilisateur['id']) ?>">
    <?php endif; ?>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="nom_complet">Nom complet *</label>
                <input class="form-control" type="text" id="nom_complet" name="nom_complet" value="<?= e(old('nom_complet', $utilisateur['nom_complet'] ?? '')) ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="login">Login *</label>
                <input class="form-control" type="text" id="login" name="login" value="<?= e(old('login', $utilisateur['login'] ?? '')) ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="role">Rôle *</label>
                <select class="form-select" id="role" name="role" required>
                    <?php foreach (['administrateur' => 'Administrateur', 'secretaire' => 'Secrétaire', 'caissier' => 'Caissier'] as $k => $v): ?>
                        <option value="<?= e($k) ?>" <?= old('role', $utilisateur['role'] ?? 'secretaire') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($estEdition): ?>
                <div class="col-md-6">
                    <label class="form-label" for="statut">Statut</label>
                    <select class="form-select" id="statut" name="statut">
                        <option value="actif" <?= old('statut', $utilisateur['statut'] ?? 'actif') === 'actif' ? 'selected' : '' ?>>Actif</option>
                        <option value="inactif" <?= old('statut', $utilisateur['statut'] ?? '') === 'inactif' ? 'selected' : '' ?>>Inactif</option>
                    </select>
                </div>
            <?php endif; ?>
            <div class="col-md-6">
                <label class="form-label" for="mot_de_passe"><?= $estEdition ? 'Nouveau mot de passe (optionnel)' : 'Mot de passe *' ?></label>
                <input class="form-control" type="password" id="mot_de_passe" name="mot_de_passe" <?= $estEdition ? '' : 'required' ?> minlength="6">
            </div>
            <?php if (!$estEdition): ?>
                <div class="col-md-6">
                    <label class="form-label" for="confirmation">Confirmation du mot de passe *</label>
                    <input class="form-control" type="password" id="confirmation" name="confirmation" required minlength="6">
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-footer d-flex justify-content-end gap-2">
        <a href="<?= e(url('utilisateurs')) ?>" class="btn btn-light">Annuler</a>
        <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg me-1"></i>Enregistrer</button>
    </div>
</form>
