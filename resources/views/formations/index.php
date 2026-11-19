<?php use App\Core\Auth; ?>
<div class="page-head">
    <div>
        <h1>Formations</h1>
        <p class="lead-sm">Catalogue, tarifs et suivi des inscriptions.</p>
    </div>
    <?php if (Auth::isAdmin()): ?>
        <a href="<?= e(url('formations.create')) ?>" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Nouvelle formation</a>
    <?php endif; ?>
</div>

<div class="card">
    <?php if ($formations === []): ?>
        <div class="empty-state"><i class="bi bi-journal-bookmark"></i>Aucune formation.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Formation</th>
                        <th>Description</th>
                        <th class="text-end">Frais</th>
                        <th>Inscriptions</th>
                        <th>Statut</th>
                        <?php if (Auth::isAdmin()): ?>
                            <th class="text-end">Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($formations as $f): ?>
                        <tr>
                            <td class="row-title"><?= e($f['nom']) ?></td>
                            <td class="row-sub" style="max-width:360px;white-space:normal;"><?= e(mb_strimwidth((string) ($f['description'] ?? ''), 0, 110, '…')) ?></td>
                            <td class="text-end money-strong"><?= e(argent($f['frais_formation'])) ?></td>
                            <td><?= e((string) ($f['nb_inscriptions'] ?? 0)) ?></td>
                            <td><?= statut_badge((string) $f['statut']) ?></td>
                            <?php if (Auth::isAdmin()): ?>
                                <td class="text-end">
                                    <a href="<?= e(url('formations.edit', ['id' => $f['id']])) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                                    <form method="post" action="<?= e(url('formations.statut')) ?>" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= e($f['id']) ?>">
                                        <input type="hidden" name="statut" value="<?= e($f['statut'] === 'actif' ? 'inactif' : 'actif') ?>">
                                        <button class="btn btn-sm btn-outline-<?= $f['statut'] === 'actif' ? 'warning' : 'success' ?>" type="submit">
                                            <i class="bi bi-<?= $f['statut'] === 'actif' ? 'pause' : 'play' ?>"></i>
                                        </button>
                                    </form>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
