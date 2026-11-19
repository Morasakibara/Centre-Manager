<div class="page-head">
    <div>
        <h1>Étudiants</h1>
        <p class="lead-sm">Dossiers, recherche et suivi financier.</p>
    </div>
    <a href="<?= e(url('etudiants.create')) ?>" class="btn btn-primary"><i class="bi bi-person-plus me-1"></i>Nouvel étudiant</a>
</div>

<form class="filter-bar" method="get" action="<?= e(url('etudiants')) ?>">
    <div class="row g-2 align-items-end">
        <div class="col-md-6">
            <label class="form-label">Recherche</label>
            <input class="form-control" type="search" name="q" value="<?= e($filtres['q']) ?>" placeholder="Matricule, nom, prénom ou téléphone">
        </div>
        <div class="col-md-3">
            <label class="form-label">Statut</label>
            <select class="form-select" name="statut">
                <option value="">Tous (sauf archivés)</option>
                <?php foreach (['actif' => 'Actif', 'suspendu' => 'Suspendu', 'termine' => 'Terminé', 'archive' => 'Archivé'] as $k => $v): ?>
                    <option value="<?= e($k) ?>" <?= $filtres['statut'] === $k ? 'selected' : '' ?>><?= e($v) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button class="btn btn-primary flex-fill" type="submit"><i class="bi bi-search me-1"></i>Filtrer</button>
            <a href="<?= e(url('etudiants')) ?>" class="btn btn-light">Réinitialiser</a>
        </div>
    </div>
</form>

<div class="card">
    <?php if ($etudiants === []): ?>
        <div class="empty-state"><i class="bi bi-people"></i>Aucun étudiant trouvé.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Matricule</th>
                        <th>Étudiant</th>
                        <th>Téléphone</th>
                        <th>Statut</th>
                        <th>Inscriptions</th>
                        <th class="text-end">Reste dû</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($etudiants as $e): ?>
                        <?php $resume = $resumes[$e['id']] ?? ['nb' => 0, 'reste' => 0.0, 'statut' => 'solde']; ?>
                        <tr>
                            <td class="mono"><?= e($e['matricule']) ?></td>
                            <td class="row-title"><?= e($e['prenom'] . ' ' . $e['nom']) ?><div class="row-sub"><?= e($e['sexe'] === 'M' ? 'Homme' : 'Femme') ?> · <?= e(date_fr((string) $e['date_naissance'])) ?></div></td>
                            <td><?= e($e['telephone']) ?></td>
                            <td><?= statut_badge((string) $e['statut']) ?></td>
                            <td><?= e((string) $resume['nb']) ?></td>
                            <td class="text-end money <?= $resume['reste'] > 0 ? 'text-danger' : 'text-success' ?>"><?= e(argent($resume['reste'])) ?></td>
                            <td class="text-end">
                                <a href="<?= e(url('etudiants.show', ['id' => $e['id']])) ?>" class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="Fiche"><i class="bi bi-eye"></i></a>
                                <a href="<?= e(url('etudiants.edit', ['id' => $e['id']])) ?>" class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" title="Modifier"><i class="bi bi-pencil"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        $nbPages = (int) ceil($total / $par_page);
        if ($nbPages > 1): ?>
            <div class="card-footer d-flex justify-content-between align-items-center">
                <span class="small text-muted"><?= e((string) $total) ?> résultats</span>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <?php for ($i = 1; $i <= $nbPages; $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="<?= e(url('etudiants', ['q' => $filtres['q'], 'statut' => $filtres['statut'], 'page' => $i])) ?>"><?= e((string) $i) ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
