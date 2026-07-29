-- =============================================================================
-- Migration 004 — Catalogue de demonstration enrichi
-- =============================================================================
--
-- Pourquoi ce script
--   Le catalogue d'origine compte six produits repartis sur deux boutiques.
--   C'est trop peu pour que les fonctionnalites de recommandation et
--   d'analyse concurrentielle aient matiere a travailler : sans plusieurs
--   articles comparables dans une meme categorie et une meme gamme de prix,
--   toute suggestion se reduit a « voici les autres produits du site ».
--
--   Ce script ajoute deux artisans, leurs boutiques et seize produits. Les
--   gammes de prix se chevauchent volontairement — plusieurs tables en bois
--   entre 149 et 320 EUR, plusieurs luminaires entre 42 et 95 EUR — pour que
--   le positionnement tarifaire d'un vendeur soit reellement calculable.
--
-- Idempotence
--   Chaque insertion est gardee par un WHERE NOT EXISTS sur une cle
--   naturelle (email, slug). Le script peut etre rejoue sans doublon et
--   sans ecraser de donnee existante.
--
-- Prerequis
--   Les migrations 001 a 003 et la hierarchie de categories « Objet » /
--   « Materiau ». Les categories sont referencees par leur SLUG, jamais par
--   un identifiant en dur : les id different d'une base a l'autre.
--
-- Execution
--   phpMyAdmin > base marketcraft_4eme_dev > onglet Importer
--   ou :  mysql -u root marketcraft_4eme_dev < database/sql/004_catalogue_demo.sql
-- =============================================================================

SET NAMES utf8mb4;

-- -----------------------------------------------------------------------------
-- 1. Deux artisans supplementaires
--
--    Mot de passe : Password123
--    Condensat bcrypt cout 12, identique a celui produit par l'application.
--    Il ne s'agit que de comptes de demonstration ; aucun compte reel ne
--    doit partager ce mot de passe.
-- -----------------------------------------------------------------------------

-- INSERT IGNORE s'appuie sur la cle unique uq_utilisateurs_email : rejouer
-- le script n'ecrase rien et ne cree pas de doublon. On evite ainsi un
-- WHERE NOT EXISTS qui interrogerait la table en cours d'insertion, ce que
-- MySQL refuse selon les versions.
INSERT IGNORE INTO `utilisateurs` (`nom`, `prenom`, `email`, `password_hash`, `role`, `est_actif`)
VALUES
  ('Roussel', 'Hugo', 'hugo.roussel@example.com',
   '$2y$12$eAVaWBRnbkEqyiU8KJ6XkOVG/PnFF.EtiXNL0P4y0l5n.9aCWHoq2', 'vendeur', 1),
  ('Nguyen',  'Lea',  'lea.nguyen@example.com',
   '$2y$12$eAVaWBRnbkEqyiU8KJ6XkOVG/PnFF.EtiXNL0P4y0l5n.9aCWHoq2', 'vendeur', 1);

-- -----------------------------------------------------------------------------
-- 2. Leurs boutiques
--    Rappel : la cle unique uq_boutiques_vendeur interdit plus d'une
--    boutique par vendeur.
-- -----------------------------------------------------------------------------

INSERT IGNORE INTO `boutiques` (`vendeur_id`, `nom`, `slug`, `description`, `est_active`)
SELECT u.`id`, 'Fonderie du Vieux Port', 'fonderie-du-vieux-port',
       'Luminaires et objets en metal coule a la main, patines a l ancienne.', 1
FROM `utilisateurs` u
WHERE u.`email` = 'hugo.roussel@example.com';

INSERT IGNORE INTO `boutiques` (`vendeur_id`, `nom`, `slug`, `description`, `est_active`)
SELECT u.`id`, 'Cuir et Trame', 'cuir-et-trame',
       'Maroquinerie et textiles tisses main, teintures vegetales.', 1
FROM `utilisateurs` u
WHERE u.`email` = 'lea.nguyen@example.com';

