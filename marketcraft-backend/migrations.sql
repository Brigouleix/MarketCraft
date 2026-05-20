-- =============================================================
--  MarketCraft – Schéma de base de données complet
--  Encodage : utf8mb4 / Moteur : InnoDB
-- =============================================================

CREATE DATABASE IF NOT EXISTS `marketcraft`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `marketcraft`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- -------------------------------------------------------------
-- Table : utilisateurs
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `utilisateurs` (
  `id`            INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `nom`           VARCHAR(100)     NOT NULL,
  `prenom`        VARCHAR(100)     NOT NULL,
  `email`         VARCHAR(191)     NOT NULL,
  `password_hash` VARCHAR(255)     NOT NULL,
  `role`          ENUM('client','vendeur','admin') NOT NULL DEFAULT 'client',
  `avatar_url`    VARCHAR(500)     DEFAULT NULL,
  `telephone`     VARCHAR(20)      DEFAULT NULL,
  `est_actif`     TINYINT(1)       NOT NULL DEFAULT 1,
  `created_at`    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_utilisateurs_email` (`email`),
  KEY `idx_utilisateurs_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table : adresses_livraison
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `adresses_livraison` (
  `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `utilisateur_id` INT UNSIGNED  NOT NULL,
  `nom_complet`    VARCHAR(200)  NOT NULL,
  `ligne1`         VARCHAR(255)  NOT NULL,
  `ligne2`         VARCHAR(255)  DEFAULT NULL,
  `ville`          VARCHAR(100)  NOT NULL,
  `code_postal`    VARCHAR(20)   NOT NULL,
  `pays`           VARCHAR(100)  NOT NULL DEFAULT 'France',
  `est_principale` TINYINT(1)    NOT NULL DEFAULT 0,
  `created_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_adresses_utilisateur` (`utilisateur_id`),
  CONSTRAINT `fk_adresses_utilisateur`
    FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table : boutiques
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `boutiques` (
  `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `vendeur_id`   INT UNSIGNED  NOT NULL,
  `nom`          VARCHAR(150)  NOT NULL,
  `slug`         VARCHAR(160)  NOT NULL,
  `description`  TEXT          DEFAULT NULL,
  `logo_url`     VARCHAR(500)  DEFAULT NULL,
  `banniere_url` VARCHAR(500)  DEFAULT NULL,
  `est_active`   TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_boutiques_slug` (`slug`),
  KEY `idx_boutiques_vendeur` (`vendeur_id`),
  CONSTRAINT `fk_boutiques_vendeur`
    FOREIGN KEY (`vendeur_id`) REFERENCES `utilisateurs` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table : categories
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `parent_id`   INT UNSIGNED  DEFAULT NULL,
  `nom`         VARCHAR(100)  NOT NULL,
  `slug`        VARCHAR(110)  NOT NULL,
  `description` TEXT          DEFAULT NULL,
  `image_url`   VARCHAR(500)  DEFAULT NULL,
  `ordre`       SMALLINT      NOT NULL DEFAULT 0,
  `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categories_slug` (`slug`),
  KEY `idx_categories_parent` (`parent_id`),
  CONSTRAINT `fk_categories_parent`
    FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table : produits
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `produits` (
  `id`            INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  `boutique_id`   INT UNSIGNED      NOT NULL,
  `categorie_id`  INT UNSIGNED      DEFAULT NULL,
  `nom`           VARCHAR(200)      NOT NULL,
  `slug`          VARCHAR(220)      NOT NULL,
  `description`   TEXT              DEFAULT NULL,
  `prix`          DECIMAL(10,2)     NOT NULL,
  `stock`         INT               NOT NULL DEFAULT 0,
  `images`        JSON              DEFAULT NULL,
  `tags`          JSON              DEFAULT NULL,
  `est_actif`     TINYINT(1)        NOT NULL DEFAULT 1,
  `est_fait_main` TINYINT(1)        NOT NULL DEFAULT 1,
  `created_at`    DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_produits_slug` (`slug`),
  KEY `idx_produits_boutique`  (`boutique_id`),
  KEY `idx_produits_categorie` (`categorie_id`),
  KEY `idx_produits_prix`      (`prix`),
  KEY `idx_produits_actif`     (`est_actif`),
  FULLTEXT KEY `ft_produits_nom_desc` (`nom`, `description`),
  CONSTRAINT `fk_produits_boutique`
    FOREIGN KEY (`boutique_id`) REFERENCES `boutiques` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_produits_categorie`
    FOREIGN KEY (`categorie_id`) REFERENCES `categories` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table : commandes
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `commandes` (
  `id`                  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `utilisateur_id`      INT UNSIGNED  NOT NULL,
  `adresse_livraison_id` INT UNSIGNED DEFAULT NULL,
  `statut`              ENUM('en_attente','confirmee','en_preparation','expediee','livree','annulee')
                        NOT NULL DEFAULT 'en_attente',
  `montant_total`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `frais_livraison`     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `note`                TEXT          DEFAULT NULL,
  `numero_suivi`        VARCHAR(100)  DEFAULT NULL,
  `created_at`          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_commandes_utilisateur` (`utilisateur_id`),
  KEY `idx_commandes_statut`      (`statut`),
  CONSTRAINT `fk_commandes_utilisateur`
    FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_commandes_adresse`
    FOREIGN KEY (`adresse_livraison_id`) REFERENCES `adresses_livraison` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table : lignes_commande
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `lignes_commande` (
  `id`           INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `commande_id`  INT UNSIGNED   NOT NULL,
  `produit_id`   INT UNSIGNED   NOT NULL,
  `quantite`     SMALLINT       NOT NULL DEFAULT 1,
  `prix_unitaire` DECIMAL(10,2) NOT NULL,
  `nom_produit`  VARCHAR(200)   NOT NULL COMMENT 'Snapshot au moment de la commande',
  PRIMARY KEY (`id`),
  KEY `idx_lignes_commande`  (`commande_id`),
  KEY `idx_lignes_produit`   (`produit_id`),
  CONSTRAINT `fk_lignes_commande`
    FOREIGN KEY (`commande_id`) REFERENCES `commandes` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_lignes_produit`
    FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table : paiements
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `paiements` (
  `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `commande_id`    INT UNSIGNED  NOT NULL,
  `methode`        ENUM('carte','virement','paypal','cheque') NOT NULL DEFAULT 'carte',
  `statut`         ENUM('en_attente','valide','refuse','rembourse') NOT NULL DEFAULT 'en_attente',
  `montant`        DECIMAL(10,2) NOT NULL,
  `transaction_id` VARCHAR(255)  DEFAULT NULL,
  `payload`        JSON          DEFAULT NULL COMMENT 'Réponse brute du prestataire de paiement',
  `created_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_paiements_commande`     (`commande_id`),
  KEY `idx_paiements_transaction`  (`transaction_id`),
  CONSTRAINT `fk_paiements_commande`
    FOREIGN KEY (`commande_id`) REFERENCES `commandes` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table : avis
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `avis` (
  `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `produit_id`     INT UNSIGNED  NOT NULL,
  `utilisateur_id` INT UNSIGNED  NOT NULL,
  `note`           TINYINT       NOT NULL COMMENT 'Note de 1 à 5',
  `titre`          VARCHAR(150)  DEFAULT NULL,
  `commentaire`    TEXT          DEFAULT NULL,
  `est_verifie`    TINYINT(1)    NOT NULL DEFAULT 0,
  `created_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_avis_produit_user` (`produit_id`, `utilisateur_id`),
  KEY `idx_avis_produit`      (`produit_id`),
  KEY `idx_avis_utilisateur`  (`utilisateur_id`),
  CONSTRAINT `fk_avis_produit`
    FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_avis_utilisateur`
    FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_avis_note` CHECK (`note` BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
--  Données de test
-- =============================================================

-- Utilisateurs (passwords = "password123" hashé avec bcrypt)
INSERT INTO `utilisateurs` (`nom`, `prenom`, `email`, `password_hash`, `role`) VALUES
  ('Dupont',   'Marie',   'marie.dupont@example.com',   '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
  ('Martin',   'Paul',    'paul.martin@example.com',    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'vendeur'),
  ('Bernard',  'Sophie',  'sophie.bernard@example.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'vendeur'),
  ('Lemoine',  'Jules',   'jules.lemoine@example.com',  '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'client'),
  ('Petit',    'Camille', 'camille.petit@example.com',  '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'client');

-- Adresses
INSERT INTO `adresses_livraison` (`utilisateur_id`, `nom_complet`, `ligne1`, `ville`, `code_postal`, `pays`, `est_principale`) VALUES
  (4, 'Jules Lemoine',   '12 rue des Fleurs',    'Lyon',  '69001', 'France', 1),
  (5, 'Camille Petit',   '8 avenue des Artisans', 'Paris', '75011', 'France', 1);

-- Boutiques
INSERT INTO `boutiques` (`vendeur_id`, `nom`, `slug`, `description`) VALUES
  (2, 'L\'Atelier de Paul',  'atelier-de-paul',  'Créations en bois fait main, sculptures et objets décoratifs uniques.'),
  (3, 'Sophie Céramiques',   'sophie-ceramiques', 'Poteries et céramiques artisanales inspirées de la nature.');

-- Catégories
INSERT INTO `categories` (`nom`, `slug`, `description`, `ordre`) VALUES
  ('Bois & Menuiserie', 'bois-menuiserie', 'Objets et meubles en bois travaillés à la main',  1),
  ('Céramique & Poterie', 'ceramique-poterie', 'Pièces uniques façonnées en argile', 2),
  ('Bijoux & Accessoires', 'bijoux-accessoires', 'Bijoux artisanaux et accessoires faits main', 3),
  ('Textile & Couture', 'textile-couture', 'Vêtements, sacs et décorations textiles', 4),
  ('Décoration Maison', 'decoration-maison', 'Objets décoratifs pour embellir votre intérieur', 5);

-- Produits
INSERT INTO `produits` (`boutique_id`, `categorie_id`, `nom`, `slug`, `description`, `prix`, `stock`, `images`) VALUES
  (1, 1, 'Bol en noyer ciré',        'bol-noyer-cire',        'Bol tournée à la main en noyer massif, finition cire d\'abeille naturelle. Diamètre 20 cm.',          45.00, 12, '["bol-noyer-1.jpg","bol-noyer-2.jpg"]'),
  (1, 1, 'Planche à découper chêne', 'planche-decoupe-chene', 'Planche à découper en chêne massif avec poignée sculptée. 35×25 cm, épaisseur 3 cm.',               65.00,  8, '["planche-1.jpg"]'),
  (1, 5, 'Cadre photo rustique',     'cadre-photo-rustique',  'Cadre photo en bois flotté récupéré, format 15×20 cm. Finition naturelle.',                            28.00, 20, '["cadre-1.jpg","cadre-2.jpg"]'),
  (2, 2, 'Mug grès bleu océan',      'mug-gres-bleu-ocean',   'Mug en grès émaillé à la main, nuances de bleu. Contenance 350 ml. Passe au lave-vaisselle.',          38.00, 15, '["mug-bleu-1.jpg"]'),
  (2, 2, 'Vase effilé terracotta',   'vase-effile-terracotta','Vase effilé en terracotta non émaillée, hauteur 30 cm. Idéal pour fleurs séchées.',                    55.00,  6, '["vase-terra-1.jpg","vase-terra-2.jpg"]'),
  (2, 5, 'Assiette creuse fleurie',  'assiette-creuse-fleurie','Assiette creuse peinte à la main avec motifs floraux. Diamètre 22 cm. Faite main unique.',            42.00, 10, '["assiette-1.jpg"]');

-- Commandes
INSERT INTO `commandes` (`utilisateur_id`, `adresse_livraison_id`, `statut`, `montant_total`, `frais_livraison`) VALUES
  (4, 1, 'livree',      103.00, 5.90),
  (5, 2, 'en_preparation', 80.00, 5.90);

-- Lignes de commande
INSERT INTO `lignes_commande` (`commande_id`, `produit_id`, `quantite`, `prix_unitaire`, `nom_produit`) VALUES
  (1, 1, 2, 45.00, 'Bol en noyer ciré'),
  (1, 3, 1, 28.00, 'Cadre photo rustique'),
  (2, 4, 1, 38.00, 'Mug grès bleu océan'),
  (2, 5, 1, 55.00, 'Vase effilé terracotta');

-- Paiements
INSERT INTO `paiements` (`commande_id`, `methode`, `statut`, `montant`, `transaction_id`) VALUES
  (1, 'carte',  'valide',     108.90, 'TXN-20260101-001'),
  (2, 'paypal', 'valide',      85.90, 'TXN-20260115-002');

-- Avis
INSERT INTO `avis` (`produit_id`, `utilisateur_id`, `note`, `titre`, `commentaire`, `est_verifie`) VALUES
  (1, 4, 5, 'Superbe qualité !',     'Le bol est magnifique et très bien fini. Je recommande vivement cet artisan.',  1),
  (3, 4, 4, 'Très joli cadre',       'Beau produit, livraison rapide. Le bois flotté donne un charme naturel.',       1),
  (4, 5, 5, 'Parfait pour le café',  'Le mug est lourd et solide, exactement ce que je cherchais. Belle couleur.',    0);

SET FOREIGN_KEY_CHECKS = 1;
