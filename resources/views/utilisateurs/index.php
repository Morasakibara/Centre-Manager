<?php use App\Core\Auth; ?>
<div class="page-head">
    <div>
        <h1>Utilisateurs</h1>
        <p class="lead-sm">Comptes et rôles d'accès à l'application.</p>
    </div>
    <a href="<?= e(url('utilisateurs.create')) ?>" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Nouvel utilisateur</a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Nom complet</th>
                    <th>Login</th>
                    <th>Rôle</th>
                    <th>Statut</th>
                    <th>Créé le</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($utilisateurs as $u): ?>
                    <tr>
                        <td class="row-title"><?= e($u['nom_complet']) ?></td>
                        <td class="mono"><?= e($u['nom_utilisateur']) ?></td>
                        <td><?= statut_badge((string) $u['role']) ?></td>
                        <td><?= statut_badge((string) ($u['statut'] ?? 'actif')) ?></td>
                        <td class="row-sub"><?= e(date_fr((string) $u['created_at'])) ?></td>
                        <td class="text-end">
                            <a href="<?= e(url('utilisateurs.edit', ['id' => $u['id']])) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                            <?php if ((int) $u['id'] !== (int) Auth::id()): ?>
                                <form method="post" action="<?= e(url('utilisateurs.supprimer')) ?>" class="d-inline" data-confirm="Supprimer cet utilisateur ?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= e($u['id']) ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
