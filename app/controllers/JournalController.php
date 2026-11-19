<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Utilisateur;
use App\Services\AuditService;

final class JournalController extends Controller
{
    public function index(): void
    {
        $this->requireAdmin();

        $filtres = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'action' => (string) ($_GET['action'] ?? ''),
            'entite' => (string) ($_GET['entite'] ?? ''),
            'utilisateur' => (string) ($_GET['utilisateur'] ?? ''),
            'debut' => (string) ($_GET['debut'] ?? ''),
            'fin' => (string) ($_GET['fin'] ?? ''),
        ];

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $parPage = 30;
        $resultat = AuditService::paginate($filtres, $page, $parPage);

        $this->render('journal/index', [
            'titre' => 'Journal d\'activité',
            'filtres' => $filtres,
            'entrees' => $resultat['rows'],
            'total' => $resultat['total'],
            'page' => $page,
            'par_page' => $parPage,
            'utilisateurs' => Utilisateur::all(),
        ]);
    }
}
