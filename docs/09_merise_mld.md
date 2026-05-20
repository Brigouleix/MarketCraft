# Modèle Logique de Données (MLD) - Merise

Description : Le MLD traduit le MCD en un modèle relationnel implémentable dans un SGBDR (MySQL). Chaque entité devient une table, chaque association est matérialisée par des clés étrangères. La section SQL DDL détaille la création complète des tables avec contraintes, index et types précis.

```mermaid
erDiagram
    utilisateurs {
        INT id PK
        VARCHAR_100 nom
        VARCHAR_100 prenom
        VARCHAR_255 email UK
        VARCHAR_255 mot_de_passe_hash
        VARCHAR_20 telephone
        ENUM_role role
        TINYINT_1 est_actif
        VARCHAR_255 token_verification
        VARCHAR_255 token_reset
        DATETIME token_reset_expiration
        INT tentatives_echec
        DATETIME verrouille_jusqu_a
        DATETIME date_inscription
        DATETIME derniere_connexion
    }

    boutiques {
        INT id PK
        INT utilisateur_id FK
        VARCHAR_150 nom
        VARCHAR_150 slug UK
        TEXT description
        VARCHAR_500 logo
        VARCHAR_500 banniere
        ENUM_statut statut
        DECIMAL_3_2 note_moyenne
        INT nombre_ventes
        VARCHAR_14 siret UK
        VARCHAR_255 adresse
        VARCHAR_100 ville
        VARCHAR_10 code_postal
        VARCHAR_3 pays
        DATETIME date_creation
        DATETIME date_mise_a_jour
    }

    categories {
        INT id PK
        INT parent_id FK
        VARCHAR_100 nom
        VARCHAR_100 slug UK
        TEXT description
        VARCHAR_100 icone
        VARCHAR_500 image
        TINYINT ordre
        TINYINT_1 est_active
    }

    produits {
        INT id PK
        INT boutique_id FK
        INT categorie_id FK
        VARCHAR_255 nom
        VARCHAR_255 slug UK
        TEXT description
        VARCHAR_500 description_courte
        DECIMAL_10_2 prix
        DECIMAL_10_2 prix_promo
        INT stock
        INT stock_minimum
        JSON images
        JSON tags
        ENUM_statut statut
        TINYINT_1 est_mis_en_avant
        DECIMAL_8_3 poids
        VARCHAR_50 dimensions
        DECIMAL_3_2 note_moyenne
        INT nombre_avis
        DATETIME date_creation
        DATETIME date_mise_a_jour
    }

    adresses_livraison {
        INT id PK
        INT utilisateur_id FK
        VARCHAR_100 nom
        VARCHAR_100 prenom
        VARCHAR_150 entreprise
        VARCHAR_255 ligne1
        VARCHAR_255 ligne2
        VARCHAR_100 ville
        VARCHAR_10 code_postal
        VARCHAR_100 region
        VARCHAR_3 pays
        VARCHAR_20 telephone
        TINYINT_1 est_par_defaut
        DATETIME date_creation
    }

    commandes {
        INT id PK
        INT acheteur_id FK
        INT adresse_livraison_id FK
        VARCHAR_30 reference UK
        ENUM_statut statut
        DECIMAL_10_2 sous_total
        DECIMAL_10_2 frais_livraison
        DECIMAL_10_2 taxe
        DECIMAL_10_2 total
        ENUM_mode mode_livraison
        VARCHAR_100 numero_suivi
        VARCHAR_50 transporteur
        TEXT notes
        DATETIME date_commande
        DATETIME date_expedition
        DATETIME date_livraison
        DATETIME date_mise_a_jour
    }

    lignes_commande {
        INT id PK
        INT commande_id FK
        INT produit_id FK
        VARCHAR_255 nom_produit_snapshot
        DECIMAL_10_2 prix_unitaire_snapshot
        INT quantite
        DECIMAL_10_2 sous_total
        JSON options_choisies
    }

    paiements {
        INT id PK
        INT commande_id FK UK
        VARCHAR_255 stripe_payment_intent_id UK
        VARCHAR_255 stripe_charge_id
        DECIMAL_10_2 montant
        VARCHAR_3 devise
        ENUM_statut statut
        ENUM_methode methode
        VARCHAR_4 derniers_4_chiffres
        VARCHAR_20 marque_carte
        DATETIME date_paiement
        DATETIME date_remboursement
        DATETIME date_liberation
        TEXT raison_echec
    }

    avis {
        INT id PK
        INT produit_id FK
        INT utilisateur_id FK
        INT commande_id FK
        TINYINT note
        VARCHAR_200 titre
        TEXT commentaire
        JSON photos
        TINYINT_1 est_verifie
        TINYINT_1 est_visible
        INT nombre_utiles
        TEXT reponse_vendeur
        DATETIME date_creation
        DATETIME date_mise_a_jour
        DATETIME date_reponse_vendeur
    }

    refresh_tokens {
        INT id PK
        INT utilisateur_id FK
        VARCHAR_500 token UK
        DATETIME expire_at
        DATETIME created_at
        VARCHAR_45 ip_adresse
        VARCHAR_255 user_agent
        TINYINT_1 est_revoque
    }

    %% ─── RELATIONS MLD ───
    utilisateurs ||--o{ boutiques : "utilisateur_id"
    utilisateurs ||--o{ commandes : "acheteur_id"
    utilisateurs ||--o{ avis : "utilisateur_id"
    utilisateurs ||--o{ adresses_livraison : "utilisateur_id"
    utilisateurs ||--o{ refresh_tokens : "utilisateur_id"

    boutiques ||--o{ produits : "boutique_id"

    categories ||--o{ produits : "categorie_id"
    categories |o--o{ categories : "parent_id"

    commandes ||--o{ lignes_commande : "commande_id"
    commandes ||--|| paiements : "commande_id"
    commandes }o--|| adresses_livraison : "adresse_livraison_id"

    produits ||--o{ lignes_commande : "produit_id"
    produits ||--o{ avis : "produit_id"

    avis }o--o| commandes : "commande_id"
```

---

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

## Légende MLD

| Notation | Signification |
|----------|---------------|
| `PK` | Clé primaire — identifie de façon unique chaque ligne |
| `FK` | Clé étrangère — référence une clé primaire d'une autre table |
| `UK` | Contrainte d'unicité (UNIQUE KEY) |
| `NOT NULL` | Champ obligatoire |
| `DEFAULT` | Valeur par défaut si non fournie |
| `ON DELETE CASCADE` | Suppression en cascade des enregistrements enfants |
| `ON DELETE RESTRICT` | Interdit la suppression si des enregistrements référencent cette ligne |
| `ON DELETE SET NULL` | Nullifie la clé étrangère si le parent est supprimé |
| `DECIMAL(10,2)` | Nombre décimal avec 10 chiffres au total et 2 après la virgule |
| `JSON` | Champ JSON natif MySQL 8+ pour tableaux et objets |
| `FULLTEXT` | Index de recherche plein texte pour les recherches produits |
| `ENUM` | Liste fermée de valeurs autorisées |
| `snapshot` | Copie de la valeur au moment de la commande (immuable) |
