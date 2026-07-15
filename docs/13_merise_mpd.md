# Modèle Physique de Données (MPD) - Merise

Description : Le MPD traduit le [MLD](09_merise_mld.md) en un schéma directement implémentable sur le SGBDR cible (MySQL 8.0+). Il fige tous les choix techniques que le MLD laisse encore ouverts : types de colonnes exacts et leur précision, moteur de stockage, encodage et collation, index de performance, contraintes d'intégrité (`CHECK`), et actions référentielles précises (`ON DELETE` / `ON UPDATE`). Ce document constitue le script SQL exécutable de référence pour créer la base `marketcraft`.

## Script SQL DDL - Création des tables

```sql
-- ============================================================
-- MarketCraft — Schéma de base de données MySQL 8.0+
-- Encodage : UTF8MB4 | Moteur : InnoDB | Collation : utf8mb4_unicode_ci
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO';

-- ─────────────────────────────────────────────────────────────
-- TABLE : utilisateurs
-- ─────────────────────────────────────────────────────────────
CREATE TABLE utilisateurs (
    id                      INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    nom                     VARCHAR(100)        NOT NULL,
    prenom                  VARCHAR(100)        NOT NULL,
    email                   VARCHAR(255)        NOT NULL,
    mot_de_passe_hash       VARCHAR(255)        NOT NULL,
    telephone               VARCHAR(20)                  DEFAULT NULL,
    role                    ENUM('ACHETEUR','VENDEUR','ADMIN','SUPER_ADMIN')
                                                NOT NULL DEFAULT 'ACHETEUR',
    est_actif               TINYINT(1)          NOT NULL DEFAULT 0,
    token_verification      VARCHAR(255)                 DEFAULT NULL,
    token_reset             VARCHAR(255)                 DEFAULT NULL,
    token_reset_expiration  DATETIME                     DEFAULT NULL,
    tentatives_echec        TINYINT UNSIGNED    NOT NULL DEFAULT 0,
    verrouille_jusqu_a      DATETIME                     DEFAULT NULL,
    date_inscription        DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    derniere_connexion      DATETIME                     DEFAULT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uk_utilisateurs_email (email),
    INDEX idx_utilisateurs_role (role),
    INDEX idx_utilisateurs_est_actif (est_actif),
    INDEX idx_utilisateurs_token_verification (token_verification),
    INDEX idx_utilisateurs_token_reset (token_reset)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- TABLE : refresh_tokens
-- ─────────────────────────────────────────────────────────────
CREATE TABLE refresh_tokens (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    utilisateur_id  INT UNSIGNED    NOT NULL,
    token           VARCHAR(500)    NOT NULL,
    expire_at       DATETIME        NOT NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip_adresse      VARCHAR(45)              DEFAULT NULL,
    user_agent      VARCHAR(255)             DEFAULT NULL,
    est_revoque     TINYINT(1)      NOT NULL DEFAULT 0,

    PRIMARY KEY (id),
    UNIQUE KEY uk_refresh_tokens_token (token),
    INDEX idx_refresh_tokens_utilisateur (utilisateur_id),
    INDEX idx_refresh_tokens_expire_at (expire_at),
    CONSTRAINT fk_refresh_tokens_utilisateur
        FOREIGN KEY (utilisateur_id)
        REFERENCES utilisateurs(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- TABLE : boutiques
-- ─────────────────────────────────────────────────────────────
CREATE TABLE boutiques (
    id              INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    utilisateur_id  INT UNSIGNED        NOT NULL,
    nom             VARCHAR(150)        NOT NULL,
    slug            VARCHAR(150)        NOT NULL,
    description     TEXT                         DEFAULT NULL,
    logo            VARCHAR(500)                 DEFAULT NULL,
    banniere        VARCHAR(500)                 DEFAULT NULL,
    statut          ENUM('EN_ATTENTE','ACTIVE','SUSPENDUE','FERMEE')
                                        NOT NULL DEFAULT 'EN_ATTENTE',
    note_moyenne    DECIMAL(3,2)        NOT NULL DEFAULT 0.00,
    nombre_ventes   INT UNSIGNED        NOT NULL DEFAULT 0,
    siret           VARCHAR(14)                  DEFAULT NULL,
    adresse         VARCHAR(255)                 DEFAULT NULL,
    ville           VARCHAR(100)                 DEFAULT NULL,
    code_postal     VARCHAR(10)                  DEFAULT NULL,
    pays            CHAR(3)             NOT NULL DEFAULT 'FRA',
    date_creation   DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_mise_a_jour DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP
                                        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uk_boutiques_slug (slug),
    UNIQUE KEY uk_boutiques_utilisateur (utilisateur_id),
    UNIQUE KEY uk_boutiques_siret (siret),
    INDEX idx_boutiques_statut (statut),
    INDEX idx_boutiques_note (note_moyenne),
    CONSTRAINT fk_boutiques_utilisateur
        FOREIGN KEY (utilisateur_id)
        REFERENCES utilisateurs(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- TABLE : categories
-- ─────────────────────────────────────────────────────────────
CREATE TABLE categories (
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    parent_id   INT UNSIGNED             DEFAULT NULL,
    nom         VARCHAR(100)    NOT NULL,
    slug        VARCHAR(100)    NOT NULL,
    description TEXT                     DEFAULT NULL,
    icone       VARCHAR(100)             DEFAULT NULL,
    image       VARCHAR(500)             DEFAULT NULL,
    ordre       TINYINT UNSIGNED NOT NULL DEFAULT 0,
    est_active  TINYINT(1)      NOT NULL DEFAULT 1,

    PRIMARY KEY (id),
    UNIQUE KEY uk_categories_slug (slug),
    INDEX idx_categories_parent (parent_id),
    INDEX idx_categories_est_active (est_active),
    CONSTRAINT fk_categories_parent
        FOREIGN KEY (parent_id)
        REFERENCES categories(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- TABLE : produits
-- ─────────────────────────────────────────────────────────────
CREATE TABLE produits (
    id                  INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    boutique_id         INT UNSIGNED        NOT NULL,
    categorie_id        INT UNSIGNED        NOT NULL,
    nom                 VARCHAR(255)        NOT NULL,
    slug                VARCHAR(255)        NOT NULL,
    description         TEXT                         DEFAULT NULL,
    description_courte  VARCHAR(500)                 DEFAULT NULL,
    prix                DECIMAL(10,2)       NOT NULL,
    prix_promo          DECIMAL(10,2)                DEFAULT NULL,
    stock               INT UNSIGNED        NOT NULL DEFAULT 0,
    stock_minimum       INT UNSIGNED        NOT NULL DEFAULT 5,
    images              JSON                         DEFAULT NULL,
    tags                JSON                         DEFAULT NULL,
    statut              ENUM('BROUILLON','PUBLIE','EN_RUPTURE','ARCHIVE')
                                            NOT NULL DEFAULT 'BROUILLON',
    est_mis_en_avant    TINYINT(1)          NOT NULL DEFAULT 0,
    poids               DECIMAL(8,3)                 DEFAULT NULL,
    dimensions          VARCHAR(50)                  DEFAULT NULL,
    note_moyenne        DECIMAL(3,2)        NOT NULL DEFAULT 0.00,
    nombre_avis         INT UNSIGNED        NOT NULL DEFAULT 0,
    date_creation       DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_mise_a_jour    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP
                                            ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uk_produits_slug (slug),
    INDEX idx_produits_boutique (boutique_id),
    INDEX idx_produits_categorie (categorie_id),
    INDEX idx_produits_statut (statut),
    INDEX idx_produits_prix (prix),
    INDEX idx_produits_note (note_moyenne),
    INDEX idx_produits_mise_en_avant (est_mis_en_avant),
    FULLTEXT INDEX ft_produits_search (nom, description_courte),
    CONSTRAINT fk_produits_boutique
        FOREIGN KEY (boutique_id)
        REFERENCES boutiques(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_produits_categorie
        FOREIGN KEY (categorie_id)
        REFERENCES categories(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT chk_produits_prix
        CHECK (prix >= 0 AND (prix_promo IS NULL OR prix_promo < prix))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- TABLE : adresses_livraison
-- ─────────────────────────────────────────────────────────────
CREATE TABLE adresses_livraison (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    utilisateur_id  INT UNSIGNED    NOT NULL,
    nom             VARCHAR(100)    NOT NULL,
    prenom          VARCHAR(100)    NOT NULL,
    entreprise      VARCHAR(150)             DEFAULT NULL,
    ligne1          VARCHAR(255)    NOT NULL,
    ligne2          VARCHAR(255)             DEFAULT NULL,
    ville           VARCHAR(100)    NOT NULL,
    code_postal     VARCHAR(10)     NOT NULL,
    region          VARCHAR(100)             DEFAULT NULL,
    pays            CHAR(3)         NOT NULL DEFAULT 'FRA',
    telephone       VARCHAR(20)              DEFAULT NULL,
    est_par_defaut  TINYINT(1)      NOT NULL DEFAULT 0,
    date_creation   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    INDEX idx_adresses_utilisateur (utilisateur_id),
    INDEX idx_adresses_par_defaut (utilisateur_id, est_par_defaut),
    CONSTRAINT fk_adresses_utilisateur
        FOREIGN KEY (utilisateur_id)
        REFERENCES utilisateurs(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- TABLE : commandes
-- ─────────────────────────────────────────────────────────────
CREATE TABLE commandes (
    id                      INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    acheteur_id             INT UNSIGNED    NOT NULL,
    adresse_livraison_id    INT UNSIGNED    NOT NULL,
    reference               VARCHAR(30)     NOT NULL,
    statut                  ENUM('EN_ATTENTE_PAIEMENT','PAYEE','EN_PREPARATION',
                                 'EXPEDIEE','LIVREE','ANNULEE','REMBOURSEE')
                                            NOT NULL DEFAULT 'EN_ATTENTE_PAIEMENT',
    sous_total              DECIMAL(10,2)   NOT NULL,
    frais_livraison         DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    taxe                    DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    total                   DECIMAL(10,2)   NOT NULL,
    mode_livraison          ENUM('STANDARD','EXPRESS','RETRAIT')
                                            NOT NULL DEFAULT 'STANDARD',
    numero_suivi            VARCHAR(100)             DEFAULT NULL,
    transporteur            VARCHAR(50)              DEFAULT NULL,
    notes                   TEXT                     DEFAULT NULL,
    date_commande           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_expedition         DATETIME                 DEFAULT NULL,
    date_livraison          DATETIME                 DEFAULT NULL,
    date_mise_a_jour        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                            ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uk_commandes_reference (reference),
    INDEX idx_commandes_acheteur (acheteur_id),
    INDEX idx_commandes_statut (statut),
    INDEX idx_commandes_date (date_commande),
    INDEX idx_commandes_adresse (adresse_livraison_id),
    CONSTRAINT fk_commandes_acheteur
        FOREIGN KEY (acheteur_id)
        REFERENCES utilisateurs(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_commandes_adresse
        FOREIGN KEY (adresse_livraison_id)
        REFERENCES adresses_livraison(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT chk_commandes_total
        CHECK (total >= 0 AND sous_total >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- TABLE : lignes_commande
-- ─────────────────────────────────────────────────────────────
CREATE TABLE lignes_commande (
    id                      INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    commande_id             INT UNSIGNED    NOT NULL,
    produit_id              INT UNSIGNED    NOT NULL,
    nom_produit_snapshot    VARCHAR(255)    NOT NULL,
    prix_unitaire_snapshot  DECIMAL(10,2)   NOT NULL,
    quantite                INT UNSIGNED    NOT NULL DEFAULT 1,
    sous_total              DECIMAL(10,2)   NOT NULL,
    options_choisies        JSON                     DEFAULT NULL,

    PRIMARY KEY (id),
    INDEX idx_lignes_commande (commande_id),
    INDEX idx_lignes_produit (produit_id),
    CONSTRAINT fk_lignes_commande
        FOREIGN KEY (commande_id)
        REFERENCES commandes(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_lignes_produit
        FOREIGN KEY (produit_id)
        REFERENCES produits(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT chk_lignes_quantite
        CHECK (quantite >= 1),
    CONSTRAINT chk_lignes_prix
        CHECK (prix_unitaire_snapshot >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- TABLE : paiements
-- ─────────────────────────────────────────────────────────────
CREATE TABLE paiements (
    id                          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    commande_id                 INT UNSIGNED    NOT NULL,
    stripe_payment_intent_id    VARCHAR(255)    NOT NULL,
    stripe_charge_id            VARCHAR(255)             DEFAULT NULL,
    montant                     DECIMAL(10,2)   NOT NULL,
    devise                      CHAR(3)         NOT NULL DEFAULT 'EUR',
    statut                      ENUM('EN_ATTENTE','CAPTURE','LIBERE','REMBOURSE','ECHOUE')
                                                NOT NULL DEFAULT 'EN_ATTENTE',
    methode                     ENUM('CARTE','PAYPAL','VIREMENT')
                                                NOT NULL DEFAULT 'CARTE',
    derniers_4_chiffres         CHAR(4)                  DEFAULT NULL,
    marque_carte                VARCHAR(20)              DEFAULT NULL,
    date_paiement               DATETIME                 DEFAULT NULL,
    date_remboursement          DATETIME                 DEFAULT NULL,
    date_liberation             DATETIME                 DEFAULT NULL,
    raison_echec                TEXT                     DEFAULT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uk_paiements_commande (commande_id),
    UNIQUE KEY uk_paiements_stripe_pi (stripe_payment_intent_id),
    INDEX idx_paiements_statut (statut),
    INDEX idx_paiements_date_liberation (date_liberation),
    CONSTRAINT fk_paiements_commande
        FOREIGN KEY (commande_id)
        REFERENCES commandes(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- TABLE : avis
-- ─────────────────────────────────────────────────────────────
CREATE TABLE avis (
    id                      INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    produit_id              INT UNSIGNED    NOT NULL,
    utilisateur_id          INT UNSIGNED    NOT NULL,
    commande_id             INT UNSIGNED             DEFAULT NULL,
    note                    TINYINT UNSIGNED NOT NULL,
    titre                   VARCHAR(200)             DEFAULT NULL,
    commentaire             TEXT                     DEFAULT NULL,
    photos                  JSON                     DEFAULT NULL,
    est_verifie             TINYINT(1)      NOT NULL DEFAULT 0,
    est_visible             TINYINT(1)      NOT NULL DEFAULT 1,
    nombre_utiles           INT UNSIGNED    NOT NULL DEFAULT 0,
    reponse_vendeur         TEXT                     DEFAULT NULL,
    date_creation           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_mise_a_jour        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                            ON UPDATE CURRENT_TIMESTAMP,
    date_reponse_vendeur    DATETIME                 DEFAULT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uk_avis_utilisateur_produit (utilisateur_id, produit_id),
    INDEX idx_avis_produit (produit_id),
    INDEX idx_avis_utilisateur (utilisateur_id),
    INDEX idx_avis_commande (commande_id),
    INDEX idx_avis_note (note),
    INDEX idx_avis_est_visible (est_visible),
    CONSTRAINT fk_avis_produit
        FOREIGN KEY (produit_id)
        REFERENCES produits(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_avis_utilisateur
        FOREIGN KEY (utilisateur_id)
        REFERENCES utilisateurs(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_avis_commande
        FOREIGN KEY (commande_id)
        REFERENCES commandes(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,
    CONSTRAINT chk_avis_note
        CHECK (note BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
```

