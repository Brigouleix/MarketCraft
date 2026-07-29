-- =============================================================
--  MarketCraft – Schéma de base de données complet
--  Encodage : utf8mb4 / Moteur : InnoDB
-- =============================================================

CREATE DATABASE IF NOT EXISTS `marketcraft_3eme_dev`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `marketcraft_3eme_dev`;

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
  `note_moyenne` DECIMAL(3,2)  NOT NULL DEFAULT 0.00 COMMENT 'Recalculée automatiquement par trigger sur produits',
  `created_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_boutiques_slug` (`slug`),
  UNIQUE KEY `uq_boutiques_vendeur` (`vendeur_id`) COMMENT 'Règle de gestion : au plus une boutique par vendeur',
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
  `note_moyenne`  DECIMAL(3,2)      NOT NULL DEFAULT 0.00 COMMENT 'Recalculée automatiquement par trigger sur avis',
  `nombre_avis`   INT UNSIGNED      NOT NULL DEFAULT 0 COMMENT 'Recalculé automatiquement par trigger sur avis',
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
-- Table : produit_categorie (liaison N-N produits ↔ categories)
-- produits.categorie_id reste la catégorie principale (la 1re
-- sélectionnée) ; cette table porte l'ensemble des catégories.
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `produit_categorie` (
  `produit_id`   INT UNSIGNED NOT NULL,
  `categorie_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`produit_id`, `categorie_id`),
  KEY `idx_pc_categorie` (`categorie_id`),
  CONSTRAINT `fk_pc_produit`
    FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_pc_categorie`
    FOREIGN KEY (`categorie_id`) REFERENCES `categories` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reprise des catégories principales existantes dans la liaison
INSERT IGNORE INTO `produit_categorie` (`produit_id`, `categorie_id`)
SELECT `id`, `categorie_id` FROM `produits` WHERE `categorie_id` IS NOT NULL;

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
  `date_livraison`      DATETIME      DEFAULT NULL COMMENT 'Date de livraison effective, sert de base au calcul J+14',
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
  `statut`         ENUM('en_attente','valide','refuse','rembourse','libere') NOT NULL DEFAULT 'en_attente',
  `montant`        DECIMAL(10,2) NOT NULL,
  `transaction_id` VARCHAR(255)  DEFAULT NULL,
  `date_liberation` DATETIME     DEFAULT NULL COMMENT 'Renseignée par sp_liberer_paiements_echus() à J+14',
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
--  Composants dans le langage du SGBD (triggers, fonction,
--  procédure stockée, event) — CP8 du référentiel CDA
--  Toute la logique ci-dessous vit dans MySQL, indépendamment
--  du code PHP applicatif.
-- =============================================================

-- Permet de rejouer le script sur une base existante sans erreur #1359
DROP TRIGGER IF EXISTS `trg_avis_after_insert`;
DROP TRIGGER IF EXISTS `trg_avis_after_update`;
DROP TRIGGER IF EXISTS `trg_avis_after_delete`;
DROP TRIGGER IF EXISTS `trg_produits_after_update_note`;
DROP FUNCTION IF EXISTS `fn_stock_suffisant`;
DROP PROCEDURE IF EXISTS `sp_liberer_paiements_echus`;

DELIMITER $$

-- -------------------------------------------------------------
-- Triggers : recalcul automatique de la note moyenne d'un
-- produit à chaque écriture sur la table avis (règle de gestion
-- déjà documentée dans le MCD).
-- -------------------------------------------------------------

CREATE TRIGGER `trg_avis_after_insert`
AFTER INSERT ON `avis`
FOR EACH ROW
BEGIN
  UPDATE `produits`
     SET `note_moyenne` = (SELECT ROUND(AVG(`note`), 2) FROM `avis` WHERE `produit_id` = NEW.`produit_id`),
         `nombre_avis`  = (SELECT COUNT(*) FROM `avis` WHERE `produit_id` = NEW.`produit_id`)
   WHERE `id` = NEW.`produit_id`;
END$$

CREATE TRIGGER `trg_avis_after_update`
AFTER UPDATE ON `avis`
FOR EACH ROW
BEGIN
  UPDATE `produits`
     SET `note_moyenne` = (SELECT ROUND(AVG(`note`), 2) FROM `avis` WHERE `produit_id` = NEW.`produit_id`),
         `nombre_avis`  = (SELECT COUNT(*) FROM `avis` WHERE `produit_id` = NEW.`produit_id`)
   WHERE `id` = NEW.`produit_id`;

  IF NEW.`produit_id` <> OLD.`produit_id` THEN
    UPDATE `produits`
       SET `note_moyenne` = (SELECT COALESCE(ROUND(AVG(`note`), 2), 0.00) FROM `avis` WHERE `produit_id` = OLD.`produit_id`),
           `nombre_avis`  = (SELECT COUNT(*) FROM `avis` WHERE `produit_id` = OLD.`produit_id`)
     WHERE `id` = OLD.`produit_id`;
  END IF;
END$$

CREATE TRIGGER `trg_avis_after_delete`
AFTER DELETE ON `avis`
FOR EACH ROW
BEGIN
  UPDATE `produits`
     SET `note_moyenne` = (SELECT COALESCE(ROUND(AVG(`note`), 2), 0.00) FROM `avis` WHERE `produit_id` = OLD.`produit_id`),
         `nombre_avis`  = (SELECT COUNT(*) FROM `avis` WHERE `produit_id` = OLD.`produit_id`)
   WHERE `id` = OLD.`produit_id`;
END$$

-- -------------------------------------------------------------
-- Trigger : répercute la note moyenne des produits d'une
-- boutique sur la note moyenne de la boutique elle-même.
-- -------------------------------------------------------------

CREATE TRIGGER `trg_produits_after_update_note`
AFTER UPDATE ON `produits`
FOR EACH ROW
BEGIN
  IF NEW.`note_moyenne` <> OLD.`note_moyenne` THEN
    UPDATE `boutiques`
       SET `note_moyenne` = (
             SELECT COALESCE(ROUND(AVG(`note_moyenne`), 2), 0.00)
               FROM `produits`
              WHERE `boutique_id` = NEW.`boutique_id` AND `note_moyenne` > 0
           )
     WHERE `id` = NEW.`boutique_id`;
  END IF;
END$$

-- -------------------------------------------------------------
-- Fonction : vérifie qu'un produit a un stock suffisant avant
-- la création d'une ligne de commande (règle de gestion du MCD :
-- « une commande ne peut être créée que si le stock est suffisant »).
-- -------------------------------------------------------------

