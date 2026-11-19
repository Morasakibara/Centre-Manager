<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title><?= e($rapport['titre']) ?></title>
    <style>
        @page { margin: 1.2cm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 9pt; color: #222; }
        .header { text-align: center; margin-bottom: 12px; }
        .header h1 { font-size: 14pt; margin: 0 0 2px; color: #0f4c81; }
        .header .sub { font-size: 9pt; color: #555; }
        .meta { font-size: 8pt; color: #666; margin-bottom: 10px; }
        .resume { width: 100%; margin-bottom: 10px; }
        .resume td { padding: 3px 6px; border: 1px solid #ddd; font-size: 8.5pt; }
        .resume .label { background: #f2f5f9; font-weight: bold; width: 35%; }
        .section-title { font-size: 10.5pt; font-weight: bold; color: #0f4c81; margin: 12px 0 4px; border-bottom: 1px solid #0f4c81; padding-bottom: 2px; }
        table.data { width: 100%; border-collapse: collapse; font-size: 8pt; }
        table.data th { background: #0f4c81; color: #fff; padding: 4px 5px; text-align: left; }
        table.data td { border-bottom: 1px solid #e5e9ee; padding: 3px 5px; }
        table.data tr:nth-child(even) { background: #f7f9fc; }
        table.data .total td { font-weight: bold; background: #e8f1f9; border-top: 1px solid #0f4c81; }
        .money { text-align: right; white-space: nowrap; }
        .footer { margin-top: 12px; font-size: 8pt; color: #777; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1><?= e($rapport['centre']['nom']) ?></h1>
        <div class="sub"><?= e($rapport['centre']['adresse']) ?> · <?= e($rapport['centre']['telephone']) ?></div>
        <div style="margin-top:6px;font-size:12pt;font-weight:bold;"><?= e($rapport['titre']) ?></div>
        <div class="sub"><?= e($rapport['sous_titre']) ?></div>
    </div>

    <div class="meta">
        Généré le <?= e($rapport['generee_le']) ?> · <?= e($rapport['filtres_texte']) ?>
    </div>

    <?php if ($rapport['resume'] !== []): ?>
        <table class="resume">
            <?php foreach ($rapport['resume'] as $item): ?>
                <tr>
                    <td class="label"><?= e($item['libelle']) ?></td>
                    <td>
                        <?php if (($item['type'] ?? '') === 'money'): ?>
                            <?= e(argent($item['valeur'])) ?>
                        <?php elseif (($item['type'] ?? '') === 'int'): ?>
                            <?= e(number_format((float) $item['valeur'], 0, ',', ' ')) ?>
                        <?php else: ?>
                            <?= e((string) $item['valeur']) ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <?php foreach ($rapport['sections'] as $section): ?>
        <div class="section-title"><?= e($section['titre']) ?></div>
        <?php if (empty($section['lignes'])): ?>
            <p style="color:#777;font-style:italic;">Aucune donnée pour ces critères.</p>
        <?php else: ?>
            <table class="data">
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
                                <td class="<?= ($col['type'] ?? '') === 'money' ? 'money' : '' ?>">
                                    <?php if (($col['type'] ?? '') === 'money'): ?>
                                        <?= e(argent($valeur)) ?>
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
                    <?php if (!empty($section['totaux'])): ?>
                        <tr class="total">
                            <?php foreach ($section['colonnes'] as $col): ?>
                                <td class="<?= ($col['type'] ?? '') === 'money' ? 'money' : '' ?>">
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
                    <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php endforeach; ?>

    <div class="footer">
        Centre Manager — solution développée par NAYGAL. Les montants sont exprimés en <?= e((string) config('devise', 'XAF')) ?>.
    </div>
</body>
</html>
