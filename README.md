# MarketCraft - Marketplace Artisanale

![PHP](https://img.shields.io/badge/PHP-8.1+-777BB4?style=flat-square&logo=php&logoColor=white)
![React](https://img.shields.io/badge/React-18-61DAFB?style=flat-square&logo=react&logoColor=black)
![MySQL](https://img.shields.io/badge/MySQL-8.0+-4479A1?style=flat-square&logo=mysql&logoColor=white)
![TailwindCSS](https://img.shields.io/badge/Tailwind_CSS-3.x-06B6D4?style=flat-square&logo=tailwindcss&logoColor=white)
![JWT](https://img.shields.io/badge/JWT-Auth-000000?style=flat-square&logo=jsonwebtokens&logoColor=white)
![License](https://img.shields.io/badge/Licence-MIT-green?style=flat-square)

---

## Description

**MarketCraft** est une marketplace e-commerce dédiée à l'artisanat, permettant aux artisans de créer leur boutique en ligne et de vendre leurs créations directement aux acheteurs.

La plateforme repose sur une architecture moderne découplée :

- **Frontend** : React 18 avec Tailwind CSS, gestion d'état via Context API et hooks personnalisés
- **Backend** : PHP 8.1 suivant le pattern MVC, exposant une API REST sécurisée par JWT
- **Base de données** : MySQL 8.0+ avec un schéma relationnel normalisé

---

## Fonctionnalités

### Acheteurs

- Navigation et recherche de produits par mots-clés
- Filtres avancés : catégorie, fourchette de prix, note minimale
- Fiche produit détaillée avec galerie d'images et avis clients
- Panier persistant via `localStorage`
- Tunnel d'achat : saisie d'adresse de livraison et confirmation de commande
- Historique des commandes avec suivi du statut

### Vendeurs

- Création et personnalisation d'une boutique (nom, description, logo)
- CRUD complet sur les produits : titre, description, prix, stock, images
- Suivi et gestion des commandes reçues (statuts : en attente, expédié, livré)
- Dashboard avec indicateurs clés : chiffre d'affaires, nombre de commandes, note moyenne

### Admin

- Validation et activation des boutiques soumises par les vendeurs
- Gestion des comptes utilisateurs (suspension, suppression)
- Arbitrage des litiges entre acheteurs et vendeurs

---

## Structure du Projet

```
MarketCraft/
├── marketcraft-backend/          # PHP MVC Backend
│   ├── app/
│   │   ├── Controllers/          # 5 controllers (Auth, Product, Boutique, Order, Avis)
│   │   ├── Models/               # 6 models (User, Product, Boutique, Order, Payment, Avis)
│   │   └── Core/                 # Router, Controller, Auth (JWT)
│   ├── config/database.php       # Config PDO MySQL
│   ├── routes/web.php            # Toutes les routes API
│   ├── public/index.php          # Point d'entrée unique (front controller)
│   ├── migrations.sql            # Schéma complet + données de test
│   └── composer.json
│
├── marketcraft-frontend/         # React Frontend
│   ├── src/
│   │   ├── pages/               # 10 pages (Home, Products, Cart, Checkout, etc.)
│   │   ├── components/          # Navbar, Footer, ProductCard, FilterBar, etc.
│   │   ├── contexts/            # AuthContext, CartContext
│   │   ├── services/api.js      # Instance Axios + intercepteurs JWT
│   │   └── hooks/               # useProducts, useAuth, useCart
│   ├── tailwind.config.js
│   └── package.json
│
└── docs/                        # Documentation & Diagrammes
    ├── 01_uml_classes.md        # Diagramme de classes UML
    ├── 02_sequence_achat.md     # Séquence processus achat
    ├── 03_sequence_vendeur.md   # Séquence workflow vendeur
    ├── 04_sequence_auth.md      # Séquence authentification JWT
    ├── 05_activite_achat.md     # Activité parcours acheteur
    ├── 06_activite_vendeur.md   # Activité gestion vendeur
    ├── 07_activite_admin.md     # Activité administration
    ├── 08_merise_mcd.md         # MCD Merise
    ├── 09_merise_mld.md         # MLD Merise + SQL DDL
    ├── 10_architecture.md       # Architecture applicative
    ├── 11_deploiement.md        # Diagramme déploiement
    └── 12_cas_utilisation.md    # Cas d'utilisation UML
```

---

## API Endpoints

### Authentification

| Méthode | Route | Description | Auth requise |
|---------|-------|-------------|:------------:|
| `POST` | `/api/auth/register` | Inscription d'un nouvel utilisateur | Non |
| `POST` | `/api/auth/login` | Connexion et obtention du token JWT | Non |
| `POST` | `/api/auth/logout` | Invalidation de la session | Oui |
| `GET` | `/api/auth/me` | Profil de l'utilisateur connecté | Oui |

### Produits

| Méthode | Route | Description | Auth requise |
|---------|-------|-------------|:------------:|
| `GET` | `/api/products` | Liste paginée des produits (+ filtres) | Non |
| `GET` | `/api/products/{id}` | Détail d'un produit | Non |
| `POST` | `/api/products` | Créer un produit | Vendeur |
| `PUT` | `/api/products/{id}` | Modifier un produit | Vendeur (propriétaire) |
| `DELETE` | `/api/products/{id}` | Supprimer un produit | Vendeur (propriétaire) |

### Boutiques

| Méthode | Route | Description | Auth requise |
|---------|-------|-------------|:------------:|
| `GET` | `/api/boutiques` | Liste des boutiques validées | Non |
| `GET` | `/api/boutiques/{id}` | Détail d'une boutique et ses produits | Non |
| `POST` | `/api/boutiques` | Créer une boutique | Oui |
| `PUT` | `/api/boutiques/{id}` | Modifier sa boutique | Vendeur (propriétaire) |
| `PUT` | `/api/boutiques/{id}/validate` | Valider une boutique | Admin |
| `DELETE` | `/api/boutiques/{id}` | Supprimer une boutique | Admin |

### Commandes

| Méthode | Route | Description | Auth requise |
|---------|-------|-------------|:------------:|
| `GET` | `/api/orders` | Commandes de l'utilisateur connecté | Oui |
| `GET` | `/api/orders/{id}` | Détail d'une commande | Oui |
| `POST` | `/api/orders` | Passer une commande | Oui |
| `PUT` | `/api/orders/{id}/status` | Mettre à jour le statut | Vendeur |

### Avis

| Méthode | Route | Description | Auth requise |
|---------|-------|-------------|:------------:|
| `GET` | `/api/products/{id}/avis` | Avis d'un produit | Non |
| `POST` | `/api/products/{id}/avis` | Déposer un avis (acheteur vérifié) | Oui |
| `DELETE` | `/api/avis/{id}` | Supprimer un avis | Admin |

---

## Installation

### Prérequis

- PHP 8.1+
- MySQL 8.0+
- Node.js 18+
- Composer

### Backend

```bash
cd marketcraft-backend

# Copier et configurer les variables d'environnement
cp .env.example .env
# Éditer .env avec vos paramètres (voir section Variables d'environnement)

# Installer les dépendances PHP
composer install

# Initialiser la base de données
mysql -u root -p < migrations.sql

# Lancer le serveur de développement
php -S localhost:8000 -t public
```

L'API est accessible sur `http://localhost:8000/api`.

### Frontend

```bash
cd marketcraft-frontend

# Installer les dépendances Node
npm install

# Lancer le serveur de développement
npm start
```

L'application est accessible sur `http://localhost:3000`.

---

## Variables d'Environnement

Créer un fichier `.env` à la racine de `marketcraft-backend/` en vous basant sur `.env.example` :

| Variable | Description | Exemple |
|----------|-------------|---------|
| `DB_HOST` | Hôte de la base de données | `localhost` |
| `DB_NAME` | Nom de la base de données | `marketcraft` |
| `DB_USER` | Utilisateur MySQL | `root` |
| `DB_PASS` | Mot de passe MySQL | `secret` |
| `JWT_SECRET` | Clé secrète pour signer les tokens JWT | `une_chaine_aleatoire_longue` |
| `JWT_TTL` | Durée de vie du token en secondes | `3600` |
| `APP_ENV` | Environnement (`development` / `production`) | `development` |
| `APP_URL` | URL de base de l'API | `http://localhost:8000` |

---

## Modèle de Données

La base de données comprend **9 tables** principales :

| Table | Colonnes principales | Description |
|-------|---------------------|-------------|
| `users` | `id`, `email`, `password_hash`, `role`, `created_at` | Comptes utilisateurs (acheteur / vendeur / admin) |
| `boutiques` | `id`, `user_id`, `nom`, `description`, `logo_url`, `statut`, `created_at` | Boutiques des vendeurs |
| `products` | `id`, `boutique_id`, `titre`, `description`, `prix`, `stock`, `categorie`, `created_at` | Produits mis en vente |
| `product_images` | `id`, `product_id`, `url`, `ordre` | Images associées aux produits |
| `orders` | `id`, `user_id`, `adresse_livraison`, `statut`, `total`, `created_at` | Commandes passées |
| `order_items` | `id`, `order_id`, `product_id`, `quantite`, `prix_unitaire` | Lignes de commande |
| `payments` | `id`, `order_id`, `methode`, `statut`, `montant`, `paid_at` | Paiements associés aux commandes |
| `avis` | `id`, `product_id`, `user_id`, `note`, `commentaire`, `created_at` | Avis et notes des acheteurs |
| `litiges` | `id`, `order_id`, `description`, `statut`, `created_at` | Litiges soumis à l'administration |

---

## Diagrammes Disponibles

La documentation technique complète est disponible dans le dossier `docs/` :

| Fichier | Contenu |
|---------|---------|
| [`01_uml_classes.md`](docs/01_uml_classes.md) | Diagramme de classes UML complet (entités, relations, multiplicités) |
| [`02_sequence_achat.md`](docs/02_sequence_achat.md) | Diagramme de séquence du processus d'achat (de la recherche au paiement) |
| [`03_sequence_vendeur.md`](docs/03_sequence_vendeur.md) | Diagramme de séquence du workflow vendeur (création boutique → gestion commandes) |
| [`04_sequence_auth.md`](docs/04_sequence_auth.md) | Diagramme de séquence de l'authentification JWT (login, refresh, logout) |
| [`05_activite_achat.md`](docs/05_activite_achat.md) | Diagramme d'activité du parcours acheteur (navigation → confirmation) |
| [`06_activite_vendeur.md`](docs/06_activite_vendeur.md) | Diagramme d'activité de la gestion vendeur (produits, commandes, dashboard) |
| [`07_activite_admin.md`](docs/07_activite_admin.md) | Diagramme d'activité des tâches d'administration (validation, arbitrage) |
| [`08_merise_mcd.md`](docs/08_merise_mcd.md) | Modèle Conceptuel de Données (MCD) au format Merise |
| [`09_merise_mld.md`](docs/09_merise_mld.md) | Modèle Logique de Données (MLD) Merise + DDL SQL complet |
| [`10_architecture.md`](docs/10_architecture.md) | Schéma de l'architecture applicative (frontend ↔ API ↔ BDD) |
| [`11_deploiement.md`](docs/11_deploiement.md) | Diagramme de déploiement UML (serveurs, conteneurs, réseau) |
| [`12_cas_utilisation.md`](docs/12_cas_utilisation.md) | Diagramme de cas d'utilisation UML par rôle |

---

## Rôles Utilisateurs

| Permission | Acheteur | Vendeur | Admin |
|------------|:--------:|:-------:|:-----:|
| Parcourir les produits | Oui | Oui | Oui |
| Passer une commande | Oui | Oui | Non |
| Déposer un avis | Oui (achat vérifié) | Non | Non |
| Créer une boutique | Non | Oui | Non |
| Gérer ses produits | Non | Oui | Non |
| Gérer ses commandes reçues | Non | Oui | Non |
| Accéder au dashboard vendeur | Non | Oui | Non |
| Valider des boutiques | Non | Non | Oui |
| Gérer les utilisateurs | Non | Non | Oui |
| Arbitrer les litiges | Non | Non | Oui |
| Supprimer des avis | Non | Non | Oui |

---

## Conventions de Code

### PHP (Backend)

- **Autoloading** : PSR-4 via Composer (`App\` → `app/`)
- **Namespaces** : `App\Controllers`, `App\Models`, `App\Core`
- **Style** : PSR-12 (indentation 4 espaces, accolades ouvrantes sur la même ligne)
- **Réponses** : JSON systématique avec codes HTTP appropriés (200, 201, 400, 401, 403, 404, 422, 500)

### React (Frontend)

- **Composants** : exclusivement fonctionnels (pas de class components)
- **Hooks** : hooks custom dans `src/hooks/` pour encapsuler la logique métier
- **État global** : Context API (`AuthContext`, `CartContext`)
- **Requêtes** : centralisées dans `src/services/api.js` via Axios avec intercepteurs JWT

### CSS

- **Framework** : Tailwind CSS en mode utility-first
- **Composants** : classes utilitaires directement dans le JSX, extraction en `@apply` uniquement si réutilisation fréquente

### Git

- **Branches** : `feature/<nom-fonctionnalite>`, `fix/<nom-bug>`, `chore/<tache>`
- **Commits** : format conventionnel — `feat:`, `fix:`, `docs:`, `refactor:`, `test:`, `chore:`
- **Exemples** :
  - `feat: ajouter le panier persistant en localStorage`
  - `fix: corriger la validation du token JWT expiré`
  - `docs: mettre à jour le diagramme de séquence achat`

---

## Licence

Ce projet est distribué sous licence **MIT**.

Voir le fichier [LICENSE](LICENSE) pour plus de détails.
