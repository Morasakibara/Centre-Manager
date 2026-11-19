<?php

declare(strict_types=1);

use App\Controllers\AnneeController;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\EcheanceController;
use App\Controllers\EtudiantController;
use App\Controllers\FormationController;
use App\Controllers\InscriptionController;
use App\Controllers\JournalController;
use App\Controllers\PaiementController;
use App\Controllers\ParametreController;
use App\Controllers\RapportController;
use App\Controllers\UtilisateurController;

/**
 * Chaque route : [methode HTTP, controleur, action, auth, admin].
 */
return [
    'login'  => ['GET', AuthController::class, 'showLogin', false, false],
    'login.post' => ['POST', AuthController::class, 'login', false, false],
    'logout' => ['POST', AuthController::class, 'logout', true, false],

    'dashboard' => ['GET', DashboardController::class, 'index', true, false],

    'etudiants'         => ['GET', EtudiantController::class, 'index', true, false],
    'etudiants.create'  => ['GET', EtudiantController::class, 'create', true, false],
    'etudiants.store'   => ['POST', EtudiantController::class, 'store', true, false],
    'etudiants.show'    => ['GET', EtudiantController::class, 'show', true, false],
    'etudiants.edit'    => ['GET', EtudiantController::class, 'edit', true, false],
    'etudiants.update'  => ['POST', EtudiantController::class, 'update', true, false],
    'etudiants.statut'  => ['POST', EtudiantController::class, 'changerStatut', true, false],

    'formations'        => ['GET', FormationController::class, 'index', true, false],
    'formations.create' => ['GET', FormationController::class, 'create', true, false],
    'formations.store'  => ['POST', FormationController::class, 'store', true, true],
    'formations.edit'   => ['GET', FormationController::class, 'edit', true, true],
    'formations.update' => ['POST', FormationController::class, 'update', true, true],
    'formations.statut' => ['POST', FormationController::class, 'changerStatut', true, true],

    'annees'          => ['GET', AnneeController::class, 'index', true, false],
    'annees.store'    => ['POST', AnneeController::class, 'store', true, true],
    'annees.update'   => ['POST', AnneeController::class, 'update', true, true],
    'annees.cloturer' => ['POST', AnneeController::class, 'cloturer', true, true],
    'annees.rouvrir'  => ['POST', AnneeController::class, 'rouvrir', true, true],

    'inscriptions'         => ['GET', InscriptionController::class, 'index', true, false],
    'inscriptions.create'  => ['GET', InscriptionController::class, 'create', true, false],
    'inscriptions.store'   => ['POST', InscriptionController::class, 'store', true, false],
    'inscriptions.show'    => ['GET', InscriptionController::class, 'show', true, false],
    'inscriptions.terminer' => ['POST', InscriptionController::class, 'terminer', true, true],
    'inscriptions.annuler' => ['POST', InscriptionController::class, 'annuler', true, true],

    'paiements'        => ['GET', PaiementController::class, 'index', true, false],
    'paiements.create' => ['GET', PaiementController::class, 'create', true, false],
    'paiements.store'  => ['POST', PaiementController::class, 'store', true, false],
    'paiements.annuler' => ['POST', PaiementController::class, 'annuler', true, true],
    'paiements.recu'   => ['GET', PaiementController::class, 'recu', true, false],

    'echeances'       => ['GET', EcheanceController::class, 'index', true, false],
    'echeances.store' => ['POST', EcheanceController::class, 'store', true, false],
    'echeances.delete' => ['POST', EcheanceController::class, 'delete', true, true],

    'rapports'        => ['GET', RapportController::class, 'index', true, false],
    'rapports.generer' => ['GET', RapportController::class, 'generer', true, false],
    'rapports.pdf'    => ['GET', RapportController::class, 'pdf', true, false],
    'rapports.excel'  => ['GET', RapportController::class, 'excel', true, false],
    'rapports.telecharger' => ['GET', RapportController::class, 'telecharger', true, false],

    'utilisateurs'        => ['GET', UtilisateurController::class, 'index', true, true],
    'utilisateurs.create' => ['GET', UtilisateurController::class, 'create', true, true],
    'utilisateurs.store'  => ['POST', UtilisateurController::class, 'store', true, true],
    'utilisateurs.edit'   => ['GET', UtilisateurController::class, 'edit', true, true],
    'utilisateurs.update' => ['POST', UtilisateurController::class, 'update', true, true],
    'utilisateurs.statut' => ['POST', UtilisateurController::class, 'changerStatut', true, true],
    'utilisateurs.motdepasse' => ['POST', UtilisateurController::class, 'reinitialiserMotDePasse', true, true],

    'parametres'       => ['GET', ParametreController::class, 'index', true, true],
    'parametres.update' => ['POST', ParametreController::class, 'update', true, true],
    'parametres.backup' => ['POST', ParametreController::class, 'backup', true, true],
    'parametres.telecharger_sauvegarde' => ['GET', ParametreController::class, 'telechargerSauvegarde', true, true],
    'parametres.restaurer' => ['POST', ParametreController::class, 'restaurer', true, true],
    'parametres.restaurer_upload' => ['POST', ParametreController::class, 'restaurerUpload', true, true],

    'journal' => ['GET', JournalController::class, 'index', true, true],

    'profil.motdepasse' => ['POST', AuthController::class, 'changerMotDePasse', true, false],
];
