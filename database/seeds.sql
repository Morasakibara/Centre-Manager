-- ---------------------------------------------------------------------------
-- Centre Manager - graines de demonstration (seeds.sql)
-- Insertion de données de test cohérentes pour l'application
-- Doit être exécuté aprés le schema.sql
-- ---------------------------------------------------------------------------

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------
-- 1. Parametres par defaut (ja dans schema, on s'assure qu'ils existent)
-- -----------------------------------------------------
DELETE FROM parametres;
INSERT INTO parametres (cle, valeur) VALUES
    ('centre_nom', 'PharaonAcademia'),
    ('centre_adresse', 'Avenue de la Formation, Douala'),
    ('centre_telephone', '+237 6 00 00 00 00'),
    ('centre_email', 'contact@centre-exemple.local'),
    ('matricule_prefixe', 'CF'),
    ('devise', 'XAF');

-- -----------------------------------------------------
-- 2. Utilisateurs (admin + secretary)
-- -----------------------------------------------------
DELETE FROM utilisateurs;
ALTER TABLE utilisateurs AUTO_INCREMENT = 1;
INSERT INTO utilisateurs (nom_complet, nom_utilisateur, email, mot_de_passe, role, statut) VALUES
    ('Administrateur Système', 'admin', 'admin@centre-exemple.local', '$2y$12$eVwpEgSiDWH1NEnCe1gYsOICtd.n2zqHZj81lb74baav3HZkb/.6S', 'administrateur', 'actif'),  -- mot de passe : Admin1234
    ('Secrétaire Trésorier', 'secrétaire', 'sec@centre-exemple.local', '$2y$12$eVwpEgSiDWH1NEnCe1gYsOICtd.n2zqHZj81lb74baav3HZkb/.6S', 'secretaire', 'actif');

-- -----------------------------------------------------
-- 3. Formations
-- -----------------------------------------------------
DELETE FROM formations;
ALTER TABLE formations AUTO_INCREMENT = 1;
INSERT INTO formations (nom, description, frais_formation, statut) VALUES
    ('Informatique', 'Formation en développement logiciel et bases de données', 50000.00, 'actif'),
    ('Comptabilité', 'Formation en comptabilité et gestion financière', 45000.00, 'actif'),
    ('Management', 'Formation en gestion d''entreprise et leadership', 60000.00, 'actif'),
    ('Ressources Humaines', 'Formation en gestion des ressources humaines', 40000.00, 'actif');

-- -----------------------------------------------------
-- 4. Annees academiques
-- -----------------------------------------------------
DELETE FROM annees_academiques;
ALTER TABLE annees_academiques AUTO_INCREMENT = 1;
INSERT INTO annees_academiques (libelle, date_debut, date_fin, statut) VALUES
    ('2023-2024', '2023-09-01', '2024-06-30', 'cloturee'),
    ('2026-2027', '2026-09-01', '2027-06-30', 'ouverte');

-- -----------------------------------------------------
-- 5. Etudiants
-- -----------------------------------------------------
DELETE FROM etudiants;
ALTER TABLE etudiants AUTO_INCREMENT = 1;
INSERT INTO etudiants (matricule, nom, prenom, sexe, date_naissance, telephone, adresse, date_enregistrement, statut) VALUES
    ('CF-2023-001', 'Nchinda', 'Jean', 'M', '1998-03-15', '+237 6 12 34 56 78', 'Quartier Ndogpassi, Douala', '2023-09-15', 'actif'),
    ('CF-2023-002', 'Tchouangue', 'Marie', 'F', '2000-07-22', '+237 6 23 45 67 89', 'Quartier Bonamoussadi, Douala', '2023-09-15', 'actif'),
    ('CF-2023-003', 'Djoko', 'Pierre', 'M', '1999-11-10', '+237 6 34 56 78 90', 'Quartier Bastos, Douala', '2023-09-16', 'actif'),
    ('CF-2023-004', 'Amougou', 'Sophie', 'F', '2001-05-30', '+237 6 45 67 89 01', 'Quartier Makepe, Douala', '2023-09-16', 'actif'),
    ('CF-2023-005', 'Moto', 'Paul', 'M', '1997-01-12', '+237 6 56 78 90 12', 'Quartier Acacias, Douala', '2023-09-17', 'termine');

