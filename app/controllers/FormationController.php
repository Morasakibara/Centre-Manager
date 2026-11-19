<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Core\Validator;
use App\Models\Formation;
use App\Services\AuditService;

final class FormationController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();

        $this->render('formations/index', [
            'titre' => 'Formations',
            'formations' => Formation::avecStatistiques(),
        ]);
    }

    public function create(): void
    {
        $this->requireAdmin();

        $this->render('formations/form', [
            'titre' => 'Nouvelle formation',
            'formation' => null,
        ]);
    }

    public function store(): void
    {
        $this->requireAdmin();

        $data = $this->valider();
        if (isset($data['errors'])) {
            Session::setErrors($data['errors']);
            Session::flashInput($_POST);
            redirect('formations.create');
        }

        if (Formation::nomExiste($data['nom'])) {
            Session::setErrors(['nom' => 'Une formation porte déjà ce nom.']);
            Session::flashInput($_POST);
            redirect('formations.create');
        }

        $id = Formation::create($data);
        AuditService::log('formation_creation', 'formation', $id, [
            'nom' => $data['nom'],
            'frais' => $data['frais_formation'],
        ]);

        flash('success', 'Formation créée.');
        redirect('formations');
    }

    public function edit(): void
    {
        $this->requireAdmin();

        $formation = Formation::find((int) ($_GET['id'] ?? 0));
        if ($formation === null) {
            flash('danger', 'Formation introuvable.');
            redirect('formations');
        }

        $this->render('formations/form', [
            'titre' => 'Modifier une formation',
            'formation' => $formation,
        ]);
    }

    public function update(): void
    {
        $this->requireAdmin();

        $id = (int) ($_POST['id'] ?? 0);
        $formation = Formation::find($id);
        if ($formation === null) {
            flash('danger', 'Formation introuvable.');
            redirect('formations');
        }

        $data = $this->valider();
        if (isset($data['errors'])) {
            Session::setErrors($data['errors']);
            Session::flashInput($_POST);
            redirect('formations.edit', ['id' => $id]);
        }

        if (Formation::nomExiste($data['nom'], $id)) {
            Session::setErrors(['nom' => 'Une autre formation porte déjà ce nom.']);
            Session::flashInput($_POST);
            redirect('formations.edit', ['id' => $id]);
        }

        Formation::update($id, $data);

        $details = [];
        if ((string) $formation['frais_formation'] !== $data['frais_formation']) {
            $details['ancien_tarif'] = $formation['frais_formation'];
            $details['nouveau_tarif'] = $data['frais_formation'];
        }
        AuditService::log('formation_modification', 'formation', $id, $details);

        flash('success', 'Formation mise à jour. Les inscriptions existantes conservent leur montant convenu.');
        redirect('formations');
    }

    public function changerStatut(): void
    {
        $this->requireAdmin();

        $id = (int) ($_POST['id'] ?? 0);
        $statut = (string) ($_POST['statut'] ?? '');
        $formation = Formation::find($id);

        if ($formation === null) {
            flash('danger', 'Formation introuvable.');
            redirect('formations');
        }

        if (!in_array($statut, ['actif', 'inactif'], true)) {
            flash('danger', 'Statut invalide.');
            redirect('formations');
        }

        Formation::changerStatut($id, $statut);
        AuditService::log('formation_statut', 'formation', $id, ['nouveau' => $statut]);
        flash('success', 'Statut de la formation mis à jour.');

        redirect('formations');
    }

    /**
     * @return array<string, mixed>
     */
    private function valider(): array
    {
        $errors = Validator::validate($_POST, [
            'nom' => 'required|max:150',
            'description' => 'max:2000',
            'frais_formation' => 'required|decimal|min:0',
            'statut' => 'required|in:actif,inactif',
        ]);

        if ($errors !== []) {
            return ['errors' => $errors];
        }

        return [
            'nom' => trim((string) $_POST['nom']),
            'description' => trim((string) ($_POST['description'] ?? '')),
            'frais_formation' => number_format((float) str_replace([' ', ','], ['', '.'], (string) $_POST['frais_formation']), 2, '.', ''),
            'statut' => (string) $_POST['statut'],
        ];
    }
}
