<?php

declare(strict_types=1);

use App\Core\Auth;

$route = (string) ($_GET['r'] ?? 'dashboard');
$utilisateur = Auth::user();
$nomComplet = (string) ($utilisateur['nom_complet'] ?? '');
$roleLabel = ($utilisateur['role'] ?? '') === 'administrateur' ? 'Administrateur' : 'Secrétaire / Comptable';
$morceauxNom = preg_split('/\s+/', trim($nomComplet)) ?: [''];
$initiales = mb_strtoupper(mb_substr($morceauxNom[0], 0, 1) . mb_substr(end($morceauxNom) ?: '', 0, 1));

$menu = [
    ['section' => 'Pilotage', 'items' => [
        ['route' => 'dashboard', 'label' => 'Tableau de bord', 'icon' => 'bi-speedometer2', 'match' => ['dashboard']],
    ]],
    ['section' => 'Gestion', 'items' => [
        ['route' => 'etudiants', 'label' => 'Étudiants', 'icon' => 'bi-people', 'match' => ['etudiants']],
        ['route' => 'formations', 'label' => 'Formations', 'icon' => 'bi-journal-bookmark', 'match' => ['formations']],
        ['route' => 'annees', 'label' => 'Années académiques', 'icon' => 'bi-calendar3', 'match' => ['annees']],
        ['route' => 'inscriptions', 'label' => 'Inscriptions', 'icon' => 'bi-file-earmark-text', 'match' => ['inscriptions']],
    ]],
    ['section' => 'Finances', 'items' => [
        ['route' => 'paiements', 'label' => 'Paiements', 'icon' => 'bi-cash-coin', 'match' => ['paiements']],
        ['route' => 'echeances', 'label' => 'Échéances', 'icon' => 'bi-calendar-check', 'match' => ['echeances']],
        ['route' => 'rapports', 'label' => 'Rapports', 'icon' => 'bi-bar-chart-line', 'match' => ['rapports']],
    ]],
];

if (Auth::isAdmin()) {
    $menu[] = ['section' => 'Administration', 'items' => [
        ['route' => 'utilisateurs', 'label' => 'Utilisateurs', 'icon' => 'bi-person-gear', 'match' => ['utilisateurs']],
        ['route' => 'journal', 'label' => 'Journal d\'activité', 'icon' => 'bi-clock-history', 'match' => ['journal']],
        ['route' => 'parametres', 'label' => 'Paramètres', 'icon' => 'bi-sliders', 'match' => ['parametres']],
    ]];
}

$estActif = static function (array $item) use ($route): bool {
    foreach ($item['match'] as $prefixe) {
        if ($route === $prefixe || str_starts_with($route, $prefixe . '.')) {
            return true;
        }
    }
    return false;
};

$centreNom = (string) config('centre.nom', 'Centre Manager');
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($titre ?? 'Centre Manager') ?> — <?= e($centreNom) ?></title>
    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
<div class="app-sidebar d-none d-lg-flex">
    <?php require __DIR__ . '/_sidebar.php'; ?>
</div>
<div class="app-sidebar d-lg-none" id="sidebar_mobile">
    <?php require __DIR__ . '/_sidebar.php'; ?>
</div>
<div class="sidebar-backdrop"></div>

<div class="app-main">
    <header class="app-topbar">
        <button class="btn btn-sm btn-outline-secondary d-lg-none" type="button" data-toggle-sidebar
                aria-label="Ouvrir le menu" aria-controls="sidebar_mobile" aria-expanded="false">
            <i class="bi bi-list"></i>
        </button>
        <div class="me-auto">
            <div class="topbar-title"><?= e($titre ?? 'Centre Manager') ?></div>
            <div class="topbar-sub"><?= e($centreNom) ?></div>
        </div>
        <div class="dropdown">
            <button class="user-chip border-0" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="avatar"><?= e($initiales) ?></span>
                <span class="d-none d-sm-block text-start lh-sm">
                    <span class="d-block" style="font-size:.82rem;font-weight:600;"><?= e($nomComplet) ?></span>
                    <span class="d-block" style="font-size:.7rem;color:#8494a5;"><?= e($roleLabel) ?></span>
                </span>
                <i class="bi bi-chevron-down ms-1" style="font-size:.7rem;color:#8494a5;"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                <li><span class="dropdown-item-text small text-muted">Connecté en tant que<br><strong><?= e((string) ($utilisateur['nom_utilisateur'] ?? '')) ?></strong></span></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#modalMotDePasse">
                        <i class="bi bi-key me-2"></i>Changer mon mot de passe
                    </button>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="post" action="<?= e(url('logout')) ?>">
                        <?= csrf_field() ?>
                        <button class="dropdown-item text-danger" type="submit"><i class="bi bi-box-arrow-right me-2"></i>Déconnexion</button>
                    </form>
                </li>
            </ul>
        </div>
    </header>

    <main class="content">
        <?php foreach ($flashes ?? [] as $flash): ?>
            <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
                <i class="bi <?= $flash['type'] === 'success' ? 'bi-check-circle' : ($flash['type'] === 'danger' ? 'bi-exclamation-octagon' : 'bi-info-circle') ?> me-2"></i>
                <?= e($flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
            </div>
        <?php endforeach; ?>

        <?= $content ?>
    </main>

    <footer class="app-footer d-flex flex-wrap justify-content-between gap-2">
        <span><?= e($centreNom) ?> — Centre Manager · Solution développée par NAYGAL</span>
        <span>Devise : <?= e((string) config('devise', 'XAF')) ?> · <?= e(date('d/m/Y')) ?></span>
    </footer>
</div>

<div class="modal fade" id="modalMotDePasse" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="post" action="<?= e(url('profil.motdepasse')) ?>">
            <?= csrf_field() ?>
            <div class="modal-header">
                <h5 class="modal-title h6 mb-0">Changer mon mot de passe</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="mot_de_passe_actuel">Mot de passe actuel</label>
                    <input class="form-control" type="password" id="mot_de_passe_actuel" name="mot_de_passe_actuel" required autocomplete="current-password">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="nouveau_mot_de_passe">Nouveau mot de passe</label>
                    <input class="form-control" type="password" id="nouveau_mot_de_passe" name="nouveau_mot_de_passe" required minlength="8" autocomplete="new-password">
                    <div class="form-text">8 caractères minimum, avec au moins une lettre et un chiffre.</div>
                </div>
                <div>
                    <label class="form-label" for="nouveau_mot_de_passe_confirmation">Confirmer le nouveau mot de passe</label>
                    <input class="form-control" type="password" id="nouveau_mot_de_passe_confirmation" name="nouveau_mot_de_passe_confirmation" required autocomplete="new-password">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script src="<?= e(asset('vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<?php if (!empty($scripts)): ?>
    <?= $scripts ?>
<?php endif; ?>
</body>
</html>
