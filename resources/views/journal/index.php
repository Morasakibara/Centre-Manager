<div class="page-head">
    <div>
        <h1>Journal d'audit</h1>
        <p class="lead-sm">Traçabilité des actions sensibles et modifications.</p>
    </div>
</div>

<form class="filter-bar" method="get" action="<?= e(url('journal')) ?>">
    <div class="row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label">Recherche</label>
            <input class="form-control" type="search" name="q" value="<?= e($filtres['q']) ?>" placeholder="Action, détail…">
        </div>
        <div class="col-md-2">
            <label class="form-label">Utilisateur</label>
            <select class="form-select" name="utilisateur">
                <option value="">Tous</option>
                <?php foreach ($utilisateurs as $u): ?>
                    <option value="<?= e($u['id']) ?>" <?= $filtres['utilisateur'] === (string) $u['id'] ? 'selected' : '' ?>><?= e($u['nom_complet']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Du</label>
            <input class="form-control" type="date" name="debut" value="<?= e($filtres['debut']) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label">Au</label>
            <input class="form-control" type="date" name="fin" value="<?= e($filtres['fin']) ?>">
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button class="btn btn-primary flex-grow-1" type="submit"><i class="bi bi-search me-1"></i></button>
            <a href="<?= e(url('journal')) ?>" class="btn btn-light">Réinit.</a>
        </div>
    </div>
</form>

<div class="card">
    <?php if ($entrees === []): ?>
        <div class="empty-state"><i class="bi bi-journal-text"></i>Aucune entrée.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Utilisateur</th>
                        <th>Action</th>
                        <th>Entité</th>
                        <th>Détail</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($entrees as $l): ?>
                        <tr>
                            <td class="row-sub"><?= e(date_fr((string) $l['date_action'], true)) ?></td>
                            <td><?= e($l['utilisateur_nom'] ?? '—') ?></td>
                            <td><span class="badge bg-light text-dark"><?= e($l['action']) ?></span></td>
                            <td><?= e(($l['entite'] ?? '') . ($l['entite_id'] ? ' #' . $l['entite_id'] : '')) ?></td>
                            <td class="row-sub" style="max-width:360px;white-space:normal;"><?= e($l['detail'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        $nbPages = (int) ceil($total / $par_page);
        if ($nbPages > 1): ?>
            <div class="card-footer d-flex justify-content-between align-items-center">
                <span class="small text-muted"><?= e((string) $total) ?> entrées</span>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <?php for ($i = 1; $i <= $nbPages; $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="<?= e(url('journal', array_merge($filtres, ['page' => $i]))) ?>"><?= e((string) $i) ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
