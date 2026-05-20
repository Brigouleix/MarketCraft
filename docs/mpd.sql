-- ============================================================
-- MarketCraft — Modèle Physique de Données (MPD)
-- SGBD : MySQL 8+ / MariaDB 10.4+
-- Encodage : utf8mb4 / utf8mb4_unicode_ci
-- ============================================================

CREATE DATABASE IF NOT EXISTS marketcraft
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE marketcraft;

-- ─────────────────────────────────────────────────────────────
-- TABLE : utilisateurs
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS utilisateurs (
  id           INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  nom          VARCHAR(100)     NOT NULL,
  prenom       VARCHAR(100)     NOT NULL,
  email        VARCHAR(255)     NOT NULL,
  password_hash VARCHAR(255)    NOT NULL,
  role         ENUM('client','vendeur','admin') NOT NULL DEFAULT 'client',
  telephone    VARCHAR(20)      NULL,
  avatar_url   VARCHAR(500)     NULL,
  est_actif    TINYINT(1)       NOT NULL DEFAULT 1,
  -- Brute-force protection
  login_attempts   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  locked_until     DATETIME         NULL,
  last_login_at    DATETIME         NULL,
  created_at   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE  KEY uq_email           (email),
  INDEX   idx_role               (role),
  INDEX   idx_est_actif          (est_actif)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- TABLE : boutiques
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS boutiques (
  id           INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  vendeur_id   INT UNSIGNED     NOT NULL,
  nom          VARCHAR(150)     NOT NULL,
  description  TEXT             NULL,
  logo_url     VARCHAR(500)     NULL,
  banniere_url VARCHAR(500)     NULL,
  localisation VARCHAR(200)     NULL,
  site_web     VARCHAR(300)     NULL,
  est_active   TINYINT(1)       NOT NULL DEFAULT 1,
  created_at   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE  KEY uq_vendeur_boutique (vendeur_id),
  INDEX   idx_est_active          (est_active),
  CONSTRAINT fk_boutique_vendeur
    FOREIGN KEY (vendeur_id) REFERENCES utilisateurs(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- TABLE : categories
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS categories (
  id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nom    VARCHAR(100) NOT NULL,
  slug   VARCHAR(110) NOT NULL,
  icone  VARCHAR(50)  NULL,

  PRIMARY KEY (id),
  UNIQUE KEY uq_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- TABLE : produits
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS produits (
  id           INT UNSIGNED       NOT NULL AUTO_INCREMENT,
  boutique_id  INT UNSIGNED       NOT NULL,
  categorie_id INT UNSIGNED       NULL,
  nom          VARCHAR(200)       NOT NULL,
  description  TEXT               NULL,
  prix         DECIMAL(10, 2)     NOT NULL,
  stock        INT UNSIGNED       NOT NULL DEFAULT 0,
  tags         VARCHAR(500)       NULL COMMENT 'CSV de mots-clés, ex: bois,chêne,artisanal',
  images       JSON               NULL COMMENT 'Tableau JSON d URLs',
  poids_g      INT UNSIGNED       NULL,
  dimensions   VARCHAR(100)       NULL,
  est_actif    TINYINT(1)         NOT NULL DEFAULT 1,
  vues         INT UNSIGNED       NOT NULL DEFAULT 0,
  created_at   DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  INDEX   idx_boutique        (boutique_id),
  INDEX   idx_categorie       (categorie_id),
  INDEX   idx_prix            (prix),
  INDEX   idx_est_actif       (est_actif),
  FULLTEXT idx_ft_search      (nom, description, tags),
  CONSTRAINT fk_produit_boutique
    FOREIGN KEY (boutique_id) REFERENCES boutiques(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_produit_categorie
    FOREIGN KEY (categorie_id) REFERENCES categories(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT chk_prix_positif CHECK (prix >= 0),
  CONSTRAINT chk_stock_positif CHECK (stock >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- TABLE : commandes
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS commandes (
  id                  INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  utilisateur_id      INT UNSIGNED   NOT NULL,
  statut              ENUM('en_attente','confirmee','expediee','livree','annulee')
                                     NOT NULL DEFAULT 'en_attente',
  total               DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
  adresse_livraison   TEXT           NOT NULL,
  notes               TEXT           NULL,
  created_at          DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  INDEX   idx_utilisateur     (utilisateur_id),
  INDEX   idx_statut          (statut),
  CONSTRAINT fk_commande_user
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- TABLE : lignes_commande
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS lignes_commande (
  id             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  commande_id    INT UNSIGNED  NOT NULL,
  produit_id     INT UNSIGNED  NOT NULL,
  quantite       INT UNSIGNED  NOT NULL DEFAULT 1,
  prix_unitaire  DECIMAL(10,2) NOT NULL,

  PRIMARY KEY (id),
  INDEX   idx_commande        (commande_id),
  INDEX   idx_produit         (produit_id),
  CONSTRAINT fk_ligne_commande
    FOREIGN KEY (commande_id) REFERENCES commandes(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_ligne_produit
    FOREIGN KEY (produit_id) REFERENCES produits(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_quantite CHECK (quantite > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- TABLE : paiements
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS paiements (
  id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  commande_id     INT UNSIGNED  NOT NULL,
  montant         DECIMAL(10,2) NOT NULL,
  methode         ENUM('carte','paypal','virement') NOT NULL,
  statut          ENUM('en_attente','valide','echec','rembourse') NOT NULL DEFAULT 'en_attente',
  reference_ext   VARCHAR(200)  NULL COMMENT 'ID Stripe / PayPal',
  created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE  KEY uq_commande (commande_id),
  CONSTRAINT fk_paiement_commande
    FOREIGN KEY (commande_id) REFERENCES commandes(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- TABLE : avis
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS avis (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  utilisateur_id INT UNSIGNED NOT NULL,
  produit_id     INT UNSIGNED NOT NULL,
  note           TINYINT      NOT NULL,
  commentaire    TEXT         NULL,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE  KEY uq_avis_user_produit (utilisateur_id, produit_id),
  INDEX   idx_produit              (produit_id),
  CONSTRAINT fk_avis_user
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_avis_produit
    FOREIGN KEY (produit_id) REFERENCES produits(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT chk_note CHECK (note BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- TABLE : logs_activite (sécurité back-office)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS logs_activite (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  utilisateur_id INT UNSIGNED NULL,
  action         VARCHAR(100) NOT NULL,
  entite         VARCHAR(50)  NULL COMMENT 'ex: produit, commande',
  entite_id      INT UNSIGNED NULL,
  ip_address     VARCHAR(45)  NULL,
  user_agent     VARCHAR(500) NULL,
  donnees        JSON         NULL COMMENT 'Données contextuelles',
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  INDEX idx_utilisateur (utilisateur_id),
  INDEX idx_action      (action),
  INDEX idx_created_at  (created_at),
  CONSTRAINT fk_log_user
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- TABLE : tentatives_connexion (brute-force tracking)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS tentatives_connexion (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email      VARCHAR(255) NOT NULL,
  ip_address VARCHAR(45)  NOT NULL,
  succes     TINYINT(1)   NOT NULL DEFAULT 0,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  INDEX idx_email      (email),
  INDEX idx_ip         (ip_address),
  INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
