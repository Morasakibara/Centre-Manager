<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Session;
use App\Core\Validator;
use App\Models\Utilisateur;
use App\Services\AuditService;

final class AuthController extends Controller
{
    public function showLogin(): void
    {
        if (Auth::check()) {
            redirect('dashboard');
        }

        $this->render('auth/login', ['titre' => 'Connexion'], 'layouts/auth');
    }

    public function login(): void
    {
        $username = trim((string) ($_POST['nom_utilisateur'] ?? ''));
        $password = (string) ($_POST['mot_de_passe'] ?? '');

        $errors = Validator::validate($_POST, [
            'nom_utilisateur' => 'required|max:60',
            'mot_de_passe' => 'required',
        ]);

        if ($errors !== []) {
            Session::setErrors($errors);
            flash('danger', 'Veuillez renseigner votre identifiant et votre mot de passe.');
            redirect('login');
        }

        $resultat = Auth::attempt($username, $password);

        if (!$resultat['success']) {
            Session::setErrors(['nom_utilisateur' => $resultat['message']]);
            redirect('login');
        }

        flash('success', 'Bienvenue, ' . (Auth::user()['nom_complet'] ?? '') . '.');
        redirect('dashboard');
    }

    public function logout(): void
    {
        $user = Auth::user();
        if ($user !== null) {
            AuditService::log('deconnexion', 'utilisateur', (int) $user['id'], []);
        }

        Auth::logout();
        Session::destroy();
        Session::start();
        flash('success', 'Vous êtes déconnecté.');
        redirect('login');
    }

    public function changerMotDePasse(): void
    {
        $this->requireAuth();
        $user = Auth::user();
        $actuel = (string) ($_POST['mot_de_passe_actuel'] ?? '');
        $nouveau = (string) ($_POST['nouveau_mot_de_passe'] ?? '');

        $row = Utilisateur::find((int) $user['id']);
        if ($row === null || !password_verify($actuel, (string) $row['mot_de_passe'])) {
            flash('danger', 'Le mot de passe actuel est incorrect.');
            redirect('dashboard');
        }

        $errors = Validator::validate($_POST, [
            'nouveau_mot_de_passe' => 'required|motdepasse|confirme',
        ]);

        if ($errors !== []) {
            foreach ($errors as $message) {
                flash('danger', $message);
            }
            redirect('dashboard');
        }

        Utilisateur::changerMotDePasse((int) $user['id'], $nouveau);
        AuditService::log('mot_de_passe_change', 'utilisateur', (int) $user['id'], []);

        flash('success', 'Votre mot de passe a été modifié.');
        redirect('dashboard');
    }
}