---

## Composants programmables SGBD (triggers, fonction, procédure, event)

Au-delà des tables et contraintes déclaratives, le MPD embarque une couche de logique métier exécutée directement par le SGBD, indépendamment du code PHP applicatif. Cette logique est implémentée dans `marketcraft-backend/migrations.sql` et vise deux objectifs : garantir la cohérence de données dérivées (notes moyennes) même en cas d'écriture SQL directe, et automatiser une tâche métier récurrente (libération des paiements).

### Triggers de recalcul de note moyenne

| Trigger | Événement | Action |
|---------|-----------|--------|
| `trg_avis_after_insert` | `AFTER INSERT ON avis` | Recalcule `produits.note_moyenne` (moyenne des notes) et `produits.nombre_avis` (COUNT) pour le produit concerné |
| `trg_avis_after_update` | `AFTER UPDATE ON avis` | Idem, recalcul déclenché si la note d'un avis existant est modifiée |
| `trg_avis_after_delete` | `AFTER DELETE ON avis` | Idem, recalcul déclenché après suppression d'un avis (avec `COALESCE(..., 0)` pour remettre `note_moyenne` à 0 si le produit n'a plus aucun avis) |
| `trg_produits_after_update_note` | `AFTER UPDATE ON produits` | Si `note_moyenne` a changé, recalcule en cascade `boutiques.note_moyenne` (moyenne des `note_moyenne` des produits actifs de la boutique) |

