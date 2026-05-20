-- ============================================================
-- MarketCraft — Seeds (données de démonstration)
-- Utiliser après migrations.sql / mpd.sql
-- ============================================================

USE marketcraft;

-- Désactiver les checks FK pendant l'insertion
SET FOREIGN_KEY_CHECKS = 0;

-- ─────────────────────────────────────────────────────────────
-- Catégories
-- ─────────────────────────────────────────────────────────────
INSERT INTO categories (nom, slug, icone) VALUES
  ('Mobilier',       'mobilier',       '🪑'),
  ('Bijoux',         'bijoux',         '💍'),
  ('Céramique',      'ceramique',      '🏺'),
  ('Textile',        'textile',        '🧵'),
  ('Décoration',     'decoration',     '🕯️'),
  ('Maroquinerie',   'maroquinerie',   '👜'),
  ('Gastronomie',    'gastronomie',    '🧀'),
  ('Papeterie',      'papeterie',      '📝');

-- ─────────────────────────────────────────────────────────────
-- Utilisateurs (passwords = "Password1!" bcrypt)
-- hash généré avec password_hash('Password1!', PASSWORD_BCRYPT, ['cost'=>12])
-- ─────────────────────────────────────────────────────────────
INSERT INTO utilisateurs (nom, prenom, email, password_hash, role, telephone) VALUES
  ('Admin',    'MarketCraft', 'admin@marketcraft.fr',
   '$2y$12$LpX7Y9eK0aB3mN5qR8uVOuWjS6dT2fG4hI1jK9lM7nO0pQ3rS5tU', 'admin', NULL),

  ('Leblanc',  'Sophie',      'sophie@atelier-bois.fr',
   '$2y$12$LpX7Y9eK0aB3mN5qR8uVOuWjS6dT2fG4hI1jK9lM7nO0pQ3rS5tU', 'vendeur', '+33612345678'),

  ('Moreau',   'Thomas',      'thomas@ceramiques-tm.fr',
   '$2y$12$LpX7Y9eK0aB3mN5qR8uVOuWjS6dT2fG4hI1jK9lM7nO0pQ3rS5tU', 'vendeur', '+33623456789'),

  ('Petit',    'Isabelle',    'isabelle@fil-en-aiguille.fr',
   '$2y$12$LpX7Y9eK0aB3mN5qR8uVOuWjS6dT2fG4hI1jK9lM7nO0pQ3rS5tU', 'vendeur', '+33634567890'),

  ('Dupont',   'Lucas',       'lucas.dupont@gmail.com',
   '$2y$12$LpX7Y9eK0aB3mN5qR8uVOuWjS6dT2fG4hI1jK9lM7nO0pQ3rS5tU', 'client',  '+33645678901'),

  ('Martin',   'Emma',        'emma.martin@gmail.com',
   '$2y$12$LpX7Y9eK0aB3mN5qR8uVOuWjS6dT2fG4hI1jK9lM7nO0pQ3rS5tU', 'client',  '+33656789012'),

  ('Bernard',  'Hugo',        'hugo.bernard@outlook.com',
   '$2y$12$LpX7Y9eK0aB3mN5qR8uVOuWjS6dT2fG4hI1jK9lM7nO0pQ3rS5tU', 'client',  NULL);

-- ─────────────────────────────────────────────────────────────
-- Boutiques
-- ─────────────────────────────────────────────────────────────
INSERT INTO boutiques (vendeur_id, nom, description, localisation, est_active) VALUES
  (2, "L'Atelier du Bois",
   "Créations en bois massif faites à la main dans mon atelier lyonnais. Chaque pièce est unique, conçue avec des essences locales (chêne, noyer, frêne).",
   'Lyon, France', 1),

  (3, 'Céramiques Thomas Moreau',
   "Poteries et céramiques artisanales. Je travaille la terre chamottée et émaillée avec des glaçures naturelles. Pièces utilitaires et décoratives.",
   'Limoges, France', 1),

  (4, 'Fil en Aiguille',
   "Créations textiles brodées à la main. Coussins, tableaux brodés, accessoires — chaque création raconte une histoire.",
   'Paris, France', 1);

