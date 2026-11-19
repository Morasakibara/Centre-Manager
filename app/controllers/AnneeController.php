<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Core\Validator;
use App\Models\AnneeAcademique;
use App\Models\Inscription;
use App\Services\AuditService;

final class AnneeController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();

        $annees = AnneeAcademique::all();
        $stats = [];
        foreach ($annees as $annee) {
            $stats[(int) $annee['id']] = Inscription::compter((int) $annee['id']);
        }

        $this->render('annees/index', [
            'titre' => 'Années académiques',
            'annees' => $annees,
            'stats' => $stats,
        ]);
    }

    public function store(): void
    {
        $this->requireAdmin();

        $data = $this->valider();
        if (isset($data['errors'])) {
            Session::setErrors($data['errors']);
            Session::flashInput($_POST);
            redirect('annees');
        }

        if (AnneeAcademique::libelleExiste($data['libelle'])) {
            Session::setErrors(['libelle' => 'Cette année académique existe déjà.']);
            Session::flashInput($_POST);
            redirect('annees');
        }

        $id = AnneeAcademique::create($data);
        AuditService::log('annee_creation', 'annee', $id, ['libelle' => $data['libelle']]);
        flash('success', 'Année académique créée.');
        redirect('annees');
    }

    public function update(): void
    {
        $this->requireAdmin();

        $id = (int) ($_POST['id'] ?? 0);
        $annee = AnneeAcademique::find($id);
        if ($annee === null) {
            flash('danger', 'Année académique introuvable.');
            redirect('annees');
        }

        if ($annee['statut'] === 'cloturee') {
            flash('danger', 'Une année clôturée est protégée. Rouvrez-la avant toute modification.');
            redirect('annees');
        }

        $data = $this->valider();
        if (isset($data['errors'])) {
            Session::setErrors($data['errors']);
            Session::flashInput($_POST);
            redirect('annees');
        }

        if (AnneeAcademique::libelleExiste($data['libelle'], $id)) {
            Session::setErrors(['libelle' => 'Une autre année porte déjà ce libellé.']);
            Session::flashInput($_POST);
            redirect('annees');
        }

        AnneeAcademique::update($id, $data);
        AuditService::log('annee_modification', 'annee', $id, [
            'ancien' => $annee['libelle'],
            'nouveau' => $data['libelle'],
        ]);
        flash('success', 'Année académique mise à jour.');
        redirect('annees');
    }

    public function cloturer(): void
    {
        $this->requireAdmin();

        $id = (int) ($_POST['id'] ?? 0);
        $annee = AnneeAcademique::find($id);
        if ($annee === null) {
            flash('danger', 'Année académique introuvable.');
            redirect('annees');
        }

        AnneeAcademique::changerStatut($id, 'cloturee');
        AuditService::log('annee_cloture', 'annee', $id, ['libelle' => $annee['libelle']]);
        flash('success', 'Année académique clôturée.');
        redirect('annees');
    }

    public function rouvrir(): void
    {
        $this->requireAdmin();

        $id = (int) ($_POST['id'] ?? 0);
        $annee = AnneeAcademique::find($id);
        if ($annee === null) {
            flash('danger', 'Année académique introuvable.');
            redirect('annees');
        }

        AnneeAcademique::changerStatut($id, 'ouverte');
        AuditService::log('annee_reouverture', 'annee', $id, ['libelle' => $annee['libelle']]);
        flash('success', 'Année académique réouverte. Cette opération est tracée dans le journal.');
        redirect('annees');
    }

    /**
     * @return array<string, mixed>
     */
    private function valider(): array
    {
        $errors = Validator::validate($_POST, [
            'libelle' => 'required|max:20',
            'date_debut' => 'required|date',
            'date_fin' => 'required|date|apres:date_debut',
        ]);

        if ($errors === []) {
            $libelle = trim((string) $_POST['libelle']);
            if (!preg_match('/^\d{4}-\d{4}$/', $libelle)) {
                $errors['libelle'] = 'Le libellé doit suivre le format AAAA-AAAA, par exemple 2026-2027.';
            }
        }

        if ($errors !== []) {
            return ['errors' => $errors];
        }

        return [
            'libelle' => trim((string) $_POST['libelle']),
            'date_debut' => (string) $_POST['date_debut'],
            'date_fin' => (string) $_POST['date_fin'],
            'statut' => 'ouverte',
        ];
    }
}
