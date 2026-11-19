<div class="page-head no-print">
    <div>
        <h1>Reçu de paiement</h1>
        <p class="lead-sm"><?= e($paiement['numero_recu']) ?></p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= e(url('paiements.recu', ['id' => $paiement['id'], 'format' => 'pdf'])) ?>" class="btn btn-primary">
            <i class="bi bi-file-earmark-pdf me-1"></i>Télécharger en PDF
        </a>
        <button class="btn btn-outline-secondary" type="button" data-print>
            <i class="bi bi-printer me-1"></i>Imprimer
        </button>
    </div>
</div>

<div class="card" id="recu">
    <div class="card-body">
        <div class="text-center mb-4">
            <img class="receipt-logo mb-3" src="<?= e(asset('img/logo-pharaonacademia.png')) ?>" alt="Logo PharaonAcademia">
            <h2 class="h5 mb-0">PharaonAcademia</h2>
            <div class="small text-muted"><?= e((string) config('centre.adresse', '')) ?> · <?= e((string) config('centre.telephone', '')) ?></div>
            <div class="mt-3"><strong>REÇU DE PAIEMENT</strong></div>
            <div class="small text-muted">N° <?= e($paiement['numero_recu']) ?> · Émis le <?= e(date_fr((string) $paiement['date_paiement'], true)) ?></div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="form-section-title">Étudiant</div>
                <ul class="detail-list">
                    <li><span class="label">Nom et prénom</span><span class="value"><?= e($paiement['nom'] . ' ' . $paiement['prenom']) ?></span></li>
                    <li><span class="label">Matricule</span><span class="value mono"><?= e($paiement['matricule']) ?></span></li>
                    <li><span class="label">Téléphone</span><span class="value"><?= e($paiement['telephone']) ?></span></li>
                </ul>
            </div>
            <div class="col-md-6">
                <div class="form-section-title">Inscription</div>
                <ul class="detail-list">
                    <li><span class="label">Formation</span><span class="value"><?= e($paiement['formation_nom']) ?></span></li>
                    <li><span class="label">Année</span><span class="value"><?= e($paiement['annee_libelle']) ?></span></li>
                    <li><span class="label">Montant convenu</span><span class="value money"><?= e(argent($paiement['montant_total'])) ?></span></li>
                    <li><span class="label">Solde après ce paiement</span><span class="value money"><?= e(argent($situation['reste'])) ?></span></li>
                </ul>
            </div>
        </div>

        <table class="table table-bordered">
            <tbody>
                <tr>
                    <th style="width:40%;">Date du paiement</th>
                    <td><?= e(date_fr((string) $paiement['date_paiement'], true)) ?></td>
                </tr>
                <tr>
                    <th>Mode de paiement</th>
                    <td><?= e(mode_paiement_label((string) $paiement['mode_paiement'])) ?></td>
                </tr>
                <?php if (!empty($paiement['reference'])): ?>
                    <tr>
                        <th>Référence</th>
                        <td><?= e($paiement['reference']) ?></td>
                    </tr>
                <?php endif; ?>
                <tr>
                    <th>Montant payé</th>
                    <td class="money-strong"><?= e(argent($paiement['montant'])) ?></td>
                </tr>
                <?php if (!empty($paiement['observation'])): ?>
                    <tr>
                        <th>Observation</th>
                        <td><?= e($paiement['observation']) ?></td>
                    </tr>
                <?php endif; ?>
                <tr>
                    <th>Encaissé par</th>
                    <td><?= e($paiement['encaisse_par']) ?></td>
                </tr>
            </tbody>
        </table>

        <div class="text-center small text-muted mt-4">
            Merci pour votre règlement. Ce reçu est généré automatiquement par PharaonAcademia.<br>
            Solution développée par NAYGAL.<br>
            <?= e(date('d/m/Y H:i')) ?>
        </div>
    </div>
</div>

<style>
    .receipt-logo { display: block; width: min(100%, 320px); height: auto; margin: 0 auto; }
</style>
