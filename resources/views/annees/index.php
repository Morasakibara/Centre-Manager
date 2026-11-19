<?php use App\Core\Auth; ?>
<div class="page-head">
    <div>
        <h1>Années académiques</h1>
        <p class="lead-sm">Périodes de formation et clôture des années.</p>
    </div>
</div>

<?php if (Auth::isAdmin()): ?>
    <div class="card mb-3">
        <div class="card-header">Nouvelle année académique</div>
        <div class="card-body">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger small">
                    <?php foreach ($errors as $message): ?><div><?= e($message) ?></div><?php endforeach; ?>
                </div>
            <?php endif; ?>
            <form method="post" action="<?= e(url('annees.store')) ?>" class="row g-2 align-items-end">
                <?= csrf_field() ?>
                <div class="col-md-3">
                    <label class="form-label">Libellé *</label>
                    <input class="form-control" type="text" name="libelle" placeholder="2026-2027" value="<?= e(old('libelle')) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date de début *</label>
                    <input class="form-control" type="date" name="date_debut" value="<?= e(old('date_debut')) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date de fin *</label>
                    <input class="form-control" type="date" name="date_fin" value="<?= e(old('date_fin')) ?>" required>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-primary w-100" type="submit"><i class="bi bi-plus-circle me-1"></i>Créer</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Libellé</th>
                    <th>Période</th>
                    <th>Statut</th>
                    <th>Inscriptions</th>
                    <?php if (Auth::isAdmin()): ?>
                        <th class="text-end">Actions</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($annees as $a): ?>
                    <tr>
                        <td class="row-title"><?= e($a['libelle']) ?></td>
                        <td><?= e(date_fr((string) $a['date_debut'])) ?> → <?= e(date_fr((string) $a['date_fin'])) ?></td>
                        <td><?= statut_badge((string) $a['statut']) ?></td>
                        <td><?= e((string) ($stats[$a['id']] ?? 0)) ?></td>
                        <?php if (Auth::isAdmin()): ?>
                            <td class="text-end">
                                <?php if ($a['statut'] === 'ouverte'): ?>
                                    <form method="post" action="<?= e(url('annees.cloturer')) ?>" class="d-inline" data-confirm="Clôturer cette année ? Les modifications ordinaires seront bloquées.">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= e($a['id']) ?>">
                                        <button class="btn btn-sm btn-outline-warning" type="submit"><i class="bi bi-lock me-1"></i>Clôturer</button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" action="<?= e(url('annees.rouvrir')) ?>" class="d-inline" data-confirm="Réouvrir cette année ? L'opération est tracée dans le journal.">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= e($a['id']) ?>">
                                        <button class="btn btn-sm btn-outline-success" type="submit"><i class="bi bi-unlock me-1"></i>Réouvrir</button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($a['statut'] === 'ouverte'): ?>
                                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#modalAnnee<?= e($a['id']) ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <div class="modal fade" id="modalAnnee<?= e($a['id']) ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <form class="modal-content" method="post" action="<?= e(url('annees.update')) ?>">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id" value="<?= e($a['id']) ?>">
                                                <div class="modal-header">
                                                    <h5 class="modal-title h6">Modifier l'année</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label class="form-label">Libellé</label>
                                                        <input class="form-control" type="text" name="libelle" value="<?= e($a['libelle']) ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Date de début</label>
                                                        <input class="form-control" type="date" name="date_debut" value="<?= e($a['date_debut']) ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Date de fin</label>
                                                        <input class="form-control" type="date" name="date_fin" value="<?= e($a['date_fin']) ?>" required>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                                                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
