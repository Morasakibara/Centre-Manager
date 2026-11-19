<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\AnneeAcademique;
use App\Models\Echeance;
use App\Services\CalculFinancierService;
use App\Services\EcheanceService;
use DomainException;

final class EcheanceController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();

        $anneeId = (int) ($_GET['annee'] ?? 0);
        $vue = (string) ($_GET['vue'] ?? 'en_retard');

        $lignes = match ($vue) {
            'a_venir' => Echeance::aVenir(200),
            'toutes' => Echeance::toutesAvecDetails($anneeId > 0 ? $anneeId : null),
            default => Echeance::enRetard($anneeId > 0 ? $anneeId : null),
        };

        $this->render('echeances/index', [
            'titre' => 'Échéances',
            'vue' => $vue,
            'annee_id' => $anneeId,
            'annees' => AnneeAcademique::all(),
            'lignes' => $lignes,
        ]);
    }

    public function store(): void
    {
        $this->requireAuth();

        $inscriptionId = (int) ($_POST['inscription_id'] ?? 0);
        $libelle = trim((string) ($_POST['libelle'] ?? ''));
        $montant = (string) ($_POST['montant_attendu'] ?? '0');
        $date = (string) ($_POST['date_echeance'] ?? '');

        try {
            EcheanceService::ajouter($inscriptionId, $libelle, $montant, $date);
            flash('success', 'Échéance ajoutée à l\'échéancier.');
        } catch (DomainException $exception) {
            flash('danger', $exception->getMessage());
        } catch (\Throwable $exception) {
            flash('danger', 'Une erreur technique a empêché l\'ajout de l\'échéance.');
        }

        redirect('inscriptions.show', ['id' => $inscriptionId]);
    }

    public function delete(): void
    {
        $this->requireAdmin();

        $id = (int) ($_POST['id'] ?? 0);
        $inscriptionId = (int) ($_POST['inscription_id'] ?? 0);
        $echeance = Echeance::find($id);

        try {
            EcheanceService::supprimer($id);
            flash('success', 'Échéance supprimée.');
        } catch (\Throwable $exception) {
            flash('danger', $exception->getMessage());
        }

        redirect('inscriptions.show', ['id' => $inscriptionId > 0 ? $inscriptionId : (int) ($echeance['inscription_id'] ?? 0)]);
    }
}
