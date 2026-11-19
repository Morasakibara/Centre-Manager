<?php
$annee = $annee ?? null;
$totauxAnnee = $totaux_annee ?? ['montant_total' => 0, 'total_paye' => 0, 'reste' => 0, 'nb_inscriptions' => 0, 'nb_soldees' => 0, 'nb_en_retard' => 0];
$totauxGlobal = $totaux_global ?? ['montant_total' => 0, 'total_paye' => 0, 'reste' => 0, 'nb_inscriptions' => 0];
$taux = $totauxGlobal['montant_total'] > 0 ? ($totauxGlobal['total_paye'] / $totauxGlobal['montant_total'] * 100) : 0;
$scripts = '<script src="' . e(asset('vendor/chartjs/chart.umd.min.js')) . '"></script>
<script>
(function () {
    if (typeof Chart === "undefined") return;
    var data = window.__DASHBOARD__ || {};
    var ctx1 = document.getElementById("chartMensuel");
    if (ctx1 && data.mensuel) {
        new Chart(ctx1, {
            type: "bar",
            data: {
                labels: data.mensuel.map(function (x) { return x.label; }),
                datasets: [{
                    label: "Encaissements",
                    data: data.mensuel.map(function (x) { return x.montant; }),
                    backgroundColor: "rgba(0, 0, 255, .75)",
                    hoverBackgroundColor: "rgba(255, 107, 0, .85)",
                    borderRadius: 6
                }]
            },
            options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
        });
    }
    var ctx2 = document.getElementById("chartFormations");
    if (ctx2 && data.repartition) {
        new Chart(ctx2, {
            type: "doughnut",
            data: {
                labels: Object.keys(data.repartition),
                datasets: [{
                    data: Object.values(data.repartition),
                    backgroundColor: ["#157347","#b02a37","#b97613","#0000FF","#0d5e83","#8a9aab"]
                }]
            },
            options: { responsive: true, plugins: { legend: { position: "bottom" } } }
        });
    }
})();
</script>';
?>

<div class="page-head">
    <div>
        <h1>Tableau de bord</h1>
        <p class="lead-sm">Vue d'ensemble de l'activité financière et pédagogique.</p>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card d-flex justify-content-between align-items-start gap-3">
            <div>
                <div class="stat-label">Étudiants</div>
                <div class="stat-value"><?= e(number_format((float) $total_etudiants, 0, ',', ' ')) ?></div>
                <div class="stat-hint"><?= e(number_format((float) $etudiants_actifs, 0, ',', ' ')) ?> actifs</div>
            </div>
            <span class="stat-icon"><i class="bi bi-people"></i></span>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card d-flex justify-content-between align-items-start gap-3">
            <div>
                <div class="stat-label">Inscriptions actives</div>
                <div class="stat-value"><?= e(number_format((float) $totauxGlobal['nb_inscriptions'], 0, ',', ' ')) ?></div>
                <div class="stat-hint"><?= e(number_format((float) $nb_inscriptions_annee, 0, ',', ' ')) ?> cette année</div>
            </div>
            <span class="stat-icon info"><i class="bi bi-file-earmark-text"></i></span>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card d-flex justify-content-between align-items-start gap-3">
            <div>
                <div class="stat-label">Montant attendu</div>
                <div class="stat-value money"><?= e(argent($totauxGlobal['montant_total'])) ?></div>
                <div class="stat-hint">Inscriptions actives</div>
            </div>
            <span class="stat-icon"><i class="bi bi-wallet2"></i></span>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card d-flex justify-content-between align-items-start gap-3">
            <div>
                <div class="stat-label">Total encaissé</div>
                <div class="stat-value money text-success"><?= e(argent($totauxGlobal['total_paye'])) ?></div>
                <div class="stat-hint">Toutes inscriptions actives</div>
            </div>
            <span class="stat-icon success"><i class="bi bi-check2-circle"></i></span>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card d-flex justify-content-between align-items-start gap-3">
            <div>
                <div class="stat-label">Solde restant</div>
                <div class="stat-value money text-danger"><?= e(argent($totauxGlobal['reste'])) ?></div>
                <div class="stat-hint">Toutes inscriptions actives</div>
            </div>
            <span class="stat-icon danger"><i class="bi bi-exclamation-circle"></i></span>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card">
            <div class="d-flex justify-content-between align-items-start gap-3">
                <div>
                    <div class="stat-label">Taux de règlement</div>
                    <div class="stat-value"><?= e(number_format($taux, 1, ',', ' ')) ?> %</div>
                </div>
                <span class="stat-icon"><i class="bi bi-graph-up-arrow"></i></span>
            </div>
            <div class="progress progress-thin mt-2" role="progressbar"
                 aria-label="Taux de règlement" aria-valuenow="<?= e(number_format($taux, 1, '.', '')) ?>" aria-valuemin="0" aria-valuemax="100">
                <div class="progress-bar" style="width:<?= e(number_format(min(100, max(0, $taux)), 1, '.', '')) ?>%"></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <a href="<?= e(url('echeances', ['vue' => 'en_retard'])) ?>" class="card stat-card d-flex justify-content-between align-items-start gap-3 text-reset">
            <div>
                <div class="stat-label">Échéances en retard</div>
                <div class="stat-value text-danger"><?= e(number_format((float) count($echeances_urgentes ?? []), 0, ',', ' ')) ?></div>
                <div class="stat-hint">À relancer en priorité</div>
            </div>
            <span class="stat-icon danger"><i class="bi bi-alarm"></i></span>
        </a>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card d-flex justify-content-between align-items-start gap-3">
            <div>
                <div class="stat-label">Recettes du mois</div>
                <div class="stat-value money text-success"><?= e(argent($recettes_mois ?? 0)) ?></div>
                <div class="stat-hint">Aujourd'hui : <?= e(argent($recettes_jour ?? 0)) ?></div>
            </div>
            <span class="stat-icon success"><i class="bi bi-cash-coin"></i></span>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">Encaissements par mois</div>
            <div class="card-body">
                <canvas id="chartMensuel" height="90"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">Répartition par statut (année)</div>
            <div class="card-body">
                <canvas id="chartFormations" height="160"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header">Derniers paiements</div>
    <?php if (empty($derniers_paiements)): ?>
        <div class="empty-state py-4"><i class="bi bi-cash"></i>Aucun paiement.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>N° reçu</th>
                        <th>Étudiant</th>
                        <th>Formation</th>
                        <th class="text-end">Montant</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($derniers_paiements as $p): ?>
                        <tr>
                            <td class="row-sub"><?= e(date_fr((string) $p['date_paiement'], true)) ?></td>
                            <td class="mono"><?= e($p['numero_recu']) ?></td>
                            <td><?= e($p['nom'] . ' ' . $p['prenom']) ?></td>
                            <td><?= e($p['formation_nom']) ?></td>
                            <td class="text-end money-strong"><?= e(argent($p['montant'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
window.__DASHBOARD__ = <?= json_encode([
    'mensuel' => $serie_recettes ?? [],
    'repartition' => $repartition ?? [],
], JSON_UNESCAPED_UNICODE) ?>;
</script>
