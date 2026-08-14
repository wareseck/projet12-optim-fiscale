-- ============================================================
-- Projet 12 : Plateforme d'optimisation fiscale
-- Master CCA - ESP Dakar
-- Script de création de la base de données
-- ============================================================

CREATE DATABASE IF NOT EXISTS optim_fiscale
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE optim_fiscale;

-- ============================================================
-- Table : utilisateurs
-- Gère l'authentification et les rôles (admin, conseiller, client)
-- ============================================================
CREATE TABLE utilisateurs (
    id_utilisateur      INT AUTO_INCREMENT PRIMARY KEY,
    nom                 VARCHAR(100) NOT NULL,
    prenom              VARCHAR(100) NOT NULL,
    email               VARCHAR(150) NOT NULL UNIQUE,
    mot_de_passe        VARCHAR(255) NOT NULL,  -- password_hash() de PHP
    role                ENUM('admin', 'conseiller', 'client') NOT NULL DEFAULT 'client',
    actif               TINYINT(1) NOT NULL DEFAULT 1,
    date_creation       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    derniere_connexion  DATETIME NULL
) ENGINE=InnoDB;

-- ============================================================
-- Table : dossiers
-- Un dossier = une étude d'optimisation pour un dirigeant/entreprise
-- ============================================================
CREATE TABLE dossiers (
    id_dossier          INT AUTO_INCREMENT PRIMARY KEY,
    id_utilisateur      INT NOT NULL,           -- créateur du dossier (conseiller)
    id_client           INT NULL,               -- client concerné (optionnel)
    nom_dossier         VARCHAR(150) NOT NULL,
    nom_entreprise      VARCHAR(150) NULL,
    nom_dirigeant       VARCHAR(150) NULL,
    exercice            YEAR NOT NULL,
    statut              ENUM('brouillon', 'en_cours', 'finalise') NOT NULL DEFAULT 'brouillon',
    date_creation       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_modification   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_utilisateur) REFERENCES utilisateurs(id_utilisateur),
    FOREIGN KEY (id_client) REFERENCES utilisateurs(id_utilisateur)
) ENGINE=InnoDB;

-- ============================================================
-- Table : hypotheses
-- Les données de base saisies pour un dossier : CA, charges, résultat...
-- ============================================================
CREATE TABLE hypotheses (
    id_hypothese            INT AUTO_INCREMENT PRIMARY KEY,
    id_dossier               INT NOT NULL,
    chiffre_affaires         DECIMAL(15,2) NOT NULL,
    charges_hors_remuneration DECIMAL(15,2) NOT NULL,
    resultat_avant_remuneration DECIMAL(15,2) GENERATED ALWAYS AS
        (chiffre_affaires - charges_hors_remuneration) STORED,
    remuneration_nette_cible DECIMAL(15,2) NULL,  -- objectif de rémunération nette du dirigeant
    date_saisie              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_dossier) REFERENCES dossiers(id_dossier) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- Table : parametres_fiscaux