-- ─────────────────────────────────────────────────────────────
-- Produits
-- ─────────────────────────────────────────────────────────────
INSERT INTO produits (boutique_id, categorie_id, nom, description, prix, stock, tags) VALUES
  -- L'Atelier du Bois
  (1, 1, 'Table basse en chêne massif',
   'Table basse artisanale en chêne massif, finition huile naturelle. Dimensions : 120x60x45 cm. Personnalisable sur demande.',
   349.99, 3, 'bois,chêne,table,massif,artisanal,mobilier'),

  (1, 1, 'Étagère murale en noyer',
   "Étagère flottante en noyer américain, fixations invisibles. Disponible en 60, 80 ou 100 cm. Finition cire d'abeille.",
   129.00, 8, 'bois,noyer,étagère,mural,rangement'),

  (1, 5, 'Bougeoir en bois flotté',
   "Bougeoir sculpté dans du bois flotté récupéré en Bretagne. Pièce unique. Convient pour bougie chauffe-plat.",
   45.00, 12, 'bois,flotté,bougeoir,déco,artisanal,naturel'),

  (1, 6, 'Plateau de service en frêne',
   "Plateau rectangulaire en frêne massif avec poignées intégrées. Idéal petit-déjeuner au lit. 40x25 cm.",
   89.00, 6, 'bois,frêne,plateau,cuisine,service'),

  -- Céramiques Thomas Moreau
  (2, 3, 'Service à café 6 tasses — Bleu Ciel',
   "Service comprenant 6 tasses et 6 sous-tasses en grès chamotté, glaçure bleue dégradée. Passent au lave-vaisselle.",
   185.00, 4, 'céramique,grès,tasse,café,bleu,service,artisanal'),

  (2, 3, 'Vase soliflore — Collection Terre',
   'Vase soliflore en argile naturelle non émaillée. Hauteur 22 cm. Chaque pièce est unique par ses variations de cuisson.',
   65.00, 15, 'céramique,vase,terre,naturel,déco,soliflore'),

  (2, 3, 'Bol à soupe — Série Forêt',
   'Bol généreux en grès, glaçure verte forêt. Contenance 45 cl. Idéal soupe, ramen ou céréales.',
   38.00, 20, 'céramique,bol,grès,vert,soupe,cuisine'),

  (2, 5, 'Photophore en grès',
   'Photophore en grès percé de motifs géométriques. Crée une lumière tamisée unique. Pour bougie chauffe-plat.',
   42.00, 10, 'céramique,grès,photophore,lumière,déco'),

  -- Fil en Aiguille
  (3, 4, 'Coussin brodé — Fleurs des Champs',
   'Coussin 45x45 cm en lin naturel, broderie fleurs sauvages au point de croix. Rembourrage inclus.',
   95.00, 5, 'textile,broderie,coussin,lin,fleurs,point de croix'),

  (3, 4, 'Tableau brodé — Forêt enchantée',
   'Tableau 30x40 cm sur toile de lin. Scène de forêt brodée à la main, sous-verre inclus. Signé.',
   145.00, 2, 'textile,broderie,tableau,forêt,art,cadeau'),

  (3, 4, 'Tote bag brodé — Lavande',
   "Sac tote en coton naturel épais avec branche de lavande brodée. Anses longues. Parfait pour le marché.",
   55.00, 18, 'textile,sac,tote,broderie,lavande,coton,écolo');

-- ─────────────────────────────────────────────────────────────
-- Commandes
-- ─────────────────────────────────────────────────────────────
INSERT INTO commandes (utilisateur_id, statut, total, adresse_livraison) VALUES
  (5, 'livree',  394.99, '12 rue des Lilas, 75011 Paris'),
  (5, 'expediee', 95.00, '12 rue des Lilas, 75011 Paris'),
  (6, 'confirmee', 185.00, '8 avenue Victor Hugo, 69002 Lyon'),
  (7, 'en_attente', 129.00, '3 place Bellecour, 69002 Lyon');

INSERT INTO lignes_commande (commande_id, produit_id, quantite, prix_unitaire) VALUES
  (1, 1, 1, 349.99),
  (1, 3, 1,  45.00),
  (2, 9, 1,  95.00),
  (3, 5, 1, 185.00),
  (4, 2, 1, 129.00);

-- ─────────────────────────────────────────────────────────────
-- Avis
-- ─────────────────────────────────────────────────────────────
INSERT INTO avis (utilisateur_id, produit_id, note, commentaire) VALUES
  (5, 1, 5, "Absolument magnifique ! La table est encore plus belle en vrai. Livraison soignée, je recommande vivement."),
  (5, 3, 4, "Joli bougeoir, très original. Légèrement plus petit que je ne l'imaginais mais parfait."),
  (6, 5, 5, "Service à café sublime. Les tasses sont confortables en main et la couleur est superbe. Merci !"),
  (7, 2, 5, "Étagère parfaite, très bien finie. Installation facile. Le noyer est vraiment beau.");

-- ─────────────────────────────────────────────────────────────
-- Logs d'activité (exemples)
-- ─────────────────────────────────────────────────────────────
INSERT INTO logs_activite (utilisateur_id, action, entite, entite_id, ip_address) VALUES
  (1, 'admin_login',        NULL,       NULL, '127.0.0.1'),
  (2, 'product_create',     'produit',  1,    '82.64.23.11'),
  (2, 'product_create',     'produit',  2,    '82.64.23.11'),
  (5, 'order_create',       'commande', 1,    '90.12.34.56'),
  (6, 'order_create',       'commande', 3,    '88.45.67.89');

SET FOREIGN_KEY_CHECKS = 1;

SELECT 'Seeds MarketCraft chargés avec succès !' AS statut;