-- -----------------------------------------------------------------------------
-- 3. Les seize produits
--
--    `categorie_id` porte la categorie principale (un type d'objet) ; la
--    table de liaison portera en plus la matiere. C'est ce couple qui rend
--    le croisement des filtres et le calcul de similarite pertinents.
--
--    Les gammes se chevauchent deliberement :
--      tables en bois      149 / 189 / 265 / 320 EUR
--      luminaires          42 / 58 / 79 / 95 EUR
--      poterie et ceramique 24 / 36 / 48 / 62 EUR
--    Un vendeur peut ainsi se situer par rapport a une vraie distribution.
-- -----------------------------------------------------------------------------

-- Table temporaire : evite de repeter seize fois la meme sous-requete.
DROP TEMPORARY TABLE IF EXISTS `tmp_catalogue`;

CREATE TEMPORARY TABLE `tmp_catalogue` (
  `slug`         VARCHAR(220) NOT NULL,
  `boutique`     VARCHAR(160) NOT NULL,
  `nom`          VARCHAR(200) NOT NULL,
  `description`  TEXT,
  `prix`         DECIMAL(10,2) NOT NULL,
  `stock`        INT NOT NULL,
  `cat_objet`    VARCHAR(110) NOT NULL,
  `cat_matiere`  VARCHAR(110) NOT NULL,
  PRIMARY KEY (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tmp_catalogue` VALUES
-- ---- Tables et meubles en bois : gamme 149 -> 320 EUR ----------------------
('table-basse-chene-brut',    'atelier-de-paul',        'Table basse chêne brut',
 'Plateau de chêne massif non traité sur pieds épingle en acier noir. 100x50 cm.',
 149.00, 4, 'table', 'bois'),
('table-repas-frene-huile',   'atelier-de-paul',        'Table de repas frêne huilé',
 'Table six couverts en frêne massif, finition huile-cire. Assemblage tourillonné, sans vis apparente.',
 320.00, 2, 'table', 'bois'),
('table-appoint-noyer',       'fonderie-du-vieux-port', 'Table d appoint noyer et acier',
 'Petit plateau de noyer sur piètement en acier soudé, patine graphite. Hauteur 55 cm.',
 189.00, 6, 'table', 'bois'),
('etagere-murale-chene',      'atelier-de-paul',        'Étagère murale chêne',
 'Tablette de chêne massif 80 cm avec équerres forgées. Charge admissible 25 kg.',
 78.00, 9, 'meuble', 'bois'),
('coffre-rangement-pin',      'atelier-de-paul',        'Coffre de rangement pin',
 'Coffre à couvercle sur charnières laiton, pin massif brossé. 60x35x35 cm.',
 265.00, 3, 'meuble', 'bois'),

-- ---- Luminaires : gamme 42 -> 95 EUR --------------------------------------
('lampe-baladeuse-laiton',    'fonderie-du-vieux-port', 'Lampe baladeuse laiton',
 'Baladeuse en laiton poli, câble textile tressé de 3 m, douille E27. Ampoule non fournie.',
 58.00, 12, 'decoration-maison', 'metal'),
('lampe-poser-gres-emaille',  'sophie-ceramiques',      'Lampe à poser grès émaillé',
 'Pied en grès émaillé bleu nuit, abat-jour lin écru. Hauteur totale 42 cm.',
 95.00, 5, 'decoration-maison', 'ceramique'),
('applique-fer-forge',        'fonderie-du-vieux-port', 'Applique fer forgé',
 'Applique murale en fer forgé à la main, finition cire noire. Orientable sur 90 degrés.',
 42.00, 15, 'decoration-maison', 'metal'),
('suspension-beton-cire',     'fonderie-du-vieux-port', 'Suspension béton ciré',
 'Abat-jour coulé en béton ciré gris clair, intérieur peint cuivre. Diamètre 22 cm.',
 79.00, 7, 'decoration-maison', 'beton'),

-- ---- Poterie et ceramique : gamme 24 -> 62 EUR -----------------------------
('bol-a-soupe-gres-sable',    'sophie-ceramiques',      'Bol à soupe grès sablé',
 'Bol tourné en grès chamotté, émail sablé mat. Contenance 500 ml, passe au four.',
 24.00, 20, 'poterie', 'ceramique'),
('pichet-terre-vernissee',    'sophie-ceramiques',      'Pichet terre vernissée',
 'Pichet d un litre en terre rouge vernissée, anse pincée à la main.',
 48.00, 8, 'poterie', 'argile'),
('service-cafe-porcelaine',   'sophie-ceramiques',      'Service à café porcelaine',
 'Quatre tasses et sous-tasses en porcelaine fine, filet doré posé au pinceau.',
 62.00, 4, 'couvert', 'ceramique'),
('coupelle-argile-crue',      'sophie-ceramiques',      'Coupelle argile crue',
 'Coupelle vide-poche en argile crue polie, non émaillée. Diamètre 12 cm.',
 36.00, 14, 'poterie', 'argile'),

-- ---- Cuir et textile ------------------------------------------------------
('sac-besace-cuir-tanne',     'cuir-et-trame',          'Sac besace cuir tanné',
 'Besace en cuir de vachette à tannage végétal, couture sellier au fil de lin ciré.',
 185.00, 3, 'accessoires', 'cuir'),
('plaid-laine-tissee',        'cuir-et-trame',          'Plaid laine tissée main',
 'Plaid 130x180 cm tissé sur métier manuel, laine des Pyrénées non teintée.',
 129.00, 5, 'textile', 'textile'),
('trousse-cuir-graine',       'cuir-et-trame',          'Trousse cuir grainé',
 'Petite trousse doublée coton, cuir grainé teinté à la main. Zip laiton.',
 54.00, 11, 'accessoires', 'cuir');

-- Insertion des produits absents
INSERT IGNORE INTO `produits` (`boutique_id`, `categorie_id`, `nom`, `slug`, `description`, `prix`, `stock`, `est_actif`, `est_fait_main`)
SELECT b.`id`, c.`id`, t.`nom`, t.`slug`, t.`description`, t.`prix`, t.`stock`, 1, 1
FROM `tmp_catalogue` t
JOIN `boutiques`  b ON b.`slug` = t.`boutique`
JOIN `categories` c ON c.`slug` = t.`cat_objet`;

-- -----------------------------------------------------------------------------
-- 4. Liaisons produit <-> categories
--    Une categorie d'objet et une de matiere par produit. Les filtres du
--    catalogue interrogent UNIQUEMENT cette table : un produit qui n'y
--    figure pas reste introuvable par categorie, tout en s'affichant
--    normalement sur sa fiche.
-- -----------------------------------------------------------------------------

INSERT IGNORE INTO `produit_categorie` (`produit_id`, `categorie_id`)
SELECT p.`id`, c.`id`
FROM `tmp_catalogue` t
JOIN `produits`   p ON p.`slug` = t.`slug`
JOIN `categories` c ON c.`slug` = t.`cat_objet`;

INSERT IGNORE INTO `produit_categorie` (`produit_id`, `categorie_id`)
SELECT p.`id`, c.`id`
FROM `tmp_catalogue` t
JOIN `produits`   p ON p.`slug` = t.`slug`
JOIN `categories` c ON c.`slug` = t.`cat_matiere`;

-- Filet de securite : rattrape toute categorie principale absente de la
-- table de liaison, y compris pour les produits anterieurs a ce script.
INSERT IGNORE INTO `produit_categorie` (`produit_id`, `categorie_id`)
SELECT p.`id`, p.`categorie_id`
FROM `produits` p
WHERE p.`categorie_id` IS NOT NULL;

DROP TEMPORARY TABLE IF EXISTS `tmp_catalogue`;

-- -----------------------------------------------------------------------------
-- 5. Quelques avis, pour que les notes ne soient pas toutes a zero
--
--    La contrainte uq_avis_produit_user impose un avis par couple
--    (produit, utilisateur) : on repartit donc les auteurs.
--
--    Les triggers recalculent note_moyenne et nombre_avis a l'insertion ;
--    ces colonnes ne sont jamais renseignees a la main.
--
--    ATTENTION — erreur MySQL #1442
--    Le trigger trg_avis_after_insert met a jour `produits`. MySQL refuse
--    qu'un trigger modifie une table que la requete appelante utilise
--    deja : un INSERT INTO avis ... SELECT ... JOIN produits echoue donc,
--    meme si la jointure ne sert qu'a resoudre un identifiant.
--
--    On resout donc les identifiants dans une table temporaire AVANT
--    d'inserer. L'insertion finale ne lit plus que cette temporaire, et le
--    trigger retrouve sa liberte de mettre `produits` a jour.
-- -----------------------------------------------------------------------------

DROP TEMPORARY TABLE IF EXISTS `tmp_avis`;

CREATE TEMPORARY TABLE `tmp_avis` (
  `produit_id`     INT UNSIGNED NOT NULL,
  `utilisateur_id` INT UNSIGNED NOT NULL,
  `note`           TINYINT      NOT NULL,
  `titre`          VARCHAR(150) DEFAULT NULL,
  `commentaire`    TEXT         DEFAULT NULL,
  PRIMARY KEY (`produit_id`, `utilisateur_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Etape 1 : resolution des identifiants. Aucun trigger n'est implique ici,
-- la table cible etant temporaire.
INSERT IGNORE INTO `tmp_avis` (`produit_id`, `utilisateur_id`, `note`, `titre`, `commentaire`)
SELECT p.`id`, u.`id`, v.`note`, v.`titre`, v.`commentaire`
FROM (
            SELECT 'table-basse-chene-brut'   AS `slug`, 'jules.lemoine@example.com'   AS `email`, 5 AS `note`, 'Le bois est superbe'      AS `titre`, 'Veinage magnifique, finition impeccable. Livrée bien emballée.' AS `commentaire`
  UNION ALL SELECT 'lampe-baladeuse-laiton',        'jules.lemoine@example.com',   4, 'Belle lumière',           'Le laiton patine joliment. Câble un peu court à mon goût.'
  UNION ALL SELECT 'bol-a-soupe-gres-sable',        'jules.lemoine@example.com',   5, 'Parfait au quotidien',    'Taille idéale, agréable en main. J en ai repris deux.'
  UNION ALL SELECT 'table-repas-frene-huile',       'camille.petit@example.com',   5, 'Une pièce de menuisier',  'Assemblage irréprochable. On sent le travail à la main.'
  UNION ALL SELECT 'sac-besace-cuir-tanne',         'camille.petit@example.com',   4, 'Cuir de belle qualité',   'Odeur de cuir véritable, coutures nettes. Se patine déjà bien.'
  UNION ALL SELECT 'pichet-terre-vernissee',        'camille.petit@example.com',   3, 'Joli mais fragile',       'Très bel objet, mais l anse me paraît délicate. À manipuler avec soin.'
  UNION ALL SELECT 'suspension-beton-cire',         'camille.petit@example.com',   4, 'Effet réussi',            'Le contraste béton et cuivre fonctionne très bien au-dessus d une table.'
) AS v
JOIN `produits`     p ON p.`slug`  = v.`slug`
JOIN `utilisateurs` u ON u.`email` = v.`email`;

-- Etape 2 : insertion reelle. La requete ne lit que `tmp_avis`, donc le
-- trigger peut recalculer `produits.note_moyenne` sans conflit.
INSERT IGNORE INTO `avis` (`produit_id`, `utilisateur_id`, `note`, `titre`, `commentaire`, `est_verifie`)
SELECT `produit_id`, `utilisateur_id`, `note`, `titre`, `commentaire`, 1
FROM `tmp_avis`;

DROP TEMPORARY TABLE IF EXISTS `tmp_avis`;

-- =============================================================================
-- 6. Verification — a executer a la main apres l import
-- =============================================================================
--
-- Nombre de produits actifs par boutique :
--   SELECT b.nom, COUNT(p.id) AS nb
--   FROM boutiques b LEFT JOIN produits p ON p.boutique_id = b.id AND p.est_actif = 1
--   GROUP BY b.id ORDER BY nb DESC;
--
-- Aucun produit ne doit etre orphelin de liaison :
--   SELECT p.id, p.nom FROM produits p
--   LEFT JOIN produit_categorie pc ON pc.produit_id = p.id
--   WHERE pc.produit_id IS NULL;
--
-- Distribution des prix par categorie d objet, base de l analyse
-- concurrentielle :
--   SELECT c.nom, COUNT(*) AS nb, MIN(p.prix), ROUND(AVG(p.prix), 2), MAX(p.prix)
--   FROM produits p
--   JOIN produit_categorie pc ON pc.produit_id = p.id
--   JOIN categories c ON c.id = pc.categorie_id AND c.parent_id = (SELECT id FROM categories WHERE slug = 'objet')
--   WHERE p.est_actif = 1
--   GROUP BY c.id HAVING nb > 1 ORDER BY nb DESC;
--
-- Les triggers ont recalcule les notes :
--   SELECT nom, note_moyenne, nombre_avis FROM produits WHERE nombre_avis > 0;
