<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

final class Auth
{
    /** @var array<string, mixed>|null */
    private static ?array $cachedUser = null;

    /** @return array<string, mixed>|null */
    public static function user(): ?array
    {
        if (self::$cachedUser !== null) {
            return self::$cachedUser;
        }

        Session::start();
        $id = $_SESSION['user_id'] ?? null;

        if (!is_int($id) && !ctype_digit((string) $id)) {
            return null;
        }

        $statement = Database::pdo()->prepare(
            'SELECT id, nom_complet, nom_utilisateur, email, role, statut, derniere_connexion
             FROM utilisateurs WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => (int) $id]);
        $user = $statement->fetch();

        if ($user === false || $user['statut'] !== 'actif') {
            self::logout();
            return null;
        }

        self::$cachedUser = $user;

        return $user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function id(): ?int
    {
        $user = self::user();

        return $user === null ? null : (int) $user['id'];
    }

    public static function isAdmin(): bool
    {
        $user = self::user();

        return $user !== null && $user['role'] === 'administrateur';
    }

    public static function role(): ?string
    {
        return self::user()['role'] ?? null;
    }

    /**
     * @return array{success: bool, message: string}
     */
    public static function attempt(string $nomUtilisateur, string $motDePasse): array
    {
        $pdo = Database::pdo();
        $statement = $pdo->prepare('SELECT * FROM utilisateurs WHERE nom_utilisateur = :u LIMIT 1');
        $statement->execute(['u' => $nomUtilisateur]);
        $user = $statement->fetch();

        if ($user === false) {
            AuditServiceProxy::log('connexion_echec', 'utilisateur', null, ['nom_utilisateur' => $nomUtilisateur], null);
            return ['success' => false, 'message' => 'Identifiants incorrects.'];
        }

        if ($user['statut'] !== 'actif') {
            return ['success' => false, 'message' => 'Ce compte est desactive. Contactez l\'administrateur.'];
        }

        if ($user['verrouille_jusqu_a'] !== null && strtotime((string) $user['verrouille_jusqu_a']) > time()) {
            $minutes = (int) ceil((strtotime((string) $user['verrouille_jusqu_a']) - time()) / 60);
            return [
                'success' => false,
                'message' => sprintf('Trop de tentatives. Compte verrouille encore %d minute(s).', max(1, $minutes)),
            ];
        }

        if (!password_verify($motDePasse, (string) $user['mot_de_passe'])) {
            $max = (int) config('login.max_tentatives', 5);
            $tentatives = (int) $user['tentatives_echecs'] + 1;
            $blocage = null;

            if ($tentatives >= $max) {
                $blocage = date('Y-m-d H:i:s', time() + ((int) config('login.blocage_minutes', 15) * 60));
                $tentatives = 0;
            }

            $update = $pdo->prepare(
                'UPDATE utilisateurs SET tentatives_echecs = :t, verrouille_jusqu_a = :v WHERE id = :id'
            );
            $update->execute(['t' => $tentatives, 'v' => $blocage, 'id' => $user['id']]);

            AuditServiceProxy::log('connexion_echec', 'utilisateur', (int) $user['id'], ['nom_utilisateur' => $nomUtilisateur], null);

            return [
                'success' => false,
                'message' => $blocage !== null
                    ? sprintf('Trop de tentatives. Compte verrouille pendant %d minutes.', (int) config('login.blocage_minutes', 15))
                    : 'Identifiants incorrects.',
            ];
        }

        if (password_needs_rehash((string) $user['mot_de_passe'], PASSWORD_DEFAULT)) {
            $rehash = $pdo->prepare('UPDATE utilisateurs SET mot_de_passe = :p WHERE id = :id');
            $rehash->execute(['p' => password_hash($motDePasse, PASSWORD_DEFAULT), 'id' => $user['id']]);
        }

        $reset = $pdo->prepare(
            'UPDATE utilisateurs
             SET tentatives_echecs = 0, verrouille_jusqu_a = NULL, derniere_connexion = NOW()
             WHERE id = :id'
        );
        $reset->execute(['id' => $user['id']]);

        self::login($user);
        AuditServiceProxy::log('connexion', 'utilisateur', (int) $user['id'], [], (int) $user['id']);

        return ['success' => true, 'message' => 'Connexion reussie.'];
    }

    /** @param array<string, mixed> $user */
    public static function login(array $user): void
    {
        Session::regenerate();
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_role'] = $user['role'];
        self::$cachedUser = null;
    }

    public static function logout(): void
    {
        self::$cachedUser = null;
        Session::forget('user_id');
        Session::forget('user_role');
        Session::regenerate();
    }
}

/**
 * Petit adaptateur pour eviter une dependance circulaire dans le bootstrap.
 */
final class AuditServiceProxy
{
    public static function log(string $action, string $entite, ?int $entiteId, array $details = [], ?int $utilisateurId = null): void
    {
        if (class_exists(\App\Services\AuditService::class)) {
            \App\Services\AuditService::log($action, $entite, $entiteId, $details, $utilisateurId);
        }
    }
}
