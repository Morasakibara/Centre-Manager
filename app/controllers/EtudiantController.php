<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Session;
use App\Core\Validator;
use App\Models\Etudiant;
use App\Models\Inscription;
use App\Models\Paiement;
use App\Services\AuditService;
use App\Services\CalculFinancierService;
use App\Services\MatriculeService;
use PDOException;

final class EtudiantController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();

        $filtres = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'statut' => (string) ($_GET['statut'] ?? ''),
        ];
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $resultat = Etudiant::paginate($filtres, $page, 15);

        $ids = array_map(static fn (array $row): int => (int) $row['id'], $resultat['rows']);
        $resumes = $this->resumesFinanciers($ids);

        $this->render('etudiants/index', [
            'titre' => 'Étudiants',
            'filtres' => $filtres,
            'etudiants' => $resultat['rows'],
            'total' => $resultat['total'],
            'page' => $page,
            'par_page' => 15,
            'resumes' => $resumes,
        ]);
    }

    public function create(): void
    {
        $this->requireAuth();

        $this->render('etudiants/form', [
            'titre' => 'Nouvel étudiant',
            'etudiant' => null,
            'doublons' => [],
        ]);
    }

    public function store(): void
    {
        $this->requireAuth();

        $errors = Validator::validate($_POST, [
            'nom' => 'required|max:100',
            'prenom' => 'required|max:100',
            'sexe' => 'required|in:M,F',
            'telephone' => 'required|telephone',
            'date_naissance' => 'nullable|date',
            'date_enregistrement' => 'required|date',
            'adresse' => 'max:255',
        ]);

        if ($errors !== []) {
            Session::setErrors($errors);
            Session::flashInput($_POST);
            flash('danger', 'Certaines informations sont invalides.');
            redirect('etudiants.create');
        }

        $doublons = Etudiant::doublonsPotentiels(
            trim((string) $_POST['nom']),
            trim((string) $_POST['prenom']),
            trim((string) $_POST['telephone'])
        );

        if ($doublons !== [] && empty($_POST['confirmer_doublon'])) {
            Session::flashInput($_POST);
            flash('warning', 'Un étudiant avec un nom et un téléphone similaires existe déjà. Vérifiez avant de confirmer.');
            $this->render('etudiants/form', [
                'titre' => 'Nouvel étudiant',
                'etudiant' => $_POST,
                'doublons' => $doublons,
            ]);
            return;
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $matricule = MatriculeService::suivant((int) date('Y'));
            $id = Etudiant::create([
                'matricule' => $matricule,
                'nom' => trim((string) $_POST['nom']),
                'prenom' => trim((string) $_POST['prenom']),
                'sexe' => (string) $_POST['sexe'],
                'date_naissance' => trim((string) ($_POST['date_naissance'] ?? '')),
                'telephone' => trim((string) $_POST['telephone']),
                'adresse' => trim((string) ($_POST['adresse'] ?? '')),
                'date_enregistrement' => (string) $_POST['date_enregistrement'],
                'statut' => 'actif',
            ]);

            AuditService::log('etudiant_creation', 'etudiant', $id, ['matricule' => $matricule]);
            $pdo->commit();

            flash('success', 'Étudiant enregistré avec le matricule ' . $matricule . '.');
            redirect('etudiants.show', ['id' => $id]);
        } catch (PDOException $exception) {
            $pdo->rollBack();
            Database::log('Erreur creation etudiant : ' . $exception->getMessage());
            flash('danger', $exception->getCode() === '23000'
                ? 'Ce matricule existe déjà. Veuillez réessayer.'
                : 'Une erreur technique a empêché l\'enregistrement.');
            redirect('etudiants.create');
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            Database::log('Erreur creation etudiant : ' . $exception->getMessage());
            flash('danger', 'Une erreur technique a empêché l\'enregistrement.');
            redirect('etudiants.create');
        }
    }

    public function show(): void
    {
        $this->requireAuth();

        $id = (int) ($_GET['id'] ?? 0);
        $etudiant = Etudiant::find($id);
        if ($etudiant === null) {
            flash('danger', 'Étudiant introuvable.');
            redirect('etudiants');
        }

        $inscriptions = Inscription::deEtudiant($id);
        $situations = CalculFinancierService::situations(array_map(
            static fn (array $row): int => (int) $row['id'],
            $inscriptions
        ));
        $totaux = CalculFinancierService::totaux($situations);

        $paiements = [];
        foreach ($inscriptions as $inscription) {
            foreach (Paiement::deInscription((int) $inscription['id']) as $paiement) {
                $paiements[] = $paiement;
            }
        }
        usort($paiements, static fn (array $a, array $b): int => strcmp((string) $b['date_paiement'], (string) $a['date_paiement']));

        $this->render('etudiants/show', [
            'titre' => 'Fiche étudiant',
            'etudiant' => $etudiant,
            'inscriptions' => $inscriptions,
            'situations' => $situations,
            'totaux' => $totaux,
            'paiements' => $paiements,
        ]);
    }

    public function edit(): void
    {
        $this->requireAuth();

        $etudiant = Etudiant::find((int) ($_GET['id'] ?? 0));
        if ($etudiant === null) {
            flash('danger', 'Étudiant introuvable.');
            redirect('etudiants');
        }

        $this->render('etudiants/form', [
            'titre' => 'Modifier un étudiant',
            'etudiant' => $etudiant,
            'doublons' => [],
        ]);
    }

    public function update(): void
    {
        $this->requireAuth();

        $id = (int) ($_POST['id'] ?? 0);
        $etudiant = Etudiant::find($id);
        if ($etudiant === null) {
            flash('danger', 'Étudiant introuvable.');
            redirect('etudiants');
        }

        $errors = Validator::validate($_POST, [
            'nom' => 'required|max:100',
            'prenom' => 'required|max:100',
            'sexe' => 'required|in:M,F',
            'telephone' => 'required|telephone',
            'date_naissance' => 'nullable|date',
            'date_enregistrement' => 'required|date',
            'adresse' => 'max:255',
        ]);

        if ($errors !== []) {
            Session::setErrors($errors);
            Session::flashInput($_POST);
            flash('danger', 'Certaines informations sont invalides.');
            redirect('etudiants.edit', ['id' => $id]);
        }

        Etudiant::update($id, [
            'nom' => trim((string) $_POST['nom']),
            'prenom' => trim((string) $_POST['prenom']),
            'sexe' => (string) $_POST['sexe'],
            'date_naissance' => trim((string) ($_POST['date_naissance'] ?? '')),
            'telephone' => trim((string) $_POST['telephone']),
            'adresse' => trim((string) ($_POST['adresse'] ?? '')),
            'date_enregistrement' => (string) $_POST['date_enregistrement'],
        ]);

        AuditService::log('etudiant_modification', 'etudiant', $id, []);
        flash('success', 'La fiche étudiant a été mise à jour.');
        redirect('etudiants.show', ['id' => $id]);
    }

    public function changerStatut(): void
    {
        $this->requireAuth();

        $id = (int) ($_POST['id'] ?? 0);
        $statut = (string) ($_POST['statut'] ?? '');
        $autorises = ['actif', 'suspendu', 'termine', 'archive'];

        $etudiant = Etudiant::find($id);
        if ($etudiant === null) {
            flash('danger', 'Étudiant introuvable.');
            redirect('etudiants');
        }

        if (!in_array($statut, $autorises, true)) {
            flash('danger', 'Statut invalide.');
            redirect('etudiants.show', ['id' => $id]);
        }

        if ($statut === 'archive' && !Auth::isAdmin()) {
            flash('danger', 'Seul un administrateur peut archiver un dossier étudiant.');
            redirect('etudiants.show', ['id' => $id]);
        }

        Etudiant::changerStatut($id, $statut);
        AuditService::log('etudiant_statut', 'etudiant', $id, ['ancien' => $etudiant['statut'], 'nouveau' => $statut]);
        flash('success', 'Le statut de l\'étudiant a été mis à jour.');
        redirect('etudiants.show', ['id' => $id]);
    }

    /**
     * @param array<int, int> $etudiantIds
     * @return array<int, array{nb: int, reste: float, statut: string}>
     */
    private function resumesFinanciers(array $etudiantIds): array
    {
        if ($etudiantIds === []) {
            return [];
        }

        $in = implode(',', array_map('intval', $etudiantIds));
        $rows = Database::pdo()->query(
            "SELECT id, etudiant_id, montant_total, mode_paiement FROM inscriptions
             WHERE etudiant_id IN (" . $in . ") AND statut <> 'annulee'"
        )->fetchAll();

        $situations = CalculFinancierService::situations(array_map(
            static fn (array $row): int => (int) $row['id'],
            $rows
        ));

        $resumes = [];
        foreach ($rows as $row) {
            $etudiantId = (int) $row['etudiant_id'];
            $resumes[$etudiantId] ??= ['nb' => 0, 'reste' => 0.0, 'statut' => 'solde'];
            $resumes[$etudiantId]['nb']++;

            if (isset($situations[(int) $row['id']])) {
                $situation = $situations[(int) $row['id']];
                $resumes[$etudiantId]['reste'] += (float) $situation['reste'];
                if ($situation['statut'] === CalculFinancierService::STATUT_EN_RETARD) {
                    $resumes[$etudiantId]['statut'] = 'en_retard';
                } elseif ($resumes[$etudiantId]['statut'] !== 'en_retard' && $situation['statut'] === CalculFinancierService::STATUT_NON_SOLDE) {
                    $resumes[$etudiantId]['statut'] = 'non_solde';
                } elseif ($resumes[$etudiantId]['statut'] === 'solde' && $situation['reste'] > 0) {
                    $resumes[$etudiantId]['statut'] = 'non_solde';
                }
            }
        }

        return $resumes;
    }
}