-- Barèmes et taux en vigueur, paramétrables (pas codés en dur)
-- ============================================================
CREATE TABLE parametres_fiscaux (
    id_parametre     INT AUTO_INCREMENT PRIMARY KEY,
    cle              VARCHAR(80) NOT NULL UNIQUE,   -- ex: 'taux_IS', 'taux_IPRES_salarie'
    libelle          VARCHAR(200) NOT NULL,
    valeur           DECIMAL(10,4) NOT NULL,        -- taux (0.30 = 30%) ou montant
    unite            ENUM('taux', 'montant') NOT NULL DEFAULT 'taux',
    exercice          YEAR NOT NULL,
    date_maj         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Table pour le barème IR progressif par tranches (paramétrable)
CREATE TABLE bareme_ir (
    id_tranche       INT AUTO_INCREMENT PRIMARY KEY,
    exercice         YEAR NOT NULL,
    borne_inferieure DECIMAL(15,2) NOT NULL,
    borne_superieure DECIMAL(15,2) NULL,   -- NULL = pas de plafond (dernière tranche)
    taux             DECIMAL(6,4) NOT NULL
) ENGINE=InnoDB;

-- ============================================================
-- Table : scenarios
-- Référentiel des types de scénarios comparés
-- ============================================================
CREATE TABLE scenarios (
    id_scenario      INT AUTO_INCREMENT PRIMARY KEY,
    code             VARCHAR(40) NOT NULL UNIQUE,  -- ex: 'EI_REEL', 'SARL_MINO', 'SA', 'HOLDING'
    libelle          VARCHAR(150) NOT NULL,
    description      TEXT NULL
) ENGINE=InnoDB;

-- ============================================================
-- Table : resultats_scenarios
-- Résultats calculés pour chaque scénario, pour un dossier donné
-- ============================================================
CREATE TABLE resultats_scenarios (
    id_resultat          INT AUTO_INCREMENT PRIMARY KEY,
    id_dossier           INT NOT NULL,
    id_scenario          INT NOT NULL,
    remuneration_brute    DECIMAL(15,2) NOT NULL DEFAULT 0,
    dividendes_bruts      DECIMAL(15,2) NOT NULL DEFAULT 0,
    is_du                 DECIMAL(15,2) NOT NULL DEFAULT 0,
    ir_dirigeant          DECIMAL(15,2) NOT NULL DEFAULT 0,
    cotisations_ipres     DECIMAL(15,2) NOT NULL DEFAULT 0,
    cotisations_css       DECIMAL(15,2) NOT NULL DEFAULT 0,
    irvm_dividendes        DECIMAL(15,2) NOT NULL DEFAULT 0,  -- retenue sur dividendes
    cout_total_entreprise DECIMAL(15,2) NOT NULL DEFAULT 0,
    remuneration_nette_dirigeant DECIMAL(15,2) NOT NULL DEFAULT 0,
    taux_prelevement_global DECIMAL(6,4) NOT NULL DEFAULT 0,
    date_calcul           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_dossier) REFERENCES dossiers(id_dossier) ON DELETE CASCADE,
    FOREIGN KEY (id_scenario) REFERENCES scenarios(id_scenario)
) ENGINE=InnoDB;

-- ============================================================
-- Table : simulations_optimisation
-- Résultat de la recherche du mix optimal salaire/dividende
-- ============================================================
CREATE TABLE simulations_optimisation (
    id_simulation         INT AUTO_INCREMENT PRIMARY KEY,
    id_dossier            INT NOT NULL,
    id_scenario           INT NOT NULL,
    part_salaire_optimale DECIMAL(6,4) NOT NULL,   -- % optimal en salaire
    part_dividende_optimale DECIMAL(6,4) NOT NULL, -- % optimal en dividende
    cout_total_minimal    DECIMAL(15,2) NOT NULL,
    date_simulation        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_dossier) REFERENCES dossiers(id_dossier) ON DELETE CASCADE,
    FOREIGN KEY (id_scenario) REFERENCES scenarios(id_scenario)
) ENGINE=InnoDB;

-- ============================================================
-- Table : audit_log
-- Journal d'audit horodaté des actions sensibles
-- ============================================================
CREATE TABLE audit_log (
    id_log           INT AUTO_INCREMENT PRIMARY KEY,
    id_utilisateur   INT NULL,
    action           VARCHAR(100) NOT NULL,   -- ex: 'CREATION_DOSSIER', 'CALCUL_SCENARIO'
    table_concernee  VARCHAR(60) NULL,
    id_enregistrement INT NULL,
    details          TEXT NULL,
    adresse_ip       VARCHAR(45) NULL,
    date_action      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_utilisateur) REFERENCES utilisateurs(id_utilisateur)
) ENGINE=InnoDB;

-- ============================================================
-- Index utiles pour les performances
-- ============================================================
CREATE INDEX idx_dossiers_utilisateur ON dossiers(id_utilisateur);
CREATE INDEX idx_resultats_dossier ON resultats_scenarios(id_dossier);
CREATE INDEX idx_hypotheses_dossier ON hypotheses(id_dossier);
CREATE INDEX idx_audit_utilisateur ON audit_log(id_utilisateur);
