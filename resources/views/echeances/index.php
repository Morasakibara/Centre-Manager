<div class="page-head">
    <div>
        <h1>Échéances</h1>
        <p class="lead-sm">Suivi des échéances échues, à venir et de l'ensemble de l'échéancier.</p>
    </div>
</div>

<form class="filter-bar" method="get" action="<?= e(url('echeances')) ?>">
    <div class="row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label">Année académique</label>
            <select class="form-select" name="annee">
                <option value="">Toutes</option>
                <?php foreach ($annees as $a): ?>
                    <option value="<?= e($a['id']) ?>" <?= $annee_id === (int) $a['id'] ? 'selected' : '' ?>><?= e($a['libelle']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-8 d-flex gap-2 align-items-end">
            <button class="btn btn-primary" type="submit"><i class="bi bi-funnel me-1"></i>Filtrer</button>
            <ul class="nav nav-tabs ms-auto mb-0" role="tablist">
                <?php foreach (['en_retard' => 'En retard', 'a_venir' => 'À venir', 'toutes' => 'Toutes'] as $k => $v): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $vue === $k ? 'active' : '' ?>" href="<?= e(url('echeances', ['vue' => $k, 'annee' => $annee_id])) ?>"><?= e($v) ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</form>

<div class="card">
    <?php if ($lignes === []): ?>
        <div class="empty-state"><i class="bi bi-calendar-check"></i>Aucune échéance pour ces critères.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Étudiant</th>
                        <th>Formation</th>
                        <th>Échéance</th>
                        <th>Date limite</th>
                        <th class="text-end">Attendu</th>
                        <th class="text-end">Réglé</th>
                        <th class="text-end">Reste</th>
                        <th>État</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lignes as $e): ?>
                        <?php $regle = (float) ($e['montant_regle'] ?? 0); $reste = (float) $e['montant_attendu'] - $regle; ?>
                        <tr>
                            <td class="row-title"><?= e($e['nom'] . ' ' . $e['prenom']) ?><div class="row-sub mono"><?= e($e['matricule']) ?></div></td>
                            <td><?= e($e['formation_nom']) ?></td>
                            <td><?= e($e['libelle']) ?></td>
                            <td><?= e(date_fr((string) $e['date_echeance'])) ?></td>
                            <td class="text-end money"><?= e(argent($e['montant_attendu'])) ?></td>
                            <td class="text-end money text-success"><?= e(argent($regle)) ?></td>
                            <td class="text-end money <?= $reste > 0 ? 'text-danger' : 'text-success' ?>"><?= e(argent($reste)) ?></td>
                            <td><?= statut_badge($reste <= 0 ? 'solde' : ((string) $e['date_echeance'] < date('Y-m-d') ? 'en_retard' : 'a_venir')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
