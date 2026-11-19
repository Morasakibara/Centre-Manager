<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Reçu <?= e($paiement['numero_recu']) ?></title>
    <style>
        @page { margin: 1.2cm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 10pt; color: #222; }
        .header { text-align: center; margin-bottom: 14px; }
        .logo { display: block; width: 280px; height: auto; margin: 0 auto 8px; }
        .header h1 { font-size: 13pt; margin: 0; color: #0f4c81; }
        .header .sub { font-size: 9pt; color: #555; }
        .badge { display: inline-block; padding: 2px 8px; background: #e8f1f9; color: #0f4c81; border-radius: 4px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin: 8px 0; }
        th, td { border: 1px solid #d9e0e8; padding: 5px 8px; font-size: 9.5pt; }
        th { background: #f2f5f9; width: 40%; }
        .money { text-align: right; font-weight: bold; }
        .footer { margin-top: 18px; font-size: 8.5pt; color: #777; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <?php
        $logoPath = dirname(__DIR__, 3) . '/public/assets/img/logo-pharaonacademia.png';
        $logoData = file_get_contents($logoPath);
        if ($logoData === false) {
            throw new RuntimeException('Le logo PharaonAcademia est introuvable.');
        }
        $logoSrc = 'data:image/png;base64,' . base64_encode($logoData);
        ?>
        <img class="logo" src="<?= e($logoSrc) ?>" alt="Logo PharaonAcademia">
        <h1>PharaonAcademia</h1>
        <div class="sub"><?= e((string) config('centre.adresse', '')) ?> · <?= e((string) config('centre.telephone', '')) ?></div>
        <div style="margin-top:8px;font-size:12pt;font-weight:bold;">REÇU DE PAIEMENT</div>
        <div class="sub">N° <?= e($paiement['numero_recu']) ?> · Émis le <?= e(date_fr((string) $paiement['date_paiement'], true)) ?></div>
    </div>

    <table>
        <tr><th>Étudiant</th><td><?= e($paiement['nom'] . ' ' . $paiement['prenom']) ?> (<?= e($paiement['matricule']) ?>)</td></tr>
        <tr><th>Téléphone</th><td><?= e($paiement['telephone']) ?></td></tr>
        <tr><th>Formation</th><td><?= e($paiement['formation_nom']) ?></td></tr>
        <tr><th>Année</th><td><?= e($paiement['annee_libelle']) ?></td></tr>
        <tr><th>Montant convenu</th><td class="money"><?= e(argent($paiement['montant_total'])) ?></td></tr>
        <tr><th>Date du paiement</th><td><?= e(date_fr((string) $paiement['date_paiement'], true)) ?></td></tr>
        <tr><th>Mode de paiement</th><td><?= e(mode_paiement_label((string) $paiement['mode_paiement'])) ?></td></tr>
        <?php if (!empty($paiement['reference'])): ?>
            <tr><th>Référence</th><td><?= e($paiement['reference']) ?></td></tr>
        <?php endif; ?>
        <tr><th>Montant payé</th><td class="money"><?= e(argent($paiement['montant'])) ?></td></tr>
        <?php if (!empty($paiement['observation'])): ?>
            <tr><th>Observation</th><td><?= e($paiement['observation']) ?></td></tr>
        <?php endif; ?>
        <tr><th>Encaissé par</th><td><?= e($paiement['encaisse_par']) ?></td></tr>
        <tr><th>Solde restant</th><td class="money"><?= e(argent($situation['reste'])) ?></td></tr>
    </table>

    <div class="footer">
        Reçu généré automatiquement par PharaonAcademia le <?= e(date('d/m/Y H:i')) ?>.<br>
        Solution développée par NAYGAL.<br>
        Montants exprimés en <?= e((string) config('devise', 'XAF')) ?>.
    </div>
</body>
</html>