-- -----------------------------------------------------
-- 6. Inscriptions (avec differentes combinaisons pour les tests)
-- -----------------------------------------------------
-- Inscription libre pour Jean (Informatique, 2023-2024)
DELETE FROM inscriptions;
ALTER TABLE inscriptions AUTO_INCREMENT = 1;
INSERT INTO inscriptions (etudiant_id, formation_id, annee_academique_id, date_inscription, montant_total, montant_initial, mode_paiement, statut, observation) VALUES
    (1, 1, 1, '2023-09-20', 50000.00, 50000.00, 'libre', 'active', 'Inscription libre Informatique 2023-2024'),
    (2, 1, 1, '2023-09-20', 50000.00, 50000.00, 'libre', 'active', 'Inscription libre Informatique 2023-2024'),
    (3, 2, 1, '2023-09-21', 45000.00, 45000.00, 'tranches', 'active', 'Inscription par tranches Comptabilité 2023-2024'),
    (4, 3, 1, '2023-09-21', 60000.00, 60000.00, 'libre', 'active', 'Inscription libre Management 2023-2024'),
    (5, 4, 1, '2023-09-22', 40000.00, 40000.00, 'libre', 'active', 'Inscription libre RH 2023-2024'),
    (1, 2, 2, '2026-09-15', 45000.00, 45000.00, 'tranches', 'active', 'Inscription par tranches Comptabilité 2026-2027');

-- Inscription avec paiement pour tests (Jean a deja une inscription libre, pas de doublon)
-- Inscription deja creee ci-dessus pour l'etudiant 1

-- -----------------------------------------------------
-- 7. Echeances
-- -----------------------------------------------------
DELETE FROM echeances;
ALTER TABLE echeances AUTO_INCREMENT = 1;
INSERT INTO echeances (inscription_id, libelle, montant_attendu, date_echeance) VALUES
    -- Jean (id=1) : 3 echeances
    (1, 'Inscription', 20000.00, '2023-10-01'),
    (1, 'Mois 1', 10000.00, '2023-11-01'),
    (1, 'Mois 2', 10000.00, '2023-12-01'),
    -- Marie (id=2) : 2 echeances
    (2, 'Inscription', 20000.00, '2023-10-01'),
    (2, 'Mois 1', 15000.00, '2023-11-15'),
    -- Pierre (id=3) : tranches - 4 echeances
    (3, 'Premier versement', 15000.00, '2023-10-15'),
    (3, 'Deuxieme versement', 15000.00, '2023-11-15'),
    (3, 'Troisieme versement', 10000.00, '2023-12-15'),
    (3, 'Quatrieme versement', 5000.00, '2024-01-15'),
    -- Sophie (id=4) : 1 echeance
    (4, 'Reglement unique', 40000.00, '2023-12-01'),
    -- Jean (id=6) : reinscription 2026-2027 par tranches
    (6, 'Tranche 1', 20000.00, '2026-10-01'),
    (6, 'Tranche 2', 15000.00, '2026-11-15'),
    (6, 'Tranche 3', 10000.00, '2026-12-15')
    -- Paul (id=5) : termine, pas d'echeances
    -- (pas d'echeances pour l'etudiant termine)
;

