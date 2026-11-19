<main class="login-shell">
    <section class="login-brand-pane">
        <img class="login-brand-logo" src="<?= e(asset('img/logo-pharaonacademia.png')) ?>" alt="Logo PharaonAcademia">
        <div>
            <span class="login-eyebrow">Espace de gestion</span>
            <h1>PharaonAcademia</h1>
            <p>La gestion de votre centre de formation, de vos étudiants et de vos finances réunie en un seul espace.</p>
        </div>
        <div class="login-brand-footer">
            <i class="bi bi-shield-check me-2"></i>Accès sécurisé · Développé par NAYGAL
        </div>
    </section>

    <section class="login-form-pane">
        <div class="login-heading">
            <span class="login-icon"><i class="bi bi-person-lock"></i></span>
            <div>
                <h2>Bon retour</h2>
                <p>Connectez-vous à votre espace de travail.</p>
            </div>
        </div>

        <?php foreach ($flashes ?? [] as $flash): ?>
            <div class="alert alert-<?= e($flash['type']) ?> d-flex align-items-center gap-2" role="status">
                <i class="bi <?= $flash['type'] === 'success' ? 'bi-check-circle-fill' : 'bi-info-circle-fill' ?>"></i>
                <span><?= e($flash['message']) ?></span>
            </div>
        <?php endforeach; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger small" role="alert">
                <?php foreach ($errors as $message): ?>
                    <div><?= e($message) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('login.post')) ?>">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label" for="nom_utilisateur">Nom d'utilisateur</label>
                <input class="form-control form-control-lg" type="text" id="nom_utilisateur" name="nom_utilisateur"
                       value="<?= e(old('nom_utilisateur')) ?>" required autofocus autocomplete="username" placeholder="Votre identifiant">
            </div>
            <div class="mb-4">
                <label class="form-label" for="mot_de_passe">Mot de passe</label>
                <input class="form-control form-control-lg" type="password" id="mot_de_passe" name="mot_de_passe" required autocomplete="current-password" placeholder="Votre mot de passe">
            </div>
            <button class="btn btn-primary btn-lg w-100 login-submit" type="submit">
                Se connecter <i class="bi bi-arrow-right ms-2"></i>
            </button>
        </form>

        <div class="login-form-footer">
            <i class="bi bi-lock-fill me-1"></i>Connexion protégée par votre compte utilisateur
        </div>
    </section>
</main>