Ces quatre triggers forment une chaîne de propagation `avis → produits → boutiques` : un avis client met automatiquement à jour la note du produit, qui met à jour la note de la boutique, sans intervention du code applicatif. Cela garantit la cohérence même si les données sont modifiées par un script d'administration ou une requête SQL directe.

### Fonction de vérification de stock

`fn_stock_suffisant(p_produit_id INT UNSIGNED, p_quantite INT) RETURNS BOOLEAN` — fonction `DETERMINISTIC` / `READS SQL DATA` qui vérifie que le stock disponible d'un produit couvre la quantité demandée. Utilisable directement dans une clause `WHERE` ou dans le code applicatif via une requête `SELECT fn_stock_suffisant(...)`, pour centraliser la règle de gestion « stock suffisant » à un seul endroit.

### Procédure et event de libération des paiements (J+14)

`sp_liberer_paiements_echus()` implémente la règle métier : un paiement au statut `valide` dont la commande associée est livrée (`date_livraison` non nulle) depuis au moins 14 jours passe automatiquement au statut `libere` (avec horodatage dans `date_liberation`). Cette procédure protège l'acheteur (délai de rétractation/réclamation) tout en automatisant le virement des fonds au vendeur passé ce délai.

`evt_liberation_paiements_quotidien` est un `EVENT` MySQL planifié (`ON SCHEDULE EVERY 1 DAY`) qui appelle cette procédure chaque jour, remplaçant un cron job externe par une tâche planifiée directement au niveau du SGBD (nécessite `SET GLOBAL event_scheduler = ON`).

