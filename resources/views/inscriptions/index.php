<div class="page-head">
    <div>
        <h1>Inscriptions</h1>
        <p class="lead-sm">Dossiers financiers autonomes par étudiant et année.</p>
    </div>
    <a href="<?= e(url('inscriptions.create')) ?>" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Nouvelle inscription</a>
</div>

<form class="filter-bar" method="get" action="<?= e(url('inscriptions')) ?>">
    <div class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label">Recherche</label>
            <input class="form-control" type="search" name="q" value="<?= e($filtres['q']) ?>" placeholder="Matricule ou nom">
        </div>
        <div class="col-md-2">
            <label class="form-label">Année</label>
            <select class="form-select" name="annee">
                <option value="">Toutes</option>
                <?php foreach ($annees as $a): ?>
                    <option value="<?= e($a['id']) ?>" <?= $filtres['annee'] === (string) $a['id'] ? 'selected' : '' ?>><?= e($a['libelle']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Formation</label>
            <select class="form-select" name="formation">
                <option value="">Toutes</option>
                <?php foreach ($formations as $f): ?>
                    <option value="<?= e($f['id']) ?>" <?= $filtres['formation'] === (string) $f['id'] ? 'selected' : '' ?>><?= e($f['nom']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Mode</label>
            <select class="form-select" name="mode">
                <option value="">Tous</option>
                <option value="libre" <?= $filtres['mode'] === 'libre' ? 'selected' : '' ?>>Libre</option>
                <option value="tranches" <?= $filtres['mode'] === 'tranches' ? 'selected' : '' ?>>Tranches</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Statut financier</label>
            <select class="form-select" name="statut_financier">
                <option value="">Tous</option>
                <?php foreach (['solde' => 'Soldé', 'en_retard' => 'En retard', 'partiel' => 'Partiellement réglé', 'a_jour' => 'À jour', 'a_venir' => 'À venir', 'non_solde' => 'Non soldé'] as $k => $v): ?>
                    <option value="<?= e($k) ?>" <?= $filtres['statut_financier'] === $k ? 'selected' : '' ?>><?= e($v) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 d-flex gap-2 mt-2">
            <button class="btn btn-primary" type="submit"><i class="bi bi-search me-1"></i>Filtrer</button>
            <a href="<?= e(url('inscriptions')) ?>" class="btn btn-light">Réinitialiser</a>
        </div>
    </div>
</form>

<div class="card">
    <?php if ($inscriptions === []): ?>
        <div class="empty-state"><i class="bi bi-file-earmark"></i>Aucune inscription correspondant aux filtres.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Étudiant</th>
                        <th>Formation</th>
                        <th>Année</th>
                        <th>Mode</th>
                        <th class="text-end">Attendu</th>
                        <th class="text-end">Payé</th>
                        <th class="text-end">Reste</th>
                        <th>Statut</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($inscriptions as $ins): ?>
                        <?php $sit = $situations[$ins['id']] ?? null; ?>
                        <tr>
                            <td class="row-title"><?= e($ins['etudiant_prenom'] . ' ' . $ins['etudiant_nom']) ?><div class="row-sub mono"><?= e($ins['matricule']) ?></div></td>
                            <td><?= e($ins['formation_nom']) ?></td>
                            <td><?= e($ins['annee_libelle']) ?></td>
                            <td><?= statut_badge((string) $ins['mode_paiement']) ?></td>
                            <td class="text-end money"><?= e(argent($sit['montant_total'] ?? $ins['montant_total'])) ?></td>
                            <td class="text-end money text-success"><?= e(argent($sit['total_paye'] ?? 0)) ?></td>
                            <td class="text-end money <?= ($sit['reste'] ?? 0) > 0 ? 'text-danger' : 'text-success' ?>"><?= e(argent($sit['reste'] ?? 0)) ?></td>
                            <td><?= statut_badge($sit['statut'] ?? 'non_solde') ?></td>
                            <td class="text-end">
                                <a href="<?= e(url('inscriptions.show', ['id' => $ins['id']])) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
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
                                <a class="page-link" href="<?= e(url('inscriptions', array_merge($filtres, ['page' => $i]))) ?>"><?= e((string) $i) ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
