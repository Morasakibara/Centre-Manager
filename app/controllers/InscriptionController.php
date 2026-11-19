<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Core\Validator;
use App\Models\AnneeAcademique;
use App\Models\Echeance;
use App\Models\Etudiant;
use App\Models\Formation;
use App\Models\Inscription;
use App\Models\Paiement;
use App\Services\AuditService;
use App\Services\CalculFinancierService;
use App\Services\InscriptionService;

final class InscriptionController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();

        $filtres = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'annee' => (string) ($_GET['annee'] ?? ''),
            'formation' => (string) ($_GET['formation'] ?? ''),
            'statut' => (string) ($_GET['statut'] ?? ''),
            'mode' => (string) ($_GET['mode'] ?? ''),
            'statut_financier' => (string) ($_GET['statut_financier'] ?? ''),
        ];

        $resultat = Inscription::paginate($filtres, 1, 2000);
        $rows = $resultat['rows'];
        $situations = CalculFinancierService::situations(array_map(
            static fn (array $row): int => (int) $row['id'],
            $rows
        ));

        if ($filtres['statut_financier'] !== '') {
            $rows = array_values(array_filter(
                $rows,
                static fn (array $row): bool => isset($situations[(int) $row['id']])
                    && $situations[(int) $row['id']]['statut'] === $filtres['statut_financier']
            ));
        }

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $parPage = 15;
        $total = count($rows);
        $rows = array_slice($rows, ($page - 1) * $parPage, $parPage);

        $this->render('inscriptions/index', [
            'titre' => 'Inscriptions',
            'filtres' => $filtres,
            'inscriptions' => $rows,
            'situations' => $situations,
            'total' => $total,
            'page' => $page,
            'par_page' => $parPage,
            'annees' => AnneeAcademique::all(),
            'formations' => Formation::all(),
        ]);
    }

    public function create(): void
    {
        $this->requireAuth();

        $this->render('inscriptions/form', [
            'titre' => 'Nouvelle inscription',
            'etudiants' => Etudiant::tousActifs(),
            'formations' => Formation::all('actif'),
            'annees' => array_values(array_filter(AnneeAcademique::all(), static fn (array $a): bool => $a['statut'] === 'ouverte')),
            'pre_etudiant' => (int) ($_GET['etudiant_id'] ?? 0),
        ]);
    }

    public function store(): void
    {
        $this->requireAuth();

        $errors = Validator::validate($_POST, [
            'etudiant_id' => 'required|integer',
            'formation_id' => 'required|integer',
            'annee_academique_id' => 'required|integer',
            'date_inscription' => 'required|date',
            'montant_total' => 'nullable|decimal|min:0',
            'mode_paiement' => 'required|in:libre,tranches',
            'observation' => 'max:500',
        ]);

        if ($errors !== []) {
            Session::setErrors($errors);
            Session::flashInput($_POST);
            flash('danger', 'Certaines informations sont invalides.');
            redirect('inscriptions.create');
        }

        $echeancier = [];
        if (($_POST['mode_paiement'] ?? '') === 'tranches') {
            $libelles = $_POST['echeance_libelle'] ?? [];
            $montants = $_POST['echeance_montant'] ?? [];
            $dates = $_POST['echeance_date'] ?? [];

            foreach ((array) $dates as $index => $date) {
                if (trim((string) $date) === '' && trim((string) ($montants[$index] ?? '')) === '') {
                    continue;
                }
                $echeancier[] = [
                    'libelle' => (string) ($libelles[$index] ?? ''),
                    'montant' => (string) ($montants[$index] ?? '0'),
                    'date_echeance' => (string) $date,
                ];
            }
        }

        $resultat = InscriptionService::creer($_POST, $echeancier);

        if (!$resultat['success']) {
            Session::flashInput($_POST);
            flash('danger', $resultat['message']);
            redirect('inscriptions.create');
        }

        flash('success', $resultat['message']);
        redirect('inscriptions.show', ['id' => $resultat['inscription_id']]);
    }

    public function show(): void
    {
        $this->requireAuth();

        $id = (int) ($_GET['id'] ?? 0);
        $inscription = Inscription::find($id);
        if ($inscription === null) {
            flash('danger', 'Inscription introuvable.');
            redirect('inscriptions');
        }

        $situation = CalculFinancierService::situation($id);
        $echeances = Echeance::deInscription($id);
        $paiements = Paiement::deInscription($id);

        $this->render('inscriptions/show', [
            'titre' => 'Inscription ' . $inscription['matricule'],
            'inscription' => $inscription,
            'situation' => $situation,
            'echeances' => $echeances,
            'paiements' => $paiements,
            'resume_etudiant' => $this->resumeEtudiant((int) $inscription['etudiant_id']),
        ]);
    }

    public function terminer(): void
    {
        $this->requireAdmin();

        $id = (int) ($_POST['id'] ?? 0);
        $motif = trim((string) ($_POST['motif'] ?? ''));
        $resultat = InscriptionService::terminer($id, $motif);

        flash($resultat['success'] ? 'success' : 'danger', $resultat['message']);
        redirect('inscriptions.show', ['id' => $id]);
    }

    public function annuler(): void
    {
        $this->requireAdmin();

        $id = (int) ($_POST['id'] ?? 0);
        $motif = trim((string) ($_POST['motif'] ?? ''));
        $resultat = InscriptionService::annuler($id, $motif);

        flash($resultat['success'] ? 'success' : 'danger', $resultat['message']);
        redirect('inscriptions.show', ['id' => $id]);
    }

    /**
     * @return array<string, mixed>
     */
    private function resumeEtudiant(int $etudiantId): array
    {
        $inscriptions = Inscription::parEtudiant($etudiantId);
        $situations = CalculFinancierService::situations(array_map(
            static fn (array $row): int => (int) $row['id'],
            $inscriptions
        ));

        return [
            'nb_inscriptions' => count($inscriptions),
            'totaux' => CalculFinancierService::totaux($situations),
        ];
    }
}