### Schéma associé

Colonnes ajoutées pour supporter ces composants : `produits.note_moyenne`, `produits.nombre_avis`, `boutiques.note_moyenne`, `commandes.date_livraison`, `paiements.date_liberation`, et extension de l'ENUM `paiements.statut` avec la valeur `libere`.

> Le script complet (triggers, fonction, procédure, event, données de test et instructions de vérification manuelle) se trouve dans `marketcraft-backend/migrations.sql`, section « Composants SGBD (CP8) ».

## Choix techniques et justification

| Choix physique | Valeur retenue | Justification |
|-----------------|-----------------|----------------|
| Moteur de stockage | `InnoDB` | Seul moteur MySQL supportant les clés étrangères, les transactions ACID et le row-level locking, indispensable pour les paiements et le stock |
| Encodage / collation | `utf8mb4` / `utf8mb4_unicode_ci` | Support complet Unicode (emojis, accents, caractères multi-octets) et tri linguistique correct |
| Clés primaires | `INT UNSIGNED AUTO_INCREMENT` | Entiers auto-incrémentés, suffisants au volume attendu, plus performants qu'un UUID pour les jointures et l'index clusterisé InnoDB |
| `DECIMAL(10,2)` pour les montants | Précision exacte, pas d'arrondi flottant | Obligatoire pour toute donnée monétaire (prix, totaux, paiements) |
| `JSON` (images, tags, options_choisies, photos) | Type natif MySQL 8+ | Évite une table de jointure pour des collections de taille variable peu interrogées individuellement |
| `ENUM` pour les statuts | Liste fermée en base | Garantit l'intégrité des valeurs de statut sans table de référence supplémentaire, au prix d'une migration ALTER en cas d'ajout de valeur |
| `FULLTEXT INDEX` sur `produits` | Recherche texte native | Évite une dépendance à un moteur de recherche externe (Elasticsearch) pour le MVP |
| Index composites (ex. `idx_adresses_par_defaut`) | Sur les couples de colonnes filtrées ensemble | Optimise les requêtes fréquentes (ex. adresse par défaut d'un utilisateur) |
| `snapshot` dans `lignes_commande` | Duplication du nom/prix produit | Garantit l'immuabilité de l'historique de commande même si le produit change de prix ou est supprimé ensuite |

## Stratégie d'indexation

- **Clés primaires** : index clusterisé InnoDB sur `id` pour chaque table (recherche et jointure directes).
- **Clés étrangères** : toujours indexées explicitement (MySQL ne le fait pas automatiquement dans tous les cas) pour accélérer les jointures et les vérifications de contrainte.
- **Colonnes de filtrage fréquent** : `statut`, `est_actif`, `est_visible` — index simples pour les clauses `WHERE` des dashboards vendeur/admin.
- **Recherche texte** : `FULLTEXT INDEX ft_produits_search` sur `nom` et `description_courte` pour `MATCH ... AGAINST`.
- **Unicité métier** : `UNIQUE KEY` sur `email`, `slug`, `reference`, `siret`, `token` — empêche les doublons au niveau base, en complément de la validation applicative.

## Légende MPD

| Notation | Signification |
|----------|---------------|
| `PK` | Clé primaire — identifie de façon unique chaque ligne |
| `FK` | Clé étrangère — référence une clé primaire d'une autre table |
| `UK` | Contrainte d'unicité (`UNIQUE KEY`) |
| `NOT NULL` | Champ obligatoire |
| `DEFAULT` | Valeur par défaut si non fournie |
| `ON DELETE CASCADE` | Suppression en cascade des enregistrements enfants |
| `ON DELETE RESTRICT` | Interdit la suppression si des enregistrements référencent cette ligne |
| `ON DELETE SET NULL` | Nullifie la clé étrangère si le parent est supprimé |
| `ENGINE=InnoDB` | Moteur de stockage transactionnel avec support des clés étrangères |
| `DEFAULT CHARSET=utf8mb4` | Encodage Unicode complet (4 octets par caractère) |
| `DECIMAL(10,2)` | Nombre décimal avec 10 chiffres au total et 2 après la virgule |
| `JSON` | Champ JSON natif MySQL 8+ pour tableaux et objets |
| `FULLTEXT` | Index de recherche plein texte pour les recherches produits |
| `ENUM` | Liste fermée de valeurs autorisées |
| `CHECK` | Contrainte de validation au niveau base (ex. prix positif) |
| `snapshot` | Copie de la valeur au moment de la commande (immuable) |
