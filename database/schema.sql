-- ---------------------------------------------------------------------------
-- Centre Manager - schema de base de donnees
-- Compatible MySQL 5.7+ / 8.0+ (InnoDB, utf8mb4)
-- ---------------------------------------------------------------------------
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS journal_activites;
DROP TABLE IF EXISTS affectations_paiements;
DROP TABLE IF EXISTS paiements;
DROP TABLE IF EXISTS echeances;
DROP TABLE IF EXISTS inscriptions;
DROP TABLE IF EXISTS annees_academiques;
DROP TABLE IF EXISTS formations;
DROP TABLE IF EXISTS etudiants;
DROP TABLE IF EXISTS utilisateurs;
DROP TABLE IF EXISTS parametres;
DROP TABLE IF EXISTS compteurs;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------------
-- Utilisateurs
-- ---------------------------------------------------------------------------
CREATE TABLE utilisateurs (
    id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nom_complet          VARCHAR(150) NOT NULL,
    nom_utilisateur      VARCHAR(60)  NOT NULL,
    email                VARCHAR(190) NULL,
    mot_de_passe         VARCHAR(255) NOT NULL,
    role                 ENUM('administrateur', 'secretaire') NOT NULL DEFAULT 'secretaire',
    statut               ENUM('actif', 'inactif') NOT NULL DEFAULT 'actif',
    tentatives_echecs    TINYINT UNSIGNED NOT NULL DEFAULT 0,
    verrouille_jusqu_a   DATETIME NULL,
    derniere_connexion   DATETIME NULL,
    created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_utilisateurs_nom_utilisateur (nom_utilisateur),
    KEY idx_utilisateurs_role (role),
    KEY idx_utilisateurs_statut (statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Etudiants
-- ---------------------------------------------------------------------------
CREATE TABLE etudiants (
    id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    matricule            VARCHAR(30)  NOT NULL,
    nom                  VARCHAR(100) NOT NULL,
    prenom               VARCHAR(100) NOT NULL,
    sexe                 ENUM('M', 'F') NOT NULL,
    date_naissance       DATE NULL,
    telephone            VARCHAR(30)  NOT NULL,
    adresse              VARCHAR(255) NULL,
    date_enregistrement  DATE NOT NULL,
    statut               ENUM('actif', 'suspendu', 'termine', 'archive') NOT NULL DEFAULT 'actif',
    created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_etudiants_matricule (matricule),
    KEY idx_etudiants_nom (nom, prenom),
    KEY idx_etudiants_telephone (telephone),
    KEY idx_etudiants_statut (statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Formations
-- ---------------------------------------------------------------------------
CREATE TABLE formations (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nom              VARCHAR(150) NOT NULL,
    description      TEXT NULL,
    frais_formation  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    statut           ENUM('actif', 'inactif') NOT NULL DEFAULT 'actif',
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_formations_nom (nom),
    KEY idx_formations_statut (statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Annees academiques
-- ---------------------------------------------------------------------------
CREATE TABLE annees_academiques (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    libelle     VARCHAR(20) NOT NULL,
    date_debut  DATE NOT NULL,
    date_fin    DATE NOT NULL,
    statut      ENUM('ouverte', 'cloturee') NOT NULL DEFAULT 'ouverte',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_annees_libelle (libelle),
    KEY idx_annees_statut (statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Inscriptions : un dossier financier autonome
-- ---------------------------------------------------------------------------
CREATE TABLE inscriptions (
    id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    etudiant_id          INT UNSIGNED NOT NULL,
    formation_id         INT UNSIGNED NOT NULL,
    annee_academique_id  INT UNSIGNED NOT NULL,
    date_inscription     DATE NOT NULL,
    montant_total        DECIMAL(12,2) NOT NULL,
    montant_initial      DECIMAL(12,2) NOT NULL,
    mode_paiement        ENUM('libre', 'tranches') NOT NULL DEFAULT 'libre',
    statut               ENUM('active', 'terminee', 'annulee') NOT NULL DEFAULT 'active',
    observation          VARCHAR(500) NULL,
    motif_annulation     VARCHAR(500) NULL,
    created_by           INT UNSIGNED NULL,
    created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_inscriptions_etudiant_annee (etudiant_id, annee_academique_id),
    KEY idx_inscriptions_formation (formation_id),
    KEY idx_inscriptions_annee (annee_academique_id),
    KEY idx_inscriptions_statut (statut),
    KEY idx_inscriptions_mode (mode_paiement),
    CONSTRAINT fk_inscriptions_etudiant FOREIGN KEY (etudiant_id)
        REFERENCES etudiants (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_inscriptions_formation FOREIGN KEY (formation_id)
        REFERENCES formations (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_inscriptions_annee FOREIGN KEY (annee_academique_id)
        REFERENCES annees_academiques (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_inscriptions_utilisateur FOREIGN KEY (created_by)
        REFERENCES utilisateurs (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Echeances
-- ---------------------------------------------------------------------------
CREATE TABLE echeances (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    inscription_id   INT UNSIGNED NOT NULL,
    libelle          VARCHAR(150) NOT NULL,
    montant_attendu  DECIMAL(12,2) NOT NULL,
    date_echeance    DATE NOT NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_echeances_id_inscription (id, inscription_id),
    KEY idx_echeances_inscription (inscription_id),
    KEY idx_echeances_date (date_echeance),
    CONSTRAINT fk_echeances_inscription FOREIGN KEY (inscription_id)
        REFERENCES inscriptions (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Paiements (jamais supprimes physiquement)
-- ---------------------------------------------------------------------------
CREATE TABLE paiements (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    inscription_id    INT UNSIGNED NOT NULL,
    utilisateur_id    INT UNSIGNED NOT NULL,
    numero_recu       VARCHAR(30) NULL,
    montant           DECIMAL(12,2) NOT NULL,
    mode_paiement     ENUM('especes', 'orange_money', 'mtn_mobile_money', 'virement', 'autre') NOT NULL DEFAULT 'especes',
    reference         VARCHAR(100) NULL,
    observation       VARCHAR(500) NULL,
    date_paiement     DATETIME NOT NULL,
    statut            ENUM('valide', 'annule') NOT NULL DEFAULT 'valide',
    motif_annulation  VARCHAR(500) NULL,
    annule_par        INT UNSIGNED NULL,
    date_annulation   DATETIME NULL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_paiements_recu (numero_recu),
    UNIQUE KEY uq_paiements_id_inscription (id, inscription_id),
    KEY idx_paiements_inscription (inscription_id),
    KEY idx_paiements_date (date_paiement),
    KEY idx_paiements_statut (statut),
    KEY idx_paiements_mode (mode_paiement),
    CONSTRAINT fk_paiements_inscription FOREIGN KEY (inscription_id)
        REFERENCES inscriptions (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_paiements_utilisateur FOREIGN KEY (utilisateur_id)
        REFERENCES utilisateurs (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_paiements_annule_par FOREIGN KEY (annule_par)
        REFERENCES utilisateurs (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Affectations paiement -> echeance
-- Les FK composites garantissent que paiement et echeance appartiennent
-- a la meme inscription.
-- ---------------------------------------------------------------------------
CREATE TABLE affectations_paiements (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    paiement_id      INT UNSIGNED NOT NULL,
    echeance_id      INT UNSIGNED NOT NULL,
    inscription_id   INT UNSIGNED NOT NULL,
    montant_affecte  DECIMAL(12,2) NOT NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_affectations_paiement_echeance (paiement_id, echeance_id),
    KEY idx_affectations_echeance (echeance_id),
    KEY idx_affectations_inscription (inscription_id),
    KEY idx_affectations_composite_paiement (paiement_id, inscription_id),
    KEY idx_affectations_composite_echeance (echeance_id, inscription_id),
    CONSTRAINT fk_affectations_paiement FOREIGN KEY (paiement_id, inscription_id)
        REFERENCES paiements (id, inscription_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_affectations_echeance FOREIGN KEY (echeance_id, inscription_id)
        REFERENCES echeances (id, inscription_id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Journal d'activites
-- ---------------------------------------------------------------------------
CREATE TABLE journal_activites (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    utilisateur_id INT UNSIGNED NULL,
    action         VARCHAR(60) NOT NULL,
    entite         VARCHAR(60) NOT NULL,
    entite_id      INT UNSIGNED NULL,
    details        TEXT NULL,
    adresse_ip     VARCHAR(45) NULL,
    date_action    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_journal_utilisateur (utilisateur_id),
    KEY idx_journal_entite (entite, entite_id),
    KEY idx_journal_date (date_action),
    CONSTRAINT fk_journal_utilisateur FOREIGN KEY (utilisateur_id)
        REFERENCES utilisateurs (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Parametres applicatifs (cle/valeur)
-- ---------------------------------------------------------------------------
CREATE TABLE parametres (
    cle         VARCHAR(60) NOT NULL,
    valeur      TEXT NULL,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (cle)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Compteurs (sequences sures : matricules, etc.)
-- ---------------------------------------------------------------------------
CREATE TABLE compteurs (
    cle     VARCHAR(60) NOT NULL,
    valeur  INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (cle)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO parametres (cle, valeur) VALUES
    ('centre_nom', 'PharaonAcademia'),
    ('centre_adresse', 'Avenue de la Formation, Douala'),
    ('centre_telephone', '+237 6 00 00 00 00'),
    ('centre_email', 'contact@centre-exemple.local'),
    ('matricule_prefixe', 'CF'),
    ('devise', 'XAF');
