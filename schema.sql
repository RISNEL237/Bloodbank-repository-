-- ============================================================
--  SCHÉMA BASE DE DONNÉES — SangGestion
-- ============================================================
CREATE DATABASE IF NOT EXISTS gestion_sang
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE gestion_sang;

-- ── Utilisateurs ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS utilisateurs (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    nom        VARCHAR(80)  NOT NULL,
    prenom     VARCHAR(80)  NOT NULL,
    email      VARCHAR(150) NOT NULL UNIQUE,
    mot_de_passe VARCHAR(255) NOT NULL,
    role       ENUM('admin','agent','medecin') NOT NULL DEFAULT 'agent',
    actif      TINYINT(1) NOT NULL DEFAULT 1,
    cree_le    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modifie_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── Donneurs ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS donneurs (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    nom            VARCHAR(80)  NOT NULL,
    prenom         VARCHAR(80)  NOT NULL,
    sexe           ENUM('M','F') NOT NULL,
    telephone      VARCHAR(20),
    adresse        TEXT,
    groupe_sanguin ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
    date_inscription DATE NOT NULL DEFAULT (CURRENT_DATE),
    actif          TINYINT(1) NOT NULL DEFAULT 1,
    cree_le        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── Dons ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS dons (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    donneur_id  INT NOT NULL,
    quantite_ml INT NOT NULL DEFAULT 450,
    date_don    DATE NOT NULL,
    notes       TEXT,
    cree_le     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (donneur_id) REFERENCES donneurs(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ── Poches de sang ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS poches (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    don_id         INT,
    groupe_sanguin ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
    quantite_ml    INT NOT NULL DEFAULT 450,
    date_collecte  DATE NOT NULL,
    date_expiration DATE NOT NULL,
    etat           ENUM('disponible','distribue','expire','perime') NOT NULL DEFAULT 'disponible',
    notes          TEXT,
    cree_le        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (don_id) REFERENCES dons(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── Hôpitaux ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS hopitaux (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    nom       VARCHAR(150) NOT NULL,
    adresse   TEXT,
    telephone VARCHAR(20),
    email     VARCHAR(150),
    actif     TINYINT(1) NOT NULL DEFAULT 1,
    cree_le   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── Ventes / Distributions ────────────────────────────────────
CREATE TABLE IF NOT EXISTS ventes (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    poche_id      INT NOT NULL,
    hopital_id    INT,
    client_nom    VARCHAR(150),
    quantite_ml   INT NOT NULL,
    date_operation DATE NOT NULL,
    utilisateur_id INT NOT NULL,
    notes         TEXT,
    cree_le       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (poche_id)       REFERENCES poches(id)       ON DELETE RESTRICT,
    FOREIGN KEY (hopital_id)     REFERENCES hopitaux(id)     ON DELETE SET NULL,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ── Historique des opérations ─────────────────────────────────
CREATE TABLE IF NOT EXISTS historique (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT,
    action         VARCHAR(100) NOT NULL,
    table_cible    VARCHAR(50),
    enregistrement_id INT,
    detail         TEXT,
    ip             VARCHAR(45),
    cree_le        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── Données de démonstration ──────────────────────────────────
-- Administrateur par défaut  (mot de passe : Admin1234!)
INSERT IGNORE INTO utilisateurs (id, nom, prenom, email, mot_de_passe, role) VALUES
(1, 'Administrateur', 'Système', 'admin@sanggestion.com',
 '$2y$12$YourHashHere', 'admin');

-- Quelques hôpitaux
INSERT IGNORE INTO hopitaux (id, nom, adresse, telephone) VALUES
(1, 'Hôpital Général de la Paix', 'Avenue de la Paix, Kinshasa', '+243 81 000 0001'),
(2, 'Clinique Ngaliema',           'Route de Matadi, Kinshasa',   '+243 81 000 0002'),
(3, 'Centre Hospitalier Kabinda',  'Rue Kabinda, Kinshasa',       '+243 81 000 0003');
