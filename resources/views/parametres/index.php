<div class="page-head">
    <div>
        <h1>Paramètres</h1>
        <p class="lead-sm">Configuration générale du centre et de l'application.</p>
    </div>
</div>

<form method="post" action="<?= e(url('parametres.update')) ?>" class="card mb-3">
    <?= csrf_field() ?>
    <div class="card-body">
        <div class="form-section-title">Identité du centre</div>
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <label class="form-label" for="centre_nom">Nom du centre</label>
                <input class="form-control" type="text" id="centre_nom" name="centre_nom" value="<?= e($parametres['centre_nom']) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="centre_telephone">Téléphone</label>
                <input class="form-control" type="text" id="centre_telephone" name="centre_telephone" value="<?= e($parametres['centre_telephone']) ?>">
            </div>
            <div class="col-12">
                <label class="form-label" for="centre_adresse">Adresse</label>
                <input class="form-control" type="text" id="centre_adresse" name="centre_adresse" value="<?= e($parametres['centre_adresse']) ?>">
            </div>
        </div>

        <div class="form-section-title">Application</div>
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <label class="form-label" for="devise">Devise</label>
                <input class="form-control" type="text" id="devise" name="devise" value="<?= e($parametres['devise'] ?? 'XAF') ?>" readonly>
                <div class="form-text">La devise est fixée par la configuration (.env).</div>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="matricule_prefixe">Préfixe des matricules</label>
                <input class="form-control" type="text" id="matricule_prefixe" name="matricule_prefixe" value="<?= e($parametres['matricule_prefixe'] ?? 'CF') ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="centre_email">Courriel du centre</label>
                <input class="form-control" type="email" id="centre_email" name="centre_email" value="<?= e($parametres['centre_email'] ?? '') ?>">
            </div>
        </div>

        <div class="form-section-title">Sauvegarde</div>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="frequence_sauvegarde">Fréquence</label>
                <select class="form-select" id="frequence_sauvegarde" name="frequence_sauvegarde">
                    <?php foreach (['quotidienne' => 'Quotidienne', 'hebdomadaire' => 'Hebdomadaire', 'manuelle' => 'Manuelle'] as $k => $v): ?>
                        <option value="<?= e($k) ?>" <?= ($parametres['frequence_sauvegarde'] ?? '') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>
    <div class="card-footer d-flex justify-content-end">
        <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg me-1"></i>Enregistrer les paramètres</button>
    </div>
</form>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Sauvegarde et restauration</span>
        <div class="d-flex gap-2">
            <form method="post" action="<?= e(url('parametres.backup')) ?>">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-outline-primary" type="submit"><i class="bi bi-download me-1"></i>Créer une sauvegarde</button>
            </form>
        </div>
    </div>
    <div class="card-body">
        <?php if ($sauvegardes === []): ?>
            <div class="empty-state py-3"><i class="bi bi-archive"></i>Aucune sauvegarde disponible.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Fichier</th>
                            <th>Taille</th>
                            <th>Créé le</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sauvegardes as $s): ?>
                            <tr>
                                <td class="mono small"><?= e($s['nom']) ?></td>
                                <td><?= e(taille_lisible((int) $s['taille'])) ?></td>
                                <td><?= e(date_fr((string) $s['date'])) ?></td>
                                <td class="text-end">
                                    <a href="<?= e(url('parametres.telecharger_sauvegarde', ['fichier' => $s['nom']])) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-download"></i></a>
                                    <form method="post" action="<?= e(url('parametres.restaurer')) ?>" class="d-inline" data-confirm="Restaurer cette sauvegarde ? Les données actuelles seront remplacées.">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="fichier" value="<?= e($s['nom']) ?>">
                                        <button class="btn btn-sm btn-outline-warning" type="submit"><i class="bi bi-arrow-counterclockwise"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <form method="post" action="<?= e(url('parametres.restaurer_upload')) ?>" enctype="multipart/form-data" class="mt-3">
            <?= csrf_field() ?>
            <div class="row g-2 align-items-end">
                <div class="col-md-8">
                    <label class="form-label">Restaurer depuis un fichier SQL</label>
                    <input class="form-control" type="file" name="fichier_sql" accept=".sql" required>
                </div>
                <div class="col-md-4">
                    <button class="btn btn-outline-warning w-100" type="submit"><i class="bi bi-upload me-1"></i>Restaurer</button>
                </div>
            </div>
        </form>
    </div>
</div>
