<?php use App\Core\Auth; ?>
<div class="page-head">
    <div>
        <h1>Paiements</h1>
        <p class="lead-sm">Historique des versements et annulations tracées.</p>
    </div>
    <a href="<?= e(url('paiements.create')) ?>" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Enregistrer un paiement</a>
</div>

<form class="filter-bar" method="get" action="<?= e(url('paiements')) ?>">
    <div class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label">Recherche</label>
            <input class="form-control" type="search" name="q" value="<?= e($filtres['q']) ?>" placeholder="Matricule, nom, reçu, référence">
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
                <?php foreach ($modes as $k => $v): ?>
                    <option value="<?= e($k) ?>" <?= $filtres['mode'] === $k ? 'selected' : '' ?>><?= e($v) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-1">
            <label class="form-label">Statut</label>
            <select class="form-select" name="statut">
                <option value="">Tous</option>
                <option value="valide" <?= $filtres['statut'] === 'valide' ? 'selected' : '' ?>>Validé</option>
                <option value="annule" <?= $filtres['statut'] === 'annule' ? 'selected' : '' ?>>Annulé</option>
            </select>
        </div>
        <div class="col-md-1">
            <label class="form-label">Du</label>
            <input class="form-control" type="date" name="debut" value="<?= e($filtres['debut']) ?>">
        </div>
        <div class="col-md-1">
            <label class="form-label">Au</label>
            <input class="form-control" type="date" name="fin" value="<?= e($filtres['fin']) ?>">
        </div>
        <div class="col-12 d-flex gap-2 mt-2">
            <button class="btn btn-primary" type="submit"><i class="bi bi-search me-1"></i>Filtrer</button>
            <a href="<?= e(url('paiements')) ?>" class="btn btn-light">Réinitialiser</a>
        </div>
    </div>
</form>

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card stat-card">
            <div class="stat-label">Paiements listés</div>
            <div class="stat-value"><?= e((string) $totaux['nb']) ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card">
            <div class="stat-label">Total validé</div>
            <div class="stat-value money text-success"><?= e(argent($totaux['valide'])) ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card">
            <div class="stat-label">Total annulé</div>
            <div class="stat-value money text-danger"><?= e(argent($totaux['annule'])) ?></div>
        </div>
    </div>
</div>

<div class="card">
    <?php if ($paiements === []): ?>
        <div class="empty-state"><i class="bi bi-cash"></i>Aucun paiement.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>N° reçu</th>
                        <th>Étudiant</th>
                        <th>Formation</th>
                        <th>Mode</th>
                        <th class="text-end">Montant</th>
                        <th>Statut</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($paiements as $p): ?>
                        <tr>
                            <td class="row-sub"><?= e(date_fr((string) $p['date_paiement'], true)) ?></td>
                            <td class="mono"><?= e((string) $p['numero_recu']) ?></td>
                            <td class="row-title"><?= e($p['nom'] . ' ' . $p['prenom']) ?><div class="row-sub"><?= e($p['matricule']) ?></div></td>
                            <td><?= e($p['formation_nom']) ?></td>
                            <td><?= statut_badge((string) $p['mode_paiement']) ?></td>
                            <td class="text-end money-strong"><?= e(argent($p['montant'])) ?></td>
                            <td><?= statut_badge((string) $p['statut']) ?></td>
                            <td class="text-end">
                                <a href="<?= e(url('paiements.recu', ['id' => $p['id']])) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-receipt"></i></a>
                                <?php if ($p['statut'] === 'valide' && Auth::isAdmin()): ?>
                                    <button class="btn btn-sm btn-outline-danger" type="button" data-bs-toggle="modal" data-bs-target="#modalAnnPaiement<?= e($p['id']) ?>">
                                        <i class="bi bi-x"></i>
                                    </button>
                                <?php endif; ?>
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
                                <a class="page-link" href="<?= e(url('paiements', array_merge($filtres, ['page' => $i]))) ?>"><?= e((string) $i) ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php if (Auth::isAdmin()): ?>
    <?php foreach ($paiements as $p): ?>
        <?php if ($p['statut'] !== 'valide') continue; ?>
        <div class="modal fade" id="modalAnnPaiement<?= e($p['id']) ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form class="modal-content" method="post" action="<?= e(url('paiements.annuler')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= e($p['id']) ?>">
                    <div class="modal-header">
                        <h5 class="modal-title h6">Annuler le paiement</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="small text-muted">Paiement de <?= e(argent($p['montant'])) ?> du <?= e(date_fr((string) $p['date_paiement'])) ?> — <?= e($p['nom'] . ' ' . $p['prenom']) ?>.</p>
                        <label class="form-label">Motif d'annulation *</label>
                        <textarea class="form-control" name="motif" rows="3" required minlength="5"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-danger">Annuler le paiement</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
