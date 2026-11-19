<?php
/** @var array<int, array{section: string, items: array<int, array<string, mixed>>}> $menu */
?>
<div class="brand">
    <div class="brand-logo brand-banner">
        <img src="<?= e(asset('img/logo-pharaonacademia.png')) ?>" alt="Logo PharaonAcademia">
    </div>
    <div class="brand-text">
        <div class="brand-name">PharaonAcademia</div>
        <div class="brand-sub">Gestion du centre</div>
    </div>
    <button class="sidebar-close" type="button" data-toggle-sidebar aria-label="Fermer le menu">
        <i class="bi bi-x-lg"></i>
    </button>
</div>

<?php foreach ($menu as $groupe): ?>
    <div class="sidebar-section"><?= e($groupe['section']) ?></div>
    <ul class="sidebar-nav">
        <?php foreach ($groupe['items'] as $item): ?>
            <li>
                <a href="<?= e(url($item['route'])) ?>" class="<?= $estActif($item) ? 'active' : '' ?>">
                    <i class="bi <?= e($item['icon']) ?>"></i>
                    <span><?= e($item['label']) ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endforeach; ?>

<div class="sidebar-footer">
    <div class="d-flex align-items-center gap-2">
        <i class="bi bi-shield-lock"></i>
        <span>Accès local sécurisé</span>
    </div>
    <div class="mt-1" style="opacity:.7;">v1.0 — <?= e(date('Y')) ?></div>
</div>
