<?php
$etudiant = $etudiant ?? null;
$doublons = $doublons ?? [];
$estEdition = $etudiant !== null && isset($etudiant['id']);
$action = $estEdition ? url('etudiants.update') : url('etudiants.store');
?>

<div class="page-head">
    <div>
        <h1><?= e($titre ?? ($estEdition ? 'Modifier un étudiant' : 'Nouvel étudiant')) ?></h1>
        <p class="lead-sm">Les champs marqués d'un astérisque sont obligatoires.</p>
    </div>
    <a href="<?= e(url('etudiants')) ?>" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Retour</a>
</div>

<?php if ($doublons !== []): ?>
    <div class="alert alert-warning">
        <strong>Homonyme détecté :</strong> un étudiant avec un nom et un téléphone similaires existe déjà.
        <ul class="mb-2 mt-2">
            <?php foreach ($doublons as $d): ?>
                <li><?= e($d['prenom'] . ' ' . $d['nom']) ?> — <?= e($d['matricule']) ?> — <?= e($d['telephone']) ?></li>
            <?php endforeach; ?>
        </ul>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="confirmer_doublon" id="confirmer_doublon" value="1">
            <label class="form-check-label" for="confirmer_doublon">Je confirme qu'il s'agit bien d'un nouvel étudiant.</label>
        </div>
    </div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger small">
        <?php foreach ($errors as $message): ?>
            <div><?= e($message) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="post" action="<?= e($action) ?>" class="card">
    <?= csrf_field() ?>
    <?php if ($estEdition): ?>
        <input type="hidden" name="id" value="<?= e($etudiant['id']) ?>">
    <?php endif; ?>
    <div class="card-body">
        <div class="form-section-title">Identité</div>
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label" for="prenom">Prénom *</label>
                <input class="form-control <?= isset($errors['prenom']) ? 'is-invalid' : '' ?>" type="text" id="prenom" name="prenom" value="<?= e(old('prenom', $etudiant['prenom'] ?? '')) ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="nom">Nom *</label>
                <input class="form-control <?= isset($errors['nom']) ? 'is-invalid' : '' ?>" type="text" id="nom" name="nom" value="<?= e(old('nom', $etudiant['nom'] ?? '')) ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="sexe">Sexe *</label>
                <select class="form-select <?= isset($errors['sexe']) ? 'is-invalid' : '' ?>" id="sexe" name="sexe" required>
                    <option value="">—</option>
                    <option value="M" <?= old('sexe', $etudiant['sexe'] ?? '') === 'M' ? 'selected' : '' ?>>Masculin</option>
                    <option value="F" <?= old('sexe', $etudiant['sexe'] ?? '') === 'F' ? 'selected' : '' ?>>Féminin</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="date_naissance">Date de naissance</label>
                <input class="form-control" type="date" id="date_naissance" name="date_naissance" value="<?= e(old('date_naissance', $etudiant['date_naissance'] ?? '')) ?>">
            </div>
            <div class="col-md-5">
                <label class="form-label" for="date_enregistrement">Date d'enregistrement *</label>
                <input class="form-control <?= isset($errors['date_enregistrement']) ? 'is-invalid' : '' ?>" type="date" id="date_enregistrement" name="date_enregistrement" value="<?= e(old('date_enregistrement', $etudiant['date_enregistrement'] ?? date('Y-m-d'))) ?>" required>
            </div>
        </div>

        <div class="form-section-title">Coordonnées</div>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="telephone">Téléphone *</label>
                <input class="form-control <?= isset($errors['telephone']) ? 'is-invalid' : '' ?>" type="tel" id="telephone" name="telephone" value="<?= e(old('telephone', $etudiant['telephone'] ?? '')) ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="adresse">Adresse</label>
                <input class="form-control" type="text" id="adresse" name="adresse" value="<?= e(old('adresse', $etudiant['adresse'] ?? '')) ?>">
            </div>
        </div>

    </div>
    <div class="card-footer d-flex justify-content-end gap-2">
        <a href="<?= e(url('etudiants')) ?>" class="btn btn-light">Annuler</a>
        <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg me-1"></i><?= e($estEdition ? 'Enregistrer les modifications' : 'Créer l\'étudiant') ?></button>
    </div>
</form>

<?php if ($estEdition): ?>
<div class="card mt-3">
    <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <div class="form-section-title mb-1">Statut du dossier</div>
            <div class="small text-muted">Statut actuel : <?= statut_badge((string) $etudiant['statut']) ?></div>
        </div>
        <form method="post" action="<?= e(url('etudiants.statut')) ?>" class="d-flex gap-2 align-items-center">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= e($etudiant['id']) ?>">
            <select class="form-select" name="statut" style="width:auto;">
                <?php foreach (['actif' => 'Actif', 'suspendu' => 'Suspendu', 'termine' => 'Terminé', 'archive' => 'Archivé'] as $k => $v): ?>
                    <option value="<?= e($k) ?>" <?= ($etudiant['statut'] ?? '') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-outline-secondary" type="submit">Mettre à jour</button>
        </form>
    </div>
</div>
<?php endif; ?>
