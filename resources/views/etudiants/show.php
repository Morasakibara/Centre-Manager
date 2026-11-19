<div class="page-head">
    <div>
        <h1><?= e($etudiant['prenom'] . ' ' . $etudiant['nom']) ?></h1>
        <p class="lead-sm"><span class="mono"><?= e($etudiant['matricule']) ?></span> · <?= statut_badge((string) $etudiant['statut']) ?></p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= e(url('inscriptions.create', ['etudiant_id' => $etudiant['id']])) ?>" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Inscrire</a>
        <a href="<?= e(url('etudiants.edit', ['id' => $etudiant['id']])) ?>" class="btn btn-outline-secondary"><i class="bi bi-pencil me-1"></i>Modifier</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header">Identité</div>
            <div class="card-body">
                <ul class="detail-list">
                    <li><span class="label">Matricule</span><span class="value mono"><?= e($etudiant['matricule']) ?></span></li>
                    <li><span class="label">Téléphone</span><span class="value"><?= e($etudiant['telephone']) ?></span></li>
                    <li><span class="label">Sexe</span><span class="value"><?= e($etudiant['sexe'] === 'M' ? 'Masculin' : 'Féminin') ?></span></li>
                    <li><span class="label">Naissance</span><span class="value"><?= e(date_fr((string) $etudiant['date_naissance'])) ?></span></li>
                    <li><span class="label">Enregistré le</span><span class="value"><?= e(date_fr((string) $etudiant['date_enregistrement'])) ?></span></li>
                    <li><span class="label">Adresse</span><span class="value"><?= e($etudiant['adresse'] ?: '—') ?></span></li>
                </ul>
            </div>
        </div>
        <div class="card">
            <div class="card-header">Synthèse financière</div>
            <div class="card-body">
                <ul class="detail-list">
                    <li><span class="label">Inscriptions</span><span class="value"><?= e((string) $totaux['nb_inscriptions']) ?></span></li>
                    <li><span class="label">Montant attendu</span><span class="value money"><?= e(argent($totaux['montant_total'])) ?></span></li>
                    <li><span class="label">Total payé</span><span class="value money text-success"><?= e(argent($totaux['total_paye'])) ?></span></li>
                    <li><span class="label">Solde restant</span><span class="value money <?= $totaux['reste'] > 0 ? 'text-danger' : 'text-success' ?>"><?= e(argent($totaux['reste'])) ?></span></li>
                    <li><span class="label">Soldées</span><span class="value"><?= e((string) $totaux['nb_soldees']) ?></span></li>
                    <li><span class="label">En retard</span><span class="value text-danger"><?= e((string) $totaux['nb_en_retard']) ?></span></li>
                </ul>
                <div class="mt-3">
                    <a href="<?= e(url('rapports', ['type' => 'individuel', 'etudiant_id' => $etudiant['id']])) ?>" class="btn btn-sm btn-outline-primary w-100">
                        <i class="bi bi-file-earmark-bar-graph me-1"></i>Rapport individuel
                    </a>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header">Inscriptions</div>
            <?php if ($inscriptions === []): ?>
                <div class="empty-state"><i class="bi bi-file-earmark"></i>Aucune inscription.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
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
                                    <td class="row-title"><?= e($ins['formation_nom']) ?></td>
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
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-header">Historique des paiements</div>
            <?php if ($paiements === []): ?>
                <div class="empty-state"><i class="bi bi-cash"></i>Aucun paiement.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>N° reçu</th>
                                <th>Formation</th>
                                <th>Mode</th>
                                <th class="text-end">Montant</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($paiements as $p): ?>
                                <tr>
                                    <td class="row-sub"><?= e(date_fr((string) $p['date_paiement'], true)) ?></td>
                                    <td class="mono"><?= e((string) $p['numero_recu']) ?></td>
                                    <td><?= e($p['formation_nom']) ?></td>
                                    <td><?= statut_badge((string) $p['mode_paiement']) ?></td>
                                    <td class="text-end money-strong"><?= e(argent($p['montant'])) ?></td>
                                    <td><?= statut_badge((string) $p['statut']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
