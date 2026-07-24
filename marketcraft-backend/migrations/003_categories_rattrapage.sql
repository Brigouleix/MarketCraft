-- =============================================================================
-- Migration 003 — Rattrapage de la migration 002 et accentuation
-- =============================================================================
--
-- Pourquoi ce script
--   Dans la 002, la ligne « Objet » n'a pas ete inseree : sa description
--   contenait une apostrophe echappee ('Type d''objet artisanal') qui a fait
--   echouer l'instruction. L'UPDATE suivant lisait l'identifiant de « Objet »
--   via une sous-requete : celle-ci ne renvoyant rien, il a affecte NULL aux
--   categories concernees au lieu d'echouer, les laissant toutes a la racine.
--
--   Ce script cree « Objet », rattache toutes les categories orphelines, et
--   remet les accents sur les libelles (les slugs restent sans accent).
--
-- Sur : aucune apostrophe dans les chaines, aucun UPDATE dependant d'une
--       ligne qui pourrait ne pas exister, et rejouable sans effet de bord.
--
-- Execution : phpMyAdmin > base « marketcraft » > onglet Importer
-- =============================================================================

-- -----------------------------------------------------------------------------
-- 1. Creer « Objet » s'il manque
-- -----------------------------------------------------------------------------

INSERT INTO `categories` (`parent_id`, `nom`, `slug`, `description`, `ordre`)
SELECT NULL, 'Objet', 'objet', 'Nature de la piece artisanale', 0
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `categories` WHERE `slug` = 'objet');

-- Filet de securite : si « Materiau » manquait aussi, on le cree.
INSERT INTO `categories` (`parent_id`, `nom`, `slug`, `description`, `ordre`)
SELECT NULL, 'Materiau', 'materiau', 'Matiere principale de fabrication', 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `categories` WHERE `slug` = 'materiau');

-- -----------------------------------------------------------------------------
-- 2. Rattacher a « Objet » toutes les categories restees a la racine
--
--    Vise aussi les categories ajoutees a la main depuis l'administration
--    (Armoire, Couvert, Meuble, Table…), qui sont bien des types d'objet.
--    Les deux racines elles-memes et les enfants de « Materiau » sont exclus.
-- -----------------------------------------------------------------------------

UPDATE `categories`
SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'objet') AS t)
WHERE `parent_id` IS NULL
  AND `slug` NOT IN ('objet', 'materiau');

-- -----------------------------------------------------------------------------
-- 3. Accentuation des libelles (les slugs ne changent pas)
-- -----------------------------------------------------------------------------

UPDATE `categories` SET `nom` = 'Matériau' WHERE `slug` = 'materiau';
UPDATE `categories` SET `nom` = 'Métal'    WHERE `slug` = 'metal';
UPDATE `categories` SET `nom` = 'Béton'    WHERE `slug` = 'beton';
UPDATE `categories` SET `nom` = 'Résine'   WHERE `slug` = 'resine';

UPDATE `categories`
SET `description` = 'Matière principale de fabrication'
WHERE `slug` = 'materiau';

-- -----------------------------------------------------------------------------
-- 4. Verification — a executer apres l import
--
--    Resultat attendu : deux racines, « Objet » avec toutes les categories
--    de type et « Matériau » avec les dix matieres. Aucune ligne « racine »
--    en dehors des deux racines.
-- -----------------------------------------------------------------------------

SELECT
  COALESCE(p.`nom`, '— racine —') AS `groupe`,
  c.`nom`,
  c.`slug`
FROM `categories` c
LEFT JOIN `categories` p ON p.`id` = c.`parent_id`
ORDER BY COALESCE(p.`ordre`, c.`ordre`), c.`parent_id` IS NOT NULL, c.`ordre`, c.`nom`;