CREATE FUNCTION `fn_stock_suffisant`(p_produit_id INT UNSIGNED, p_quantite INT)
RETURNS BOOLEAN
DETERMINISTIC
READS SQL DATA
BEGIN
  DECLARE v_stock INT DEFAULT NULL;

  SELECT `stock` INTO v_stock FROM `produits` WHERE `id` = p_produit_id;

  RETURN v_stock IS NOT NULL AND v_stock >= p_quantite;
END$$

-- -------------------------------------------------------------
-- Procédure stockée : libère les paiements des commandes
-- livrées depuis au moins 14 jours (règle de gestion du MCD :
-- « le paiement n'est libéré au vendeur qu'après 14 jours sans
-- litige »). Destinée à être appelée par l'event ci-dessous.
-- -------------------------------------------------------------

CREATE PROCEDURE `sp_liberer_paiements_echus`()
BEGIN
  UPDATE `paiements` p
    JOIN `commandes` c ON c.`id` = p.`commande_id`
     SET p.`statut`          = 'libere',
         p.`date_liberation` = NOW()
   WHERE p.`statut` = 'valide'
     AND p.`date_liberation` IS NULL
     AND c.`statut` = 'livree'
     AND c.`date_livraison` IS NOT NULL
     AND c.`date_livraison` <= NOW() - INTERVAL 14 DAY;
END$$

DELIMITER ;

-- -------------------------------------------------------------
-- Event : exécute la libération des paiements chaque jour.
-- Nécessite que l'ordonnanceur d'events soit actif :
--   SET GLOBAL event_scheduler = ON;
-- -------------------------------------------------------------

CREATE EVENT IF NOT EXISTS `evt_liberation_paiements_quotidien`
ON SCHEDULE EVERY 1 DAY
STARTS (CURRENT_DATE + INTERVAL 1 DAY + INTERVAL 2 HOUR)
DO CALL `sp_liberer_paiements_echus`();

-- =============================================================
--  Données de test
-- =============================================================

-- Utilisateurs (passwords = "password123" hashé avec bcrypt)
INSERT INTO `utilisateurs` (`nom`, `prenom`, `email`, `password_hash`, `role`) VALUES
  ('Dupont',   'Marie',   'marie.dupont@example.com',   '$2y$12$0PlnyDgFjA7miP3uMXcCkeaemeKbJMk8hjuTsKqyhOFvbNJpOtzDe', 'admin'),
  ('Martin',   'Paul',    'paul.martin@example.com',    '$2y$12$0PlnyDgFjA7miP3uMXcCkeaemeKbJMk8hjuTsKqyhOFvbNJpOtzDe', 'vendeur'),
  ('Bernard',  'Sophie',  'sophie.bernard@example.com', '$2y$12$0PlnyDgFjA7miP3uMXcCkeaemeKbJMk8hjuTsKqyhOFvbNJpOtzDe', 'vendeur'),
  ('Lemoine',  'Jules',   'jules.lemoine@example.com',  '$2y$12$0PlnyDgFjA7miP3uMXcCkeaemeKbJMk8hjuTsKqyhOFvbNJpOtzDe', 'client'),
  ('Petit',    'Camille', 'camille.petit@example.com',  '$2y$12$0PlnyDgFjA7miP3uMXcCkeaemeKbJMk8hjuTsKqyhOFvbNJpOtzDe', 'client');

-- Adresses
INSERT INTO `adresses_livraison` (`utilisateur_id`, `nom_complet`, `ligne1`, `ville`, `code_postal`, `pays`, `est_principale`) VALUES
  (4, 'Jules Lemoine',   '12 rue des Fleurs',    'Lyon',  '69001', 'France', 1),
  (5, 'Camille Petit',   '8 avenue des Artisans', 'Paris', '75011', 'France', 1);

