<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Validator;
use App\Models\Parametre;
use App\Services\AuditService;
use App\Services\BackupService;
use App\Services\ExportService;

final class ParametreController extends Controller
{
    public function index(): void
    {
        $this->requireAdmin();

        $pdo = Database::pdo();
        $pdoVersion = $pdo->query('SELECT VERSION()')->fetchColumn();

        $this->render('parametres/index', [
            'titre' => 'Paramètres',
            'parametres' => Parametre::all(),
            'sauvegardes' => BackupService::liste(),
            'systeme' => [
                'php' => PHP_VERSION,
                'mysql' => (string) $pdoVersion,
                'base' => (string) (require dirname(__DIR__, 2) . '/config/database.php')['database'],
                'espace_exports' => count(glob(ExportService::dossierExports() . '/*') ?: []),
            ],
        ]);
    }

    public function update(): void
    {
        $this->requireAdmin();

        $errors = Validator::validate($_POST, [
            'centre_nom' => 'required|max:150',
            'centre_adresse' => 'max:255',
            'centre_telephone' => 'max:40',
            'centre_email' => 'nullable|email|max:190',
            'matricule_prefixe' => 'required|max:10',
        ]);

        if ($errors !== []) {
            foreach ($errors as $message) {
                flash('danger', $message);
            }
            redirect('parametres');
        }

        Parametre::setMany([
            'centre_nom' => trim((string) $_POST['centre_nom']),
            'centre_adresse' => trim((string) ($_POST['centre_adresse'] ?? '')),
            'centre_telephone' => trim((string) ($_POST['centre_telephone'] ?? '')),
            'centre_email' => trim((string) ($_POST['centre_email'] ?? '')),
            'matricule_prefixe' => strtoupper(trim((string) $_POST['matricule_prefixe'])),
        ]);

        AuditService::log('parametres_modification', 'parametres', null, []);
        flash('success', 'Paramètres enregistrés. Le préfixe des matricules s\'applique aux prochains dossiers.');
        redirect('parametres');
    }

    public function backup(): void
    {
        $this->requireAdmin();

        $resultat = BackupService::creer();
        flash($resultat['success'] ? 'success' : 'danger', $resultat['message']);

        redirect('parametres');
    }

    public function telechargerSauvegarde(): void
    {
        $this->requireAdmin();

        $nom = basename((string) ($_GET['fichier'] ?? ''));
        $chemin = BackupService::dossier() . '/' . $nom;
        if ($nom === '' || !is_file($chemin)) {
            flash('danger', 'Sauvegarde introuvable.');
            redirect('parametres');
        }

        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . $nom . '"');
        header('Content-Length: ' . (string) filesize($chemin));
        readfile($chemin);
        exit;
    }

    public function restaurer(): void
    {
        $this->requireAdmin();

        $nom = basename((string) ($_POST['fichier'] ?? ''));
        $chemin = BackupService::dossier() . '/' . $nom;

        try {
            BackupService::restaurer($chemin);
            AuditService::log('restauration_base', 'systeme', null, ['fichier' => $nom]);
            flash('success', 'Restauration effectuée depuis ' . $nom . '.');
        } catch (\Throwable $exception) {
            flash('danger', $exception->getMessage());
        }

        redirect('parametres');
    }

    public function restaurerUpload(): void
    {
        $this->requireAdmin();

        if (!isset($_FILES['fichier_sql']) || (int) $_FILES['fichier_sql']['error'] !== UPLOAD_ERR_OK) {
            flash('danger', 'Aucun fichier valide n\'a été téléversé.');
            redirect('parametres');
        }

        $tmp = (string) $_FILES['fichier_sql']['tmp_name'];
        $nom = 'upload-' . date('Ymd-His') . '.sql';
        $chemin = BackupService::dossier() . '/' . $nom;

        if (!move_uploaded_file($tmp, $chemin)) {
            flash('danger', 'Le téléversement a échoué.');
            redirect('parametres');
        }

        try {
            BackupService::restaurer($chemin);
            AuditService::log('restauration_base', 'systeme', null, ['fichier' => $nom]);
            flash('success', 'Restauration effectuée depuis le fichier téléversé.');
        } catch (\Throwable $exception) {
            @unlink($chemin);
            flash('danger', $exception->getMessage());
        }

        redirect('parametres');
    }
}
