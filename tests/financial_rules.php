<?php

declare(strict_types=1);

/**
 * Tests des règles financières de Centre Manager.
 *
 * Ce runner ne dépend d'aucun framework : il crée une base dédiée
 * (centre_manager_test), y charge le schéma, exécute les scénarios puis
 * supprime la base. Les données de démonstration ne sont jamais touchées.
 *
 * Exécution : php tests/financial_rules.php
 */

use App\Core\Database;
use App\Core\Money;
use App\Services\CalculFinancierService;
use App\Services\EcheanceService;
use App\Services\InscriptionService;
use App\Services\PaiementService;

require dirname(__DIR__) . '/vendor/autoload.php';

const TEST_DATABASE = 'centre_manager_test';

$baseConfig = require dirname(__DIR__) . '/config/database.php';

$server = new PDO(
    sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $baseConfig['host'], $baseConfig['port']),
    (string) $baseConfig['username'],
    (string) $baseConfig['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$server->exec('DROP DATABASE IF EXISTS `' . TEST_DATABASE . '`');
$server->exec('CREATE DATABASE `' . TEST_DATABASE . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

$config = $baseConfig;
$config['database'] = TEST_DATABASE;
Database::configure($config);

$pdo = Database::pdo();

$schema = (string) file_get_contents(dirname(__DIR__) . '/database/schema.sql');
foreach (preg_split('/;\s*\n/', $schema) ?: [] as $statement) {
    $statement = trim($statement);
    if ($statement !== '') {
        $pdo->exec($statement);
    }
}

// Session simulée pour que les services d'audit et les contrôles de rôle
// fonctionnent comme dans l'application (utilisateur 1 = administrateur).
if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}
$_SESSION['user_id'] = 1;

$checks = 0;
$failures = [];

function check(bool $condition, string $label, string $detail = ''): void
{
    global $checks, $failures;
    $checks++;

    if ($condition) {
        echo "  OK   {$label}\n";
        return;
    }

    $failures[] = $label . ($detail !== '' ? ' — ' . $detail : '');
    echo "  FAIL {$label}\n";
    if ($detail !== '') {
        echo "       {$detail}\n";
    }
}

function same(mixed $expected, mixed $actual, string $label): void
{
    check($expected === $actual, $label, sprintf('attendu : %s, obtenu : %s', var_export($expected, true), var_export($actual, true)));
}

function sameFloat(float $expected, mixed $actual, string $label): void
{
    $actual = (float) $actual;
    check(abs($expected - $actual) < 0.005, $label, sprintf('attendu : %s, obtenu : %s', $expected, $actual));
}

function resetFixtures(PDO $pdo): void
{
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ([
        'journal_activites',
        'affectations_paiements',
        'paiements',
        'echeances',
        'inscriptions',
        'etudiants',
        'annees_academiques',
        'formations',
        'utilisateurs',
        'parametres',
        'compteurs',
    ] as $table) {
        $pdo->exec('TRUNCATE TABLE ' . $table);
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    $pdo->exec(
        "INSERT INTO utilisateurs (id, nom_complet, nom_utilisateur, mot_de_passe, role, statut)
         VALUES (1, 'Admin Test', 'admin', 'x', 'administrateur', 'actif')"
    );
    $pdo->exec(
        "INSERT INTO formations (id, nom, frais_formation, statut)
         VALUES (1, 'Formation Test', 50000.00, 'actif')"
    );
    $pdo->exec(
        "INSERT INTO annees_academiques (id, libelle, date_debut, date_fin, statut)
         VALUES (1, 'Annee Test', '2026-09-01', '2027-06-30', 'ouverte')"
    );
    $pdo->exec(
        "INSERT INTO etudiants (id, matricule, nom, prenom, sexe, telephone, date_enregistrement, statut)
         VALUES (1, 'TEST-001', 'Test', 'Etudiant', 'M', '+237600000000', '2026-09-01', 'actif')"
    );
}

/**
 * @param array<int, array{libelle: string, montant: string, date_echeance: string}> $echeancier
 */
function createInscription(PDO $pdo, string $mode, float $montant, array $echeancier = []): int
{
    $result = InscriptionService::creer([
        'etudiant_id' => 1,
        'formation_id' => 1,
        'annee_academique_id' => 1,
        'date_inscription' => date('Y-m-d'),
        'montant_total' => (string) $montant,
        'mode_paiement' => $mode,
    ], $echeancier);

    if (!$result['success'] || !isset($result['inscription_id'])) {
        throw new RuntimeException('Création inscription impossible : ' . $result['message']);
    }

    return (int) $result['inscription_id'];
}

function pay(int $inscriptionId, float $montant, ?string $date = null): array
{
    return PaiementService::enregistrer([
        'inscription_id' => $inscriptionId,
        'montant' => (string) $montant,
        'mode_paiement' => 'especes',
        'date_paiement' => $date ?? date('Y-m-d\TH:i'),
    ], 1);
}

$future = static fn (int $days): string => date('Y-m-d', strtotime('+' . $days . ' days'));

// ---------------------------------------------------------------------------
echo "\nCas A - Solde d'une inscription libre\n";
resetFixtures($pdo);
$inscription = createInscription($pdo, 'libre', 50000.0);

$result = pay($inscription, 20000.0);
check($result['success'], 'un paiement partiel est accepté');

$situation = CalculFinancierService::situation($inscription);
sameFloat(20000.0, $situation['total_paye'], 'total payé après paiement partiel');
sameFloat(30000.0, $situation['reste'], 'solde restant après paiement partiel');
same('non_solde', $situation['statut'], 'statut non soldé');
sameFloat(40.0, $situation['pourcentage'], 'pourcentage réglé');

$result = pay($inscription, 30000.0);
check($result['success'], 'le solde exact est accepté');
$situation = CalculFinancierService::situation($inscription);
sameFloat(0.0, $situation['reste'], 'solde nul après règlement complet');
same('solde', $situation['statut'], 'statut soldé');
sameFloat(100.0, $situation['pourcentage'], 'pourcentage 100 %');

$result = pay($inscription, 1.0);
check(!$result['success'], 'un paiement sur une inscription soldée est refusé');

// ---------------------------------------------------------------------------
echo "\nCas B - Affectation FIFO des tranches\n";
resetFixtures($pdo);
$echeancier = [
    ['libelle' => 'Tranche 1', 'montant' => '20000.00', 'date_echeance' => $future(30)],
    ['libelle' => 'Tranche 2', 'montant' => '20000.00', 'date_echeance' => $future(60)],
    ['libelle' => 'Tranche 3', 'montant' => '20000.00', 'date_echeance' => $future(90)],
];
$inscription = createInscription($pdo, 'tranches', 60000.0, $echeancier);

$result = pay($inscription, 25000.0);
check($result['success'], 'paiement de 25 000 sur 60 000 accepté');

$affectations = $pdo->query(
    'SELECT echeance_id, montant_affecte FROM affectations_paiements
     WHERE paiement_id = ' . (int) $result['paiement_id'] . ' ORDER BY echeance_id'
)->fetchAll();
same(2, count($affectations), 'deux échéances affectées');
same('20000.00', $affectations[0]['montant_affecte'], 'première échéance soldée en priorité (FIFO)');
same('5000.00', $affectations[1]['montant_affecte'], 'reliquat reporté sur la deuxième échéance');

$situation = CalculFinancierService::situation($inscription);
sameFloat(25000.0, $situation['total_paye'], 'total payé après premier versement');
sameFloat(35000.0, $situation['reste'], 'solde restant après premier versement');
same('payee', $situation['echeances'][0]['statut_echeance'], 'première échéance payée');
same('partielle', $situation['echeances'][1]['statut_echeance'], 'deuxième échéance partielle');

pay($inscription, 15000.0);
$affectations = $pdo->query(
    'SELECT echeance_id, montant_affecte FROM affectations_paiements ORDER BY id'
)->fetchAll();
same(3, count($affectations), 'les affectations se cumulent entre paiements');
same('15000.00', $affectations[2]['montant_affecte'], 'deuxième échéance complétée sans dépassement');

$situation = CalculFinancierService::situation($inscription);
sameFloat(40000.0, $situation['total_paye'], 'total payé après second versement');
sameFloat(20000.0, $situation['reste'], 'solde restant après second versement');

pay($inscription, 10000.0);
$affectations = $pdo->query(
    'SELECT echeance_id, montant_affecte FROM affectations_paiements ORDER BY id'
)->fetchAll();
same(4, count($affectations), 'le reliquat est affecté à la troisième échéance');
same('10000.00', $affectations[3]['montant_affecte'], 'troisième échéance partiellement réglée');

$result = pay($inscription, 10001.0);
check(!$result['success'], 'un paiement supérieur au solde est refusé');

// ---------------------------------------------------------------------------
echo "\nCas C - Annulation tracée d'un paiement\n";
resetFixtures($pdo);
$inscription = createInscription($pdo, 'libre', 50000.0);
$paiement = pay($inscription, 20000.0);
$paiementId = (int) $paiement['paiement_id'];

$annulation = PaiementService::annuler($paiementId, 'Erreur de saisie du montant', 1);
check($annulation['success'], 'annulation avec motif acceptée');

$statut = $pdo->query('SELECT statut, motif_annulation FROM paiements WHERE id = ' . $paiementId)->fetch();
same('annule', $statut['statut'], 'paiement marqué annulé (jamais supprimé)');
same('Erreur de saisie du montant', $statut['motif_annulation'], 'motif d\'annulation conservé');

$situation = CalculFinancierService::situation($inscription);
sameFloat(0.0, $situation['total_paye'], 'paiement annulé non comptabilisé');
sameFloat(50000.0, $situation['reste'], 'solde rétabli après annulation');
same('non_solde', $situation['statut'], 'statut revenu à non soldé');

$annulation = PaiementService::annuler($paiementId, 'Deuxieme annulation', 1);
check(!$annulation['success'], 'une double annulation est refusée');

$annulation = PaiementService::annuler($paiementId, 'abc', 1);
check(!$annulation['success'], 'un motif trop court est refusé');

// ---------------------------------------------------------------------------
echo "\nCas D - Annulation d'une inscription\n";
resetFixtures($pdo);
$inscription = createInscription($pdo, 'libre', 50000.0);
pay($inscription, 10000.0);

$result = InscriptionService::annuler($inscription, 'Abandon de la formation');
check(!$result['success'], 'inscription avec paiement validé non annulable directement');

$paiementId = (int) $pdo->query('SELECT id FROM paiements WHERE inscription_id = ' . $inscription)->fetchColumn();
PaiementService::annuler($paiementId, 'Remboursement avant abandon', 1);

$result = InscriptionService::annuler($inscription, 'Abandon de la formation');
check($result['success'], 'inscription annulable après annulation des paiements');

$situation = CalculFinancierService::situation($inscription);
same('annulee', $situation['statut'], 'statut financier annulé');
sameFloat(0.0, $situation['total_paye'], 'aucun paiement comptabilisé sur inscription annulée');

// ---------------------------------------------------------------------------
echo "\nCas E - Validation de l'échéancier\n";
$lignes = EcheanceService::normaliser([
    ['libelle' => '', 'montant' => '10000', 'date_echeance' => '2026-12-01'],
    ['libelle' => 'Solde', 'montant' => '15000', 'date_echeance' => '2027-01-01'],
], 2500000);
same(2, count($lignes), 'échéancier équilibré accepté');
same('Tranche 1', $lignes[0]['libelle'], 'libellé par défaut appliqué');
same('25000.00', Money::fromCents(2500000), 'montant formaté depuis les centimes');

$erreur = null;
try {
    EcheanceService::normaliser([
        ['libelle' => 'Trop', 'montant' => '10000', 'date_echeance' => '2026-12-01'],
    ], 2000000);
} catch (DomainException $exception) {
    $erreur = $exception->getMessage();
}
check($erreur !== null, 'échéancier dont la somme diffère du total refusé');

$erreur = null;
try {
    EcheanceService::normaliser([
        ['libelle' => 'Date invalide', 'montant' => '10000', 'date_echeance' => '2026-02-31'],
    ], 1000000);
} catch (DomainException $exception) {
    $erreur = $exception->getMessage();
}
check($erreur !== null, 'date d\'échéance invalide refusée');

// ---------------------------------------------------------------------------
echo "\nCas F - Totaux agrégés et précision monétaire\n";
resetFixtures($pdo);
$inscriptionLibre = createInscription($pdo, 'libre', 50000.0);
pay($inscriptionLibre, 50000.0);

$pdo->exec(
    "INSERT INTO etudiants (id, matricule, nom, prenom, sexe, telephone, date_enregistrement, statut)
     VALUES (2, 'TEST-002', 'Second', 'Etudiant', 'F', '+237600000001', '2026-09-01', 'actif')"
);
$pdo->exec(
    "INSERT INTO inscriptions (id, etudiant_id, formation_id, annee_academique_id, date_inscription,
        montant_total, montant_initial, mode_paiement, statut)
     VALUES (2, 2, 1, 1, '2026-09-01', 50000.00, 50000.00, 'libre', 'active')"
);

$situations = CalculFinancierService::situations([1, 2]);
$totaux = CalculFinancierService::totaux($situations);
same(2, $totaux['nb_inscriptions'], 'nombre d\'inscriptions agrégées');
sameFloat(100000.0, $totaux['montant_total'], 'montant total agrégé');
sameFloat(50000.0, $totaux['total_paye'], 'total encaissé agrégé');
sameFloat(50000.0, $totaux['reste'], 'solde restant agrégé');
same(1, $totaux['nb_soldees'], 'une inscription soldée');

same(30, Money::toCents('0.30'), 'conversion en centimes sans erreur de flottant');
same('0.30', Money::fromCents(30), 'conversion inverse en centimes');
same(0, Money::toCents(''), 'montant vide converti en zéro');

// ---------------------------------------------------------------------------
$server->exec('DROP DATABASE IF EXISTS `' . TEST_DATABASE . '`');

echo "\n" . str_repeat('-', 60) . "\n";
if ($failures === []) {
    echo sprintf("SUCCES : %d vérifications passées.\n", $checks);
    exit(0);
}

echo sprintf("ECHEC : %d vérification(s) en échec sur %d.\n", count($failures), $checks);
foreach ($failures as $failure) {
    echo '  - ' . $failure . "\n";
}
exit(1);
