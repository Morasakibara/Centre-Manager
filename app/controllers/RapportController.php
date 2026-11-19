<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Services\AuditService;
use App\Services\ExportService;
use App\Services\RapportService;

final class RapportController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();

        $options = RapportService::options();
        $filtres = $this->filtres();
        $filtres['type'] = (string) ($_GET['type'] ?? 'general');

        $this->render('rapports/index', [
            'titre' => 'Rapports',
            'options' => $options,
            'filtres' => $filtres,
        ]);
    }

    public function generer(): void
    {
        $this->requireAuth();

        $filtres = $this->filtres();
        $type = (string) ($_GET['type'] ?? 'general');

        if (!array_key_exists($type, RapportService::TYPES)) {
            flash('danger', 'Type de rapport inconnu.');
            redirect('rapports');
        }

        if ($type === 'individuel' && empty($filtres['etudiant_id'])) {
            flash('warning', 'Sélectionnez un étudiant pour le rapport individuel.');
            redirect('rapports');
        }

        $rapport = RapportService::construire($type, $filtres);

        $this->render('rapports/resultat', [
            'titre' => $rapport['titre'],
            'rapport' => $rapport,
            'options' => RapportService::options(),
            'filtres' => $filtres + ['type' => $type],
        ]);
    }

    public function pdf(): void
    {
        $this->requireAuth();
        $rapport = $this->construireDepuisRequete();
        $fichier = ExportService::pdf($rapport);
        AuditService::log('rapport_pdf', 'rapport', null, ['type' => $rapport['type']]);

        redirect('rapports.telecharger', ['f' => basename($fichier)]);
    }

    public function excel(): void
    {
        $this->requireAuth();
        $rapport = $this->construireDepuisRequete();
        $fichier = ExportService::excel($rapport);
        AuditService::log('rapport_excel', 'rapport', null, ['type' => $rapport['type']]);

        redirect('rapports.telecharger', ['f' => basename($fichier)]);
    }

    public function telecharger(): void
    {
        $this->requireAuth();

        $nom = basename((string) ($_GET['f'] ?? ''));
        $chemin = ExportService::cheminTelechargement($nom);

        if ($chemin === null) {
            http_response_code(404);
            flash('danger', 'Fichier introuvable ou expiré. Relancez l\'export.');
            redirect('rapports');
        }

        $extension = strtolower(pathinfo($chemin, PATHINFO_EXTENSION));
        $mime = $extension === 'pdf'
            ? 'application/pdf'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . $nom . '"');
        header('Content-Length: ' . (string) filesize($chemin));
        header('Cache-Control: private, max-age=0, must-revalidate');
        readfile($chemin);
        exit;
    }

    /**
     * @return array<string, mixed>
     */
    private function construireDepuisRequete(): array
    {
        $filtres = $this->filtres();
        $type = (string) ($_GET['type'] ?? 'general');

        if (!array_key_exists($type, RapportService::TYPES)) {
            $type = 'general';
        }

        if ($type === 'individuel' && empty($filtres['etudiant_id'])) {
            flash('warning', 'Sélectionnez un étudiant pour le rapport individuel.');
            redirect('rapports');
        }

        return RapportService::construire($type, $filtres);
    }

    /**
     * @return array<string, mixed>
     */
    private function filtres(): array
    {
        return [
            'debut' => (string) ($_GET['debut'] ?? ''),
            'fin' => (string) ($_GET['fin'] ?? ''),
            'annee_id' => (string) ($_GET['annee_id'] ?? ''),
            'formation_id' => (string) ($_GET['formation_id'] ?? ''),
            'etudiant_id' => (string) ($_GET['etudiant_id'] ?? ''),
            'mode_paiement' => (string) ($_GET['mode_paiement'] ?? ''),
            'mode_inscription' => (string) ($_GET['mode_inscription'] ?? ''),
            'statut_financier' => (string) ($_GET['statut_financier'] ?? ''),
            'statut_paiement' => (string) ($_GET['statut_paiement'] ?? ''),
            'q' => trim((string) ($_GET['q'] ?? '')),
        ];
    }
}
