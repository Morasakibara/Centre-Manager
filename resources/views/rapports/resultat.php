<?php
$filtresActifs = $filtres ?? [];
$type = $filtresActifs['type'] ?? $rapport['type'];
$query = http_build_query(array_merge($filtresActifs, ['type' => $type]));
?>

<div class="page-head">
    <div>
        <h1><?= e($rapport['titre']) ?></h1>
        <p class="lead-sm"><?= e($rapport['sous_titre']) ?> · Généré le <?= e($rapport['generee_le']) ?></p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= e(url('rapports')) ?>" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Filtres</a>
        <a href="<?= e(url('rapports.pdf') . '?' . $query) ?>" class="btn btn-outline-danger"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a>
        <a href="<?= e(url('rapports.excel') . '?' . $query) ?>" class="btn btn-outline-success"><i class="bi bi-file-earmark-excel me-1"></i>Excel</a>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <div class="row g-3">
            <?php foreach ($rapport['resume'] as $item): ?>
                <div class="col-6 col-md-3">
                    <div class="stat-label"><?= e($item['libelle']) ?></div>
                    <div class="stat-value" style="font-size:1.1rem;">
                        <?php if (($item['type'] ?? '') === 'money'): ?>
                            <?= e(argent($item['valeur'])) ?>
                        <?php elseif (($item['type'] ?? '') === 'int'): ?>
                            <?= e(number_format((float) $item['valeur'], 0, ',', ' ')) ?>
                        <?php else: ?>
                            <?= e((string) $item['valeur']) ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="small text-muted mt-2"><?= e($rapport['filtres_texte']) ?></div>
    </div>
</div>

<?php foreach ($rapport['sections'] as $section): ?>
    <div class="card mb-3 report-section">
        <div class="card-header"><?= e($section['titre']) ?></div>
        <?php if (empty($section['lignes'])): ?>
            <div class="empty-state py-4"><i class="bi bi-inbox"></i>Aucune donnée pour ces critères.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 report-table">
                    <thead>
                        <tr>
                            <?php foreach ($section['colonnes'] as $col): ?>
                                <th><?= e($col['libelle']) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($section['lignes'] as $ligne): ?>
                            <tr>
                                <?php foreach ($section['colonnes'] as $col): ?>
                                    <?php $valeur = $ligne[$col['cle']] ?? ''; ?>
                                    <td>
                                        <?php if (($col['type'] ?? '') === 'money'): ?>
                                            <span class="money"><?= e(argent($valeur)) ?></span>
                                        <?php elseif (($col['type'] ?? '') === 'date'): ?>
                                            <?= e(date_fr((string) $valeur)) ?>
                                        <?php elseif (($col['type'] ?? '') === 'int'): ?>
                                            <?= e(number_format((float) $valeur, 0, ',', ' ')) ?>
                                        <?php else: ?>
                                            <?= e((string) $valeur) ?>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <?php if (!empty($section['totaux'])): ?>
                        <tfoot>
                            <tr class="table-light fw-bold">
                                <?php foreach ($section['colonnes'] as $col): ?>
                                    <td>
                                        <?php if ($col === $section['colonnes'][0]): ?>
                                            TOTAL
                                        <?php elseif (array_key_exists($col['cle'], $section['totaux'])): ?>
                                            <?php if (($col['type'] ?? '') === 'money'): ?>
                                                <?= e(argent($section['totaux'][$col['cle']])) ?>
                                            <?php elseif (($col['type'] ?? '') === 'int'): ?>
                                                <?= e(number_format((float) $section['totaux'][$col['cle']], 0, ',', ' ')) ?>
                                            <?php else: ?>
                                                <?= e((string) $section['totaux'][$col['cle']]) ?>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
