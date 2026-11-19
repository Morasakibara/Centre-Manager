<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Session;
use App\Core\Validator;
use App\Models\Utilisateur;
use App\Services\AuditService;

final class UtilisateurController extends Controller
{
    public function index(): void
    {
        $this->requireAdmin();

        $this->render('utilisateurs/index', [
            'titre' => 'Utilisateurs',
            'utilisateurs' => Utilisateur::all(),
        ]);
    }

    public function create(): void
    {
        $this->requireAdmin();

        $this->render('utilisateurs/form', [
            'titre' => 'Nouvel utilisateur',
            'utilisateur' => null,
        ]);
    }

    public function store(): void
    {
        $this->requireAdmin();

        $errors = Validator::validate($_POST, [
            'nom_complet' => 'required|max:150',
            'nom_utilisateur' => 'required|max:60',
            'email' => 'nullable|email|max:190',
            'role' => 'required|in:administrateur,secretaire',
            'mot_de_passe' => 'required|motdepasse|confirme',
        ]);

        if ($errors === []) {
            if (Utilisateur::nomUtilisateurExiste(trim((string) $_POST['nom_utilisateur']))) {
                $errors['nom_utilisateur'] = 'Ce nom d\'utilisateur est déjà utilisé.';
            }
        }

        if ($errors !== []) {
            Session::setErrors($errors);
            Session::flashInput($_POST);
            flash('danger', 'Certaines informations sont invalides.');
            redirect('utilisateurs.create');
        }

        $id = Utilisateur::create([
            'nom_complet' => trim((string) $_POST['nom_complet']),
            'nom_utilisateur' => trim((string) $_POST['nom_utilisateur']),
            'email' => trim((string) ($_POST['email'] ?? '')),
            'role' => (string) $_POST['role'],
            'mot_de_passe' => (string) $_POST['mot_de_passe'],
            'statut' => 'actif',
        ]);

        AuditService::log('utilisateur_creation', 'utilisateur', $id, ['role' => $_POST['role']]);
        flash('success', 'Utilisateur créé.');
        redirect('utilisateurs');
    }

    public function edit(): void
    {
        $this->requireAdmin();

        $utilisateur = Utilisateur::find((int) ($_GET['id'] ?? 0));
        if ($utilisateur === null) {
            flash('danger', 'Utilisateur introuvable.');
            redirect('utilisateurs');
        }

        $this->render('utilisateurs/form', [
            'titre' => 'Modifier un utilisateur',
            'utilisateur' => $utilisateur,
        ]);
    }

    public function update(): void
    {
        $this->requireAdmin();

        $id = (int) ($_POST['id'] ?? 0);
        $utilisateur = Utilisateur::find($id);
        if ($utilisateur === null) {
            flash('danger', 'Utilisateur introuvable.');
            redirect('utilisateurs');
        }

        $errors = Validator::validate($_POST, [
            'nom_complet' => 'required|max:150',
            'nom_utilisateur' => 'required|max:60',
            'email' => 'nullable|email|max:190',
            'role' => 'required|in:administrateur,secretaire',
        ]);

        $nomUtilisateur = trim((string) ($_POST['nom_utilisateur'] ?? ''));
        if ($errors === [] && Utilisateur::nomUtilisateurExiste($nomUtilisateur, $id)) {
            $errors['nom_utilisateur'] = 'Ce nom d\'utilisateur est déjà utilisé.';
        }

        $role = (string) ($_POST['role'] ?? $utilisateur['role']);
        if ($utilisateur['role'] === 'administrateur' && $role !== 'administrateur' && $id === (int) Auth::id()) {
            $errors['role'] = 'Vous ne pouvez pas retirer votre propre rôle administrateur.';
        }
        if ($utilisateur['role'] === 'administrateur' && $role !== 'administrateur' && Utilisateur::compterAdministrateursActifs() <= 1) {
            $errors['role'] = 'Il doit rester au moins un administrateur actif.';
        }

        if ($errors !== []) {
            Session::setErrors($errors);
            Session::flashInput($_POST);
            flash('danger', 'Certaines informations sont invalides.');
            redirect('utilisateurs.edit', ['id' => $id]);
        }

        Utilisateur::update($id, [
            'nom_complet' => trim((string) $_POST['nom_complet']),
            'nom_utilisateur' => $nomUtilisateur,
            'email' => trim((string) ($_POST['email'] ?? '')),
            'role' => $role,
        ]);

        AuditService::log('utilisateur_modification', 'utilisateur', $id, [
            'ancien_role' => $utilisateur['role'],
            'nouveau_role' => $role,
        ]);
        flash('success', 'Utilisateur mis à jour.');
        redirect('utilisateurs');
    }

    public function changerStatut(): void
    {
        $this->requireAdmin();

        $id = (int) ($_POST['id'] ?? 0);
        $statut = (string) ($_POST['statut'] ?? '');
        $utilisateur = Utilisateur::find($id);

        if ($utilisateur === null || !in_array($statut, ['actif', 'inactif'], true)) {
            flash('danger', 'Requête invalide.');
            redirect('utilisateurs');
        }

        if ($statut === 'inactif') {
            if ($id === (int) Auth::id()) {
                flash('danger', 'Vous ne pouvez pas désactiver votre propre compte.');
                redirect('utilisateurs');
            }
            if ($utilisateur['role'] === 'administrateur' && Utilisateur::compterAdministrateursActifs() <= 1) {
                flash('danger', 'Il doit rester au moins un administrateur actif.');
                redirect('utilisateurs');
            }
        }

        Utilisateur::changerStatut($id, $statut);
        AuditService::log('utilisateur_statut', 'utilisateur', $id, ['nouveau' => $statut]);
        flash('success', 'Statut du compte mis à jour.');
        redirect('utilisateurs');
    }

    public function reinitialiserMotDePasse(): void
    {
        $this->requireAdmin();

        $id = (int) ($_POST['id'] ?? 0);
        $utilisateur = Utilisateur::find($id);
        if ($utilisateur === null) {
            flash('danger', 'Utilisateur introuvable.');
            redirect('utilisateurs');
        }

        $errors = Validator::validate($_POST, [
            'mot_de_passe' => 'required|motdepasse|confirme',
        ]);

        if ($errors !== []) {
            foreach ($errors as $message) {
                flash('danger', $message);
            }
            redirect('utilisateurs');
        }

        Utilisateur::changerMotDePasse($id, (string) $_POST['mot_de_passe']);
        AuditService::log('utilisateur_mot_de_passe_reinitialise', 'utilisateur', $id, []);
        flash('success', 'Mot de passe réinitialisé pour ' . $utilisateur['nom_utilisateur'] . '.');
        redirect('utilisateurs');
    }
}
