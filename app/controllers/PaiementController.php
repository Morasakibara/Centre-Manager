<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Session;
use App\Models\AnneeAcademique;
use App\Models\Formation;
use App\Models\Inscription;
use App\Models\Paiement;
use App\Services\CalculFinancierService;
use App\Services\ExportService;
use App\Services\PaiementService;
use App\Services\AuditService;

final class PaiementController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();

        $filtres = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'annee' => (string) ($_GET['annee'] ?? ''),
            'formation' => (string) ($_GET['formation'] ?? ''),
            'mode' => (string) ($_GET['mode'] ?? ''),
            'statut' => (string) ($_GET['statut'] ?? ''),
            'debut' => (string) ($_GET['debut'] ?? ''),
            'fin' => (string) ($_GET['fin'] ?? ''),
        ];

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $parPage = 15;
        $resultat = Paiement::paginate($filtres, $page, $parPage);
        $totaux = Paiement::totalFiltre($filtres);

        $this->render('paiements/index', [
            'titre' => 'Paiements',
            'filtres' => $filtres,
            'paiements' => $resultat['rows'],
            'total' => $resultat['total'],
            'page' => $page,
            'par_page' => $parPage,
            'totaux' => $totaux,
            'annees' => AnneeAcademique::all(),
            'formations' => Formation::all(),
            'modes' => PaiementService::modes(),
        ]);
    }

    public function create(): void
    {
        $this->requireAuth();

        $inscriptions = Inscription::allFiltres(['statut' => 'active']);
        $situations = CalculFinancierService::situations(array_map(
            static fn (array $row): int => (int) $row['id'],
            $inscriptions
        ));

        $inscriptions = array_values(array_filter(
            $inscriptions,
            static fn (array $row): bool => isset($situations[(int) $row['id']]) && $situations[(int) $row['id']]['reste_cents'] > 0
        ));

        $this->render('paiements/form', [
            'titre' => 'Enregistrer un paiement',
            'inscriptions' => $inscriptions,
            'situations' => $situations,
            'modes' => PaiementService::modes(),
            'pre_inscription' => (int) ($_GET['inscription_id'] ?? 0),
        ]);
    }

    public function store(): void
    {
        $this->requireAuth();

        $utilisateur = Auth::user();
        if ($utilisateur === null) {
            redirect('login');
        }

        $resultat = PaiementService::enregistrer($_POST, (int) $utilisateur['id']);

        if (!$resultat['success']) {
            Session::flashInput($_POST);
            flash('danger', $resultat['message']);
            $inscriptionId = (int) ($_POST['inscription_id'] ?? 0);
            redirect('paiements.create', $inscriptionId > 0 ? ['inscription_id' => $inscriptionId] : []);
        }

        flash('success', $resultat['message']);
        redirect('paiements.recu', ['id' => $resultat['paiement_id']]);
    }

    public function annuler(): void
    {
        $this->requireAdmin();

        $id = (int) ($_POST['id'] ?? 0);
        $motif = trim((string) ($_POST['motif'] ?? ''));
        $utilisateur = Auth::user();

        $resultat = PaiementService::annuler($id, $motif, (int) ($utilisateur['id'] ?? 0));
        flash($resultat['success'] ? 'success' : 'danger', $resultat['message']);

        $paiement = Paiement::find($id);
        $retour = (int) ($_POST['retour_inscription'] ?? 0);
        if ($retour > 0) {
            redirect('inscriptions.show', ['id' => $retour]);
        }

        redirect('paiements');
    }

    public function recu(): void
    {
        $this->requireAuth();

        $id = (int) ($_GET['id'] ?? 0);
        $paiement = Paiement::find($id);
        if ($paiement === null) {
            flash('danger', 'Paiement introuvable.');
            redirect('paiements');
        }

        $inscription = Inscription::find((int) $paiement['inscription_id']);
        $situation = CalculFinancierService::situation((int) $paiement['inscription_id']);

        $donnees = [
            'titre' => 'Reçu ' . $paiement['numero_recu'],
            'paiement' => $paiement,
            'inscription' => $inscription,
            'situation' => $situation,
        ];

        if (($_GET['format'] ?? '') === 'pdf') {
            $fichier = ExportService::recu($donnees);
            $this->telechargerFichier($fichier);
            return;
        }

        $this->render('paiements/recu', $donnees);
    }

    private function telechargerFichier(string $fichier): void
    {
        if (!is_file($fichier)) {
            flash('danger', 'Le fichier généré est introuvable.');
            redirect('paiements');
        }

        AuditService::log('recu_pdf', 'paiement', null, ['fichier' => basename($fichier)]);

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . basename($fichier) . '"');
        header('Content-Length: ' . (string) filesize($fichier));
        header('Cache-Control: private, max-age=0, must-revalidate');
        readfile($fichier);
        exit;
    }
}
