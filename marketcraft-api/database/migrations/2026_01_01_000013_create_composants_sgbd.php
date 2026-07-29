<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Composants dans le langage du SGBD — CP8 du referentiel CDA.
 *
 * Triggers, fonction, procedure stockee et event. Toute cette logique vit
 * dans MySQL, independamment du code applicatif.
 *
 * Choix assume : `produits.note_moyenne` et `produits.nombre_avis` sont
 * maintenus ICI et nulle part ailleurs. L'application les lit, elle ne les
 * recalcule pas — deux sources de verite pour la meme valeur finissent
 * toujours par diverger.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Ces objets sont propres a MySQL/MariaDB. La suite de tests tourne
        // sous SQLite, ou ils n'ont pas d'equivalent.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $this->drop();

        // --- Recalcul de la note d'un produit a chaque ecriture d'avis ---

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `trg_avis_after_insert`
            AFTER INSERT ON `avis`
            FOR EACH ROW
            BEGIN
              UPDATE `produits`
                 SET `note_moyenne` = (SELECT ROUND(AVG(`note`), 2) FROM `avis` WHERE `produit_id` = NEW.`produit_id`),
                     `nombre_avis`  = (SELECT COUNT(*) FROM `avis` WHERE `produit_id` = NEW.`produit_id`)
               WHERE `id` = NEW.`produit_id`;
            END
        SQL);

        DB::unprepared(<<<'SQL'
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
            END
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER `trg_avis_after_delete`
            AFTER DELETE ON `avis`
            FOR EACH ROW
            BEGIN
              UPDATE `produits`
                 SET `note_moyenne` = (SELECT COALESCE(ROUND(AVG(`note`), 2), 0.00) FROM `avis` WHERE `produit_id` = OLD.`produit_id`),
                     `nombre_avis`  = (SELECT COUNT(*) FROM `avis` WHERE `produit_id` = OLD.`produit_id`)
               WHERE `id` = OLD.`produit_id`;
            END
        SQL);

        // --- Repercussion sur la note moyenne de la boutique ---
        // Les produits sans avis (note a 0) sont exclus de la moyenne :
        // sinon un catalogue neuf tirerait la note de la boutique vers zero.

        DB::unprepared(<<<'SQL'
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
            END
        SQL);

        // --- Fonction : controle de stock ---

        DB::unprepared(<<<'SQL'
            CREATE FUNCTION `fn_stock_suffisant`(p_produit_id INT UNSIGNED, p_quantite INT)
            RETURNS BOOLEAN
            DETERMINISTIC
            READS SQL DATA
            BEGIN
              DECLARE v_stock INT DEFAULT NULL;
              SELECT `stock` INTO v_stock FROM `produits` WHERE `id` = p_produit_id;
              RETURN v_stock IS NOT NULL AND v_stock >= p_quantite;
            END
        SQL);

        // --- Procedure : liberation des paiements a J+14 ---
        // Regle de gestion : le paiement n'est verse au vendeur qu'apres
        // 14 jours sans litige.

        DB::unprepared(<<<'SQL'
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
            END
        SQL);

        // --- Event quotidien ---
        // Necessite un ordonnanceur actif : SET GLOBAL event_scheduler = ON;
        // (desactive par defaut sur la plupart des installations).

        DB::unprepared(<<<'SQL'
            CREATE EVENT IF NOT EXISTS `evt_liberation_paiements_quotidien`
            ON SCHEDULE EVERY 1 DAY
            STARTS (CURRENT_DATE + INTERVAL 1 DAY + INTERVAL 2 HOUR)
            DO CALL `sp_liberer_paiements_echus`()
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $this->drop();
    }

    /**
     * Suppression prealable : permet de rejouer la migration sur une base
     * existante sans buter sur l'erreur #1359 (trigger deja present).
     */
    private function drop(): void
    {
        foreach ([
            'trg_avis_after_insert',
            'trg_avis_after_update',
            'trg_avis_after_delete',
            'trg_produits_after_update_note',
        ] as $trigger) {
            DB::unprepared("DROP TRIGGER IF EXISTS `{$trigger}`");
        }

        DB::unprepared('DROP FUNCTION IF EXISTS `fn_stock_suffisant`');
        DB::unprepared('DROP PROCEDURE IF EXISTS `sp_liberer_paiements_echus`');
        DB::unprepared('DROP EVENT IF EXISTS `evt_liberation_paiements_quotidien`');
    }
};
