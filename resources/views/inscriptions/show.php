<?php use App\Core\Auth; ?>
<div class="page-head">
    <div>
        <h1>Inscription #<?= e($inscription['id']) ?></h1>
        <p class="lead-sm">
            <?= e($inscription['etudiant_prenom'] . ' ' . $inscription['etudiant_nom']) ?> ·
            <span class="mono"><?= e($inscription['matricule']) ?></span> ·
            <?= e($inscription['formation_nom']) ?> ·
            <?= e($inscription['annee_libelle']) ?>
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= e(url('paiements.create', ['inscription_id' => $inscription['id']])) ?>" class="btn btn-primary"><i class="bi bi-cash-coin me-1"></i>Encaisser</a>
        <?php if (Auth::isAdmin()): ?>
            <button class="btn btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#modalCloture">
                <i class="bi bi-check2-circle me-1"></i>Clôturer
            </button>
            <button class="btn btn-outline-danger" type="button" data-bs-toggle="modal" data-bs-target="#modalAnnulation">
                <i class="bi bi-x-circle me-1"></i>Annuler
            </button>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header">Détails de l'inscription</div>
            <div class="card-body">
                <ul class="detail-list">
                    <li><span class="label">Étudiant</span><span class="value"><?= e($inscription['etudiant_prenom'] . ' ' . $inscription['etudiant_nom']) ?></span></li>
                    <li><span class="label">Formation</span><span class="value"><?= e($inscription['formation_nom']) ?></span></li>
                    <li><span class="label">Année</span><span class="value"><?= e($inscription['annee_libelle']) ?></span></li>
                    <li><span class="label">Date d'inscription</span><span class="value"><?= e(date_fr((string) $inscription['date_inscription'])) ?></span></li>
                    <li><span class="label">Mode</span><span class="value"><?= statut_badge((string) $inscription['mode_paiement']) ?></span></li>
                    <li><span class="label">Statut</span><span class="value"><?= statut_badge((string) $inscription['statut']) ?></span></li>
                    <li><span class="label">Montant convenu</span><span class="value money"><?= e(argent($inscription['montant_total'])) ?></span></li>
                    <li><span class="label">Tarif initial de la formation</span><span class="value money"><?= e(argent($inscription['montant_initial'])) ?></span></li>
                    <?php if (!empty($inscription['observation'])): ?>
                        <li><span class="label">Observation</span><span class="value"><?= e($inscription['observation']) ?></span></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">Situation financière</div>
            <div class="card-body">
                <?php
                $sit = $situation;
                $pourcentage = (float) $sit['pourcentage'];
                ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between small mb-1">
                        <span class="text-muted">Taux de règlement</span>
                        <strong><?= e(number_format($pourcentage, 1, ',', ' ')) ?> %</strong>
                    </div>
                    <div class="progress progress-thin">
                        <div class="progress-bar bg-success" style="width: <?= e((string) min(100, $pourcentage)) ?>%"></div>
                    </div>
                </div>
                <ul class="detail-list">
                    <li><span class="label">Montant total</span><span class="value money"><?= e(argent($sit['montant_total'])) ?></span></li>
                    <li><span class="label">Total payé</span><span class="value money text-success"><?= e(argent($sit['total_paye'])) ?></span></li>
                    <li><span class="label">Solde restant</span><span class="value money <?= $sit['reste'] > 0 ? 'text-danger' : 'text-success' ?>"><?= e(argent($sit['reste'])) ?></span></li>
                    <li><span class="label">Statut financier</span><span class="value"><?= statut_badge($sit['statut']) ?></span></li>
                    <?php if ($sit['nb_retard'] > 0): ?>
                        <li><span class="label">Montant en retard</span><span class="value money text-danger"><?= e(argent($sit['montant_retard'])) ?></span></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Ajouter une échéance</span>
            </div>
            <div class="card-body">
                <form method="post" action="<?= e(url('echeances.store')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="inscription_id" value="<?= e($inscription['id']) ?>">
                    <div class="mb-2">
                        <input class="form-control form-control-sm" type="text" name="libelle" placeholder="Libellé (ex. Tranche 2)">
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <input class="form-control form-control-sm" type="number" name="montant_attendu" min="0" step="0.01" placeholder="Montant" required>
                        </div>
                        <div class="col-6">
                            <input class="form-control form-control-sm" type="date" name="date_echeance" required>
                        </div>
                    </div>
                    <button class="btn btn-sm btn-outline-primary w-100" type="submit"><i class="bi bi-plus"></i> Ajouter</button>
                </form>
                <?php if ($inscription['mode_paiement'] === 'libre'): ?>
                    <div class="form-text mt-2">Inscription en paiement libre : l'échéancier est optionnel.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <?php if ($inscription['mode_paiement'] === 'tranches' || $echeances !== []): ?>
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Échéancier</span>
                    <span class="small text-muted">FIFO pour la répartition des versements</span>
                </div>
                <?php if ($echeances === []): ?>
                    <div class="empty-state py-4"><i class="bi bi-calendar"></i>Aucune échéance définie.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Libellé</th>
                                    <th>Date limite</th>
                                    <th class="text-end">Attendu</th>
                                    <th class="text-end">Réglé</th>
                                    <th class="text-end">Reste</th>
                                    <th>État</th>
                                    <?php if (Auth::isAdmin()): ?>
                                        <th></th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($echeances as $e): ?>
                                    <?php $regle = (float) $e['montant_regle']; $reste = (float) $e['montant_attendu'] - $regle; ?>
                                    <tr>
                                        <td class="row-title"><?= e($e['libelle']) ?></td>
                                        <td><?= e(date_fr((string) $e['date_echeance'])) ?></td>
                                        <td class="text-end money"><?= e(argent($e['montant_attendu'])) ?></td>
                                        <td class="text-end money text-success"><?= e(argent($regle)) ?></td>
                                        <td class="text-end money <?= $reste > 0 ? 'text-danger' : 'text-success' ?>"><?= e(argent($reste)) ?></td>
                                        <td><?= statut_badge($reste <= 0 ? 'solde' : ((string) $e['date_echeance'] < date('Y-m-d') ? 'en_retard' : 'a_venir')) ?></td>
                                        <?php if (Auth::isAdmin()): ?>
                                            <td class="text-end">
                                                <form method="post" action="<?= e(url('echeances.delete')) ?>" class="d-inline" data-confirm="Supprimer cette échéance ? (refusé si des paiements y sont affectés)">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="id" value="<?= e($e['id']) ?>">
                                                    <input type="hidden" name="inscription_id" value="<?= e($inscription['id']) ?>">
                                                    <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
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
        <?php endif; ?>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Paiements</span>
                <a href="<?= e(url('paiements.create', ['inscription_id' => $inscription['id']])) ?>" class="btn btn-sm btn-primary">
                    <i class="bi bi-plus"></i> Encaisser
                </a>
            </div>
            <?php if ($paiements === []): ?>
                <div class="empty-state py-4"><i class="bi bi-cash"></i>Aucun paiement pour cette inscription.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>N° reçu</th>
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
                                    <td><?= statut_badge((string) $p['mode_paiement']) ?></td>
                                    <td class="text-end money-strong"><?= e(argent($p['montant'])) ?></td>
                                    <td><?= statut_badge((string) $p['statut']) ?></td>
                                    <td class="text-end">
                                        <a href="<?= e(url('paiements.recu', ['id' => $p['id']])) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-receipt"></i></a>
                                        <?php if ($p['statut'] === 'valide' && Auth::isAdmin()): ?>
                                            <button class="btn btn-sm btn-outline-danger" type="button" data-bs-toggle="modal" data-bs-target="#modalAnnulerPaiement<?= e($p['id']) ?>">
                                                <i class="bi bi-x"></i>
                                            </button>
                                            <div class="modal fade" id="modalAnnulerPaiement<?= e($p['id']) ?>" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <form class="modal-content" method="post" action="<?= e(url('paiements.annuler')) ?>">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="id" value="<?= e($p['id']) ?>">
                                                        <input type="hidden" name="retour_inscription" value="<?= e($inscription['id']) ?>">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title h6">Annuler le paiement</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <p class="small text-muted">Paiement de <?= e(argent($p['montant'])) ?> du <?= e(date_fr((string) $p['date_paiement'])) ?>.</p>
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
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (Auth::isAdmin()): ?>
    <div class="modal fade" id="modalCloture" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form class="modal-content" method="post" action="<?= e(url('inscriptions.terminer')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= e($inscription['id']) ?>">
                <div class="modal-header">
                    <h5 class="modal-title h6">Clôturer l'inscription</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small">La clôture conserve la dette éventuelle. Le solde reste dû et tracé.</p>
                    <label class="form-label">Motif (optionnel)</label>
                    <textarea class="form-control" name="motif" rows="2"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Clôturer</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="modalAnnulation" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form class="modal-content" method="post" action="<?= e(url('inscriptions.annuler')) ?>" data-confirm="Annuler cette inscription ?">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= e($inscription['id']) ?>">
                <div class="modal-header">
                    <h5 class="modal-title h6">Annuler l'inscription</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning small">
                        L'annulation est refusée s'il reste des paiements validés. Annulez d'abord les paiements concernés.
                    </div>
                    <label class="form-label">Motif d'annulation *</label>
                    <textarea class="form-control" name="motif" rows="3" required minlength="5"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-danger">Annuler l'inscription</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>