-- -----------------------------------------------------
-- 8. Paiements (jamais supprimes physiquement)
-- -----------------------------------------------------
DELETE FROM affectations_paiements;
DELETE FROM paiements;
ALTER TABLE paiements AUTO_INCREMENT = 1;
ALTER TABLE affectations_paiements AUTO_INCREMENT = 1;
INSERT INTO paiements (inscription_id, utilisateur_id, numero_recu, montant, mode_paiement, reference, observation, date_paiement, statut, motif_annulation, annule_par, date_annulation) VALUES
    -- Jean - paiement inscription
    (1, 1, 'REC-2023-001', 20000.00, 'especes', 'REF-2023-001', 'Reglement inscription', '2023-10-01', 'valide', NULL, NULL, NULL),
    -- Jean - paiement mois 1
    (1, 1, 'REC-2023-010', 10000.00, 'orange_money', 'REF-2023-010', 'Premier versement', '2023-11-01', 'valide', NULL, NULL, NULL),
    -- Jean - paiement mois 2
    (1, 1, 'REC-2023-020', 10000.00, 'virement', 'REF-2023-020', 'Deuxieme versement', '2023-12-01', 'valide', NULL, NULL, NULL),
    -- Marie - paiement inscription
    (2, 1, 'REC-2023-030', 15000.00, 'especes', 'REF-2023-030', 'Part inscription', '2023-10-05', 'valide', NULL, NULL, NULL),
    -- Marie - paiement mois 1
    (2, 1, 'REC-2023-040', 15000.00, 'mtn_mobile_money', 'REF-2023-040', 'Premier versement', '2023-11-15', 'valide', NULL, NULL, NULL),
    -- Pierre - premier versement tranches
    (3, 1, 'REC-2023-050', 15000.00, 'virement', 'REF-2023-050', 'Premier versement tranches', '2023-10-15', 'valide', NULL, NULL, NULL),
    -- Pierre - deuxieme versement tranches
    (3, 1, 'REC-2023-060', 15000.00, 'orange_money', 'REF-2023-060', 'Deuxieme versement', '2023-11-15', 'valide', NULL, NULL, NULL),
    -- Pierre - troisieme versement tranches
    (3, 1, 'REC-2023-070', 10000.00, 'virement', 'REF-2023-070', 'Troisieme versement', '2023-12-15', 'valide', NULL, NULL, NULL),
    -- Sophie - reglement unique
    (4, 1, 'REC-2023-080', 40000.00, 'virement', 'REF-2023-080', 'Reglement complet', '2023-11-20', 'valide', NULL, NULL, NULL),
    -- Annulation tracée de Paul (plusieur paiements mais étudiant termine)
    (5, 1, 'REC-2023-090', 20000.00, 'especes', 'REF-2023-090', 'Reglement inscription', '2023-10-10', 'annule', 'Demande changement formation', 1, '2023-10-11'),
    -- Jean - premiere tranche 2026-2027 (paiement recent)
    (6, 1, 'REC-2026-011', 20000.00, 'orange_money', 'REF-2026-011', 'Premiere tranche 2026-2027', '2026-11-19', 'valide', NULL, NULL, NULL);

-- -----------------------------------------------------
-- 9. Affectations paiement -> echeance
-- -----------------------------------------------------
DELETE FROM affectations_paiements;
INSERT INTO affectations_paiements (paiement_id, echeance_id, inscription_id, montant_affecte) VALUES
    -- Jean: inscription 20000 -> echeance id=1 (Inscription)
    (1, 1, 1, 20000.00),
    -- Jean: mois 1 10000 -> echeance id=2 (Mois 1)
    (2, 2, 1, 10000.00),
    -- Jean: mois 2 10000 -> echeance id=3 (Mois 2)
    (3, 3, 1, 10000.00),
    -- Marie: inscription 15000 -> echeance id=4 (Inscription)
    (4, 4, 2, 15000.00),
    -- Marie: mois 1 15000 -> echeance id=5 (Mois 1)
    (5, 5, 2, 15000.00),
    -- Pierre: premier versement 15000 -> echeance id=6 (Premier versement)
    (6, 6, 3, 15000.00),
    -- Pierre: deuxieme versement 15000 -> echeance id=7 (Deuxieme versement)
    (7, 7, 3, 15000.00),
    -- Pierre: troisieme versement 10000 -> echeance id=8 (Troisieme versement)
    (8, 8, 3, 10000.00),
    -- Sophie: reglement unique 40000 -> echeance id=9 (Reglement unique)
    (9, 9, 4, 40000.00),
    -- Jean: tranche 1 2026-2027 20000 -> echeance id=11
    (11, 11, 6, 20000.00);

-- -----------------------------------------------------
-- 10. Journal d'activites (historique)
-- -----------------------------------------------------
DELETE FROM journal_activites;
ALTER TABLE journal_activites AUTO_INCREMENT = 1;
INSERT INTO journal_activites (utilisateur_id, action, entite, entite_id, details, adresse_ip) VALUES
    (1, 'connexion', 'utilisateur', 1, 'Connexion reussie', '127.0.0.1'),
    (1, 'creation', 'inscription', 1, 'Nouvelle inscription Informatique - Jean', '127.0.0.1'),
    (1, 'paiement', 'paiement', 1, 'Paiement inscription 20000 CFA', '127.0.0.1'),
    (1, 'annulation', 'inscription', 5, 'Annulation inscription Paul - CF-2023-005', '127.0.0.1'),
    (1, 'modification', 'parametres', NULL, 'Mise a jour parametres centre', '127.0.0.1');

SET FOREIGN_KEY_CHECKS = 1;
SELECT 'Seeds termine avec succes !' AS message;