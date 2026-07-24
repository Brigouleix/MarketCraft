-- =============================================================================
-- Migration 002 — Hierarchie des categories : « Objet » et « Materiau »
-- =============================================================================
--
-- Contexte
--   Les categories etaient jusqu'ici a plat. Cette migration introduit deux
--   categories racines et rattache les categories existantes a l'une d'elles
--   via la colonne `parent_id` (contrainte auto-referente deja presente au
--   schema initial).
--
--   « Bois » etait une categorie d'objet ; elle devient un materiau. Aucun
--   produit n'est modifie : les rattachements produit <-> categorie sont
--   conserves tels quels.
--
-- Idempotence
--   Le script peut etre rejoue sans effet de bord (INSERT ... SELECT WHERE
--   NOT EXISTS, puis UPDATE cibles).
--
-- Execution
--   mysql -u root marketcraft < migrations/002_categories_hierarchie.sql
--   ou via l'onglet SQL de phpMyAdmin.
-- =============================================================================

-- -----------------------------------------------------------------------------
-- 1. Les deux categories racines
-- -----------------------------------------------------------------------------

INSERT INTO `categories` (`parent_id`, `nom`, `slug`, `description`, `ordre`)
SELECT NULL, 'Objet', 'objet', 'Nature de la piece artisanale', 0
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `categories` WHERE `slug` = 'objet');

INSERT INTO `categories` (`parent_id`, `nom`, `slug`, `description`, `ordre`)
SELECT NULL, 'Materiau', 'materiau', 'Matiere principale de fabrication', 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `categories` WHERE `slug` = 'materiau');

-- -----------------------------------------------------------------------------
-- 2. Rattachement des categories existantes a « Objet »
--    (toutes sauf « Bois », qui devient un materiau)
-- -----------------------------------------------------------------------------

-- La clause EXISTS evite d'ecrire NULL si la racine n'a pas ete creee :
-- sans elle, une sous-requete vide affecterait silencieusement NULL.
UPDATE `categories`
SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'objet') AS t)
WHERE `slug` IN (
  'ceramique', 'bijoux', 'textile', 'decoration-maison',
  'menuiserie', 'poterie', 'accessoires', 'couture'
)
AND EXISTS (SELECT 1 FROM (SELECT `id` FROM `categories` WHERE `slug` = 'objet') AS g);

-- -----------------------------------------------------------------------------
-- 3. « Bois » passe sous « Materiau »
-- -----------------------------------------------------------------------------

UPDATE `categories`
SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'materiau') AS t)
WHERE `slug` = 'bois'
AND EXISTS (SELECT 1 FROM (SELECT `id` FROM `categories` WHERE `slug` = 'materiau') AS g);

-- -----------------------------------------------------------------------------
-- 4. Les neuf materiaux supplementaires
-- -----------------------------------------------------------------------------

INSERT INTO `categories` (`parent_id`, `nom`, `slug`, `description`, `ordre`)
SELECT m.`id`, v.`nom`, v.`slug`, v.`description`, v.`ordre`
FROM (SELECT `id` FROM `categories` WHERE `slug` = 'materiau') AS m
CROSS JOIN (
  SELECT 'Metal'  AS `nom`, 'metal'  AS `slug`, 'Fer, acier, laiton, cuivre'                AS `description`,  2 AS `ordre`
  UNION ALL SELECT 'Or',     'or',     'Or massif ou plaque',                                3
  UNION ALL SELECT 'Argent', 'argent', 'Argent massif et argent 925',                        4
  UNION ALL SELECT 'Argile', 'argile', 'Terre cuite, gres, porcelaine',                      5
  UNION ALL SELECT 'Beton',  'beton',  'Beton cire, beton mineral',                          6
  UNION ALL SELECT 'Verre',  'verre',  'Verre souffle, vitrail, verre fondu',                7
  UNION ALL SELECT 'Cuir',   'cuir',   'Cuir tanne et travaille a la main',                  8
  UNION ALL SELECT 'Pierre', 'pierre', 'Marbre, ardoise, pierre naturelle',                  9
  UNION ALL SELECT 'Resine', 'resine', 'Resine epoxy et resines de coulee',                 10
) AS v
WHERE NOT EXISTS (SELECT 1 FROM `categories` c WHERE c.`slug` = v.`slug`);

-- -----------------------------------------------------------------------------
-- 5. Ordre d'affichage des categories d'objet
-- -----------------------------------------------------------------------------

UPDATE `categories` SET `ordre` = 1 WHERE `slug` = 'ceramique';
UPDATE `categories` SET `ordre` = 2 WHERE `slug` = 'bijoux';
UPDATE `categories` SET `ordre` = 3 WHERE `slug` = 'textile';
UPDATE `categories` SET `ordre` = 4 WHERE `slug` = 'decoration-maison';
UPDATE `categories` SET `ordre` = 5 WHERE `slug` = 'menuiserie';
UPDATE `categories` SET `ordre` = 6 WHERE `slug` = 'poterie';
UPDATE `categories` SET `ordre` = 7 WHERE `slug` = 'accessoires';
UPDATE `categories` SET `ordre` = 8 WHERE `slug` = 'couture';
UPDATE `categories` SET `ordre` = 1 WHERE `slug` = 'bois';

-- -----------------------------------------------------------------------------
-- 6. Verification
-- -----------------------------------------------------------------------------
-- SELECT p.nom AS racine, c.nom, c.slug, c.ordre
-- FROM categories c
-- LEFT JOIN categories p ON p.id = c.parent_id
-- ORDER BY COALESCE(p.ordre, c.ordre), c.ordre, c.nom;
