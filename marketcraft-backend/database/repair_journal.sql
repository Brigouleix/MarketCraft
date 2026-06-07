-- ============================================================
-- MarketCraft — Réparation de la table journal_activites
-- ============================================================
-- À exécuter UNE SEULE FOIS dans phpMyAdmin ou le client MySQL
-- si le journal d'activités reste vide malgré les connexions.
--
-- Cause : un fichier .ibd orphelin (journal_activites.ibd) existe
-- dans le répertoire de données MySQL/MariaDB à la suite d'un
-- arrêt brutal lors d'une précédente création de table.
-- Ce script le supprime proprement et recrée la table.
-- ============================================================

SET foreign_key_checks = 0;

-- 1. Supprimer la table (et le fichier .ibd associé)
DROP TABLE IF EXISTS journal_activites;

-- 2. Créer proprement
CREATE TABLE journal_activites (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type       VARCHAR(50)  NOT NULL,
    message    VARCHAR(500) NOT NULL,
    user_id    INT UNSIGNED NULL,
    ip_address VARCHAR(45)  NULL,
    context    JSON         NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_type       (type),
    INDEX idx_user_id    (user_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET foreign_key_checks = 1;

-- Vérification
SELECT 'Table journal_activites créée avec succès.' AS statut;
