# MarketCraft — Marketplace Artisanale

![Laravel](https://img.shields.io/badge/Laravel-11-FF2D20?style=flat-square&logo=laravel&logoColor=white)
![React](https://img.shields.io/badge/React-18-61DAFB?style=flat-square&logo=react&logoColor=black)
![React Native](https://img.shields.io/badge/React_Native-0.74-61DAFB?style=flat-square&logo=react&logoColor=black)
![MySQL](https://img.shields.io/badge/MySQL-8.0+-4479A1?style=flat-square&logo=mysql&logoColor=white)
![JWT](https://img.shields.io/badge/JWT-Auth-000000?style=flat-square&logo=jsonwebtokens&logoColor=white)
![OpenAPI](https://img.shields.io/badge/OpenAPI-3.0-6BA539?style=flat-square&logo=openapiinitiative&logoColor=white)
![License](https://img.shields.io/badge/Licence-MIT-green?style=flat-square)

---

## Description

**MarketCraft** est une marketplace e-commerce dédiée à l'artisanat : les artisans y ouvrent une boutique et vendent leurs créations directement aux acheteurs.

L'architecture est **découplée** autour d'une API REST unique :

- **API** : **Laravel 11 + Eloquent**, sécurisée par **JWT**, exposant un contrat REST documenté en **OpenAPI 3.0**.
- **Web** : **React 18** + Tailwind CSS (acheteurs, vendeurs, back-office admin).
- **Mobile** : **React Native 0.74** (Android / iOS).
- **Base de données** : **MySQL 8**, schéma relationnel normalisé avec triggers, fonction et procédure stockée.

> Le back-end MVC PHP écrit à la main (`marketcraft-backend/`) a été **reconstruit sous Laravel** dans `marketcraft-api/`. L'ancien reste dans le dépôt comme filet de sécurité et n'est plus le back-end de référence.

---

## Composants du projet

| Dossier | Rôle | Stack |
|---|---|---|
| `marketcraft-api/` | **API REST** (back-end de référence) | Laravel 11, Eloquent, JWT |
| `marketcraft-frontend/` | Application web | React 18, Tailwind, React Query |
| `marketcraft-mobile/` | Application mobile | React Native 0.74 |
| `marketcraft-backend/` | Ancien back-end PHP MVC (legacy, conservé) | PHP 8, PDO |
| `docs/` | Documentation, diagrammes UML/Merise, état des lieux | Markdown |

---

## Fonctionnalités

### Acheteurs
- Navigation et recherche de produits, filtres croisés **objet × matériau**, prix et note.
- **Recherche IA** en langage naturel et **recommandations personnalisées** (module IA du cahier des charges).
- Panier persistant, tunnel d'achat (adresse pré-remplie), historique et suivi des commandes.
- Dépôt d'avis **conditionné à une commande livrée**.

### Vendeurs (artisans)
- Création d'une boutique, CRUD produits (images, catégories, stock).
- Gestion des commandes reçues et de leurs statuts.
- **Tableau de bord** : chiffre d'affaires, commandes, note moyenne, top produits.
- **Analyse concurrentielle** IA : positionnement tarifaire face au marché.

### Administration (back-office)
- Statistiques de la plateforme, supervision des utilisateurs et des boutiques.
- Gestion des catégories, modération des avis, journal d'activité.

### Sécurité
- Authentification **JWT** (jeton d'accès + rafraîchissement).
- **Verrouillage** du compte après 5 échecs, **captcha** maison dès le 3ᵉ échec.
- Mots de passe **bcrypt** (coût 12), en-têtes de sécurité, CORS piloté par configuration.

---

## Installation

### Prérequis
- PHP 8.2+, Composer, MySQL 8+
- Node.js 18+ (20 recommandé)
- Pour le mobile : Android Studio (JDK 21 embarqué) et un émulateur

### 1. API (Laravel) — `marketcraft-api/`

```bash
cd marketcraft-api
composer install
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
```

Configurer `.env` — **`APP_URL` doit correspondre au port réellement servi** (les URL d'images sont absolues) :

```
APP_URL=http://localhost:8000
DB_DATABASE=marketcraft
```

Base de données :

```bash
# Base neuve :
php artisan migrate

# Base déjà peuplée (schéma existant) : adopter avant de migrer
php artisan db:adopter --dry-run
php artisan db:adopter
php artisan migrate
```

Catalogue de démonstration (deux artisans, seize produits) : importer
`marketcraft-api/database/sql/004_catalogue_demo.sql`. Puis démarrer :

```bash
php artisan serve --port=8000
```

### 2. Web (React) — `marketcraft-frontend/`

```bash
cd marketcraft-frontend
npm install
npm start        # http://localhost:3000  (API attendue sur :8000)
```

### 3. Mobile (React Native) — `marketcraft-mobile/`

```bash
cd marketcraft-mobile
npm install
npx react-native start          # bundler Metro
```

Puis, un émulateur démarré, lancer depuis Android Studio (ouvrir le dossier
`android/`) **ou** en ligne de commande :

```bash
# JAVA_HOME pointé sur le JDK d'Android Studio (PowerShell)
$env:JAVA_HOME = "C:\Program Files\Android\Android Studio\jbr"
npx react-native run-android
```

> L'émulateur Android joint l'API de l'hôte via `http://10.0.2.2:8000` (géré automatiquement dans l'app).

---

## Documentation de l'API

- **Swagger UI** (interactif) : `http://localhost:8000/api/docs`
- **Spécification** : [`marketcraft-api/openapi.yaml`](marketcraft-api/openapi.yaml) — OpenAPI 3.0, ~48 opérations.

Enveloppes de réponse : `{ success, message, data }` (succès), `{ success, data, pagination }` (paginé), `{ success, error, details }` (erreur). Les endpoints d'authentification et d'upload renvoient leurs données au premier niveau (hors `data`), par compatibilité.

---

## Comptes de démonstration

Mot de passe commun : **`Password123`**

| Rôle | Adresse |
|---|---|
| admin | `marie.dupont@example.com` |
| vendeur | `paul.martin@example.com`, `sophie.bernard@example.com` |
| client | `jules.lemoine@example.com`, `camille.petit@example.com` |

> Un vendeur ne peut pas acheter et un acheteur ne peut pas vendre : une démonstration complète exige **deux comptes**.

---

## Rôles utilisateurs

| Permission | Acheteur | Vendeur | Admin |
|---|:---:|:---:|:---:|
| Parcourir / rechercher les produits | Oui | Oui | Oui |
| Passer une commande | Oui | Non | Non |
| Déposer un avis (achat livré) | Oui | Non | Non |
| Créer une boutique / gérer ses produits | Non | Oui | Non |
| Gérer les commandes reçues / dashboard | Non | Oui | Non |
| Superviser utilisateurs, boutiques, avis | Non | Non | Oui |
| Gérer les catégories | Non | Non | Oui |

---

## Documentation & diagrammes

| Fichier | Contenu |
|---|---|
| [`marketcraft-api/README.md`](marketcraft-api/README.md) | Démarrage, architecture et sécurité de l'API |
| [`marketcraft-api/openapi.yaml`](marketcraft-api/openapi.yaml) | Spécification OpenAPI 3.0 de l'API |
| [`docs/20_etat_des_lieux_backend_laravel.md`](docs/20_etat_des_lieux_backend_laravel.md) | État des lieux du portage Laravel |
| [`docs/18_conformite_cahier_des_charges.md`](docs/18_conformite_cahier_des_charges.md) | Audit de conformité au cahier des charges |
| [`docs/01_uml_classes.md`](docs/01_uml_classes.md) → [`docs/13_merise_mpd.md`](docs/13_merise_mpd.md) | Diagrammes UML (classes, séquence, cas d'usage) et Merise (MCD, MLD, MPD) |
| [`docs/10_architecture.md`](docs/10_architecture.md), [`docs/11_deploiement.md`](docs/11_deploiement.md) | Architecture applicative et déploiement |
| [`docs/15_gestion_projet.md`](docs/15_gestion_projet.md) | Gestion de projet (backlog, Kanban, Git) |

---

## Licence

Distribué sous licence **MIT**. Voir [LICENSE](LICENSE).