-- Boutiques
INSERT INTO `boutiques` (`vendeur_id`, `nom`, `slug`, `description`) VALUES
  (2, 'L\'Atelier de Paul',  'atelier-de-paul',  'Créations en bois fait main, sculptures et objets décoratifs uniques.'),
  (3, 'Sophie Céramiques',   'sophie-ceramiques', 'Poteries et céramiques artisanales inspirées de la nature.');

-- Catégories (catégories atomiques : un concept par catégorie, sans libellé composé « A & B »)
-- L'ordre d'insertion fixe les id 1..9, référencés par le seed des produits ci-dessous.
INSERT INTO `categories` (`nom`, `slug`, `description`, `ordre`) VALUES
  ('Bois',              'bois',              'Objets et meubles en bois travaillés à la main', 1),
  ('Céramique',         'ceramique',         'Pièces uniques façonnées en argile', 2),
  ('Bijoux',            'bijoux',            'Bijoux artisanaux faits main', 3),
  ('Textile',           'textile',           'Vêtements et décorations textiles', 4),
  ('Décoration Maison', 'decoration-maison', 'Objets décoratifs pour embellir votre intérieur', 5),
  ('Menuiserie',        'menuiserie',        'Ouvrages et agencements en bois', 6),
  ('Poterie',           'poterie',           'Poteries et terres cuites', 7),
  ('Accessoires',       'accessoires',       'Accessoires et petite maroquinerie', 8),
  ('Couture',           'couture',           'Créations cousues, sacs et linge', 9);

-- Produits
INSERT INTO `produits` (`boutique_id`, `categorie_id`, `nom`, `slug`, `description`, `prix`, `stock`, `images`) VALUES
  (1, 1, 'Bol en noyer ciré',        'bol-noyer-cire',        'Bol tournée à la main en noyer massif, finition cire d\'abeille naturelle. Diamètre 20 cm.',          45.00, 12, '["bol-noyer-1.jpg","bol-noyer-2.jpg"]'),
  (1, 1, 'Planche à découper chêne', 'planche-decoupe-chene', 'Planche à découper en chêne massif avec poignée sculptée. 35×25 cm, épaisseur 3 cm.',               65.00,  0, '["planche-1.jpg"]'),
  (1, 5, 'Cadre photo rustique',     'cadre-photo-rustique',  'Cadre photo en bois flotté récupéré, format 15×20 cm. Finition naturelle.',                            28.00, 20, '["cadre-1.jpg","cadre-2.jpg"]'),
  (2, 2, 'Mug grès bleu océan',      'mug-gres-bleu-ocean',   'Mug en grès émaillé à la main, nuances de bleu. Contenance 350 ml. Passe au lave-vaisselle.',          38.00, 15, '["mug-bleu-1.jpg"]'),
  (2, 2, 'Vase effilé terracotta',   'vase-effile-terracotta','Vase effilé en terracotta non émaillée, hauteur 30 cm. Idéal pour fleurs séchées.',                    55.00,  6, '["vase-terra-1.jpg","vase-terra-2.jpg"]'),
  (2, 5, 'Assiette creuse fleurie',  'assiette-creuse-fleurie','Assiette creuse peinte à la main avec motifs floraux. Diamètre 22 cm. Faite main unique.',            42.00, 10, '["assiette-1.jpg"]');

-- Commandes
INSERT INTO `commandes` (`utilisateur_id`, `adresse_livraison_id`, `statut`, `montant_total`, `frais_livraison`, `date_livraison`) VALUES
  (4, 1, 'livree',      103.00, 5.90, DATE_SUB(NOW(), INTERVAL 20 DAY)),
  (5, 2, 'en_preparation', 80.00, 5.90, NULL);

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

-- =============================================================
--  Tests manuels des composants SGBD (CP8) — à exécuter à la main
-- =============================================================
--
-- 1. Les triggers ont déjà recalculé note_moyenne/nombre_avis des
--    produits 1, 3 et 4 au moment de l'INSERT dans `avis` ci-dessus,
--    et par cascade note_moyenne des boutiques 1 et 2. Vérifier :
--      SELECT id, nom, note_moyenne, nombre_avis FROM produits;
--      SELECT id, nom, note_moyenne FROM boutiques;
--
-- 2. Fonction fn_stock_suffisant : le produit 1 a 12 en stock.
--      SELECT fn_stock_suffisant(1, 5);   -- renvoie 1 (TRUE)
--      SELECT fn_stock_suffisant(1, 50);  -- renvoie 0 (FALSE)
--
-- 3. Procédure sp_liberer_paiements_echus : la commande 1 a été
--    livrée il y a 20 jours (> 14), son paiement doit passer à 'libere'.
--      CALL sp_liberer_paiements_echus();
--      SELECT id, commande_id, statut, date_liberation FROM paiements;
--
-- 4. Activer l'event si besoin (désactivé par défaut sur la plupart
--    des installations MySQL) :
--      SET GLOBAL event_scheduler = ON;
