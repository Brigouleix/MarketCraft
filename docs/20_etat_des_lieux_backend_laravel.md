# État des lieux — reconstruction du back-end sous Laravel 11

> Document de reprise. À lire en premier après une interruption ou un
> changement de poste, et à transmettre au binôme.
>
> Dernière mise à jour : 26 juillet 2026
> Branche : `feat/backend-laravel`

---

## 1. Où en est le projet

Le back-end MVC PHP écrit à la main (`marketcraft-backend/`) est reconstruit
sous **Laravel 11 + Eloquent** dans `marketcraft-api/`, en respectant le
contrat d'API consommé par le front React et l'application mobile.

**Le parcours de démonstration complet fonctionne** : inscription, connexion,
création de boutique, création de produit avec upload d'images, filtres par
catégorie et matériau, panier, commande, changement de statut, avis.

| Indicateur | Valeur |
|---|---|
| Routes exposées | 37 |
| Tests automatisés | 94, tous au vert |
| Contrôleurs | 11 |
| Modèles Eloquent | 12 |
| Services | 6 |
| Migrations | 13 |
| Pipeline CI | 4 jobs, tous verts |

Les deux back-ends coexistent dans le dépôt. L'ancien reste démontrable tant
qu'il n'est pas retiré — c'est le filet de sécurité du plan de bascule.

---

## 2. Environnement de travail

### Démarrage

```powershell
cd marketcraft-api
composer install
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
php artisan migrate            # ou db:adopter si la base existe déjà
php artisan serve --port=8000
```

```powershell
cd marketcraft-frontend
npm install
npm start
```

### Points de configuration à ne pas manquer

**`APP_URL` doit correspondre au port réellement servi.** Les URL d'images
sont stockées en absolu en base, construites depuis `APP_URL`. Un écart
entre les deux rend toutes les images invisibles, sans erreur explicite.
C'est arrivé une fois : `APP_URL` annonçait 8001, le serveur écoutait sur
8000.

En cas de changement de port ou de machine :

```powershell
php artisan config:clear
php artisan db:reparer --ancien-hote=http://localhost:ANCIEN_PORT
```

**Le front tombe par défaut sur `http://localhost:8000/api`.** Si le
back-end tourne ailleurs, créer `marketcraft-frontend/.env.local` avec
`REACT_APP_API_URL=...`. Sinon, supprimer ce fichier.

**Les images uploadées ne sont pas dans Git.** `public/uploads` est exclu.
Sur un nouveau poste, copier le contenu depuis
`marketcraft-backend/public/uploads`.

### Base de données

Base MySQL `marketcraft_4eme_dev`, copie de `marketcraft` (l'ancienne),
pour que les deux back-ends ne se corrompent pas mutuellement.

Sur une base **déjà peuplée**, ne pas lancer `migrate` directement :

```powershell
php artisan db:adopter --dry-run   # montre ce qui serait marqué comme appliqué
php artisan db:adopter
php artisan migrate                # ne joue que les migrations réellement manquantes
```

Catalogue de démonstration : importer
`marketcraft-api/database/sql/004_catalogue_demo.sql` depuis phpMyAdmin.
Deux artisans, seize produits, gammes de prix chevauchantes. Rejouable sans
doublon.

### Comptes de démonstration

Mot de passe commun : **`Password123`**

| Rôle | Adresse |
|---|---|
| admin | `marie.dupont@example.com` |
| vendeur | `paul.martin@example.com` |
| vendeur | `sophie.bernard@example.com` |
| vendeur | `hugo.roussel@example.com` |
| vendeur | `lea.nguyen@example.com` |
| client | `jules.lemoine@example.com` |
| client | `camille.petit@example.com` |

**La démonstration exige deux comptes** : un vendeur ne peut pas acheter,
un acheteur ne peut pas vendre.

---

## 3. Ce qui est fait

### Socle

- Squelette Laravel 11 complet, sans dépendance superflue.
- Migrations reproduisant le schéma français existant, triggers, fonction,
  procédure stockée et event compris (CP8 du référentiel).
- Modèles Eloquent avec `$table` explicite, `password_hash` surchargé,
  dates au format `Y-m-d H:i:s`, `prix` et `note_moyenne` en chaîne.
- Enveloppes JSON du contrat, point de sortie unique
  (`App\Support\ApiResponse`), exceptions comprises.
- JWT maison (`sub` en chaîne), middlewares `jwt` et `role:`.
- En-têtes de sécurité, CORS piloté par `ALLOWED_ORIGINS`.

### Fonctionnel

| Domaine | Routes | État |
|---|---|---|
| Authentification | 8 | complet, avec captcha et verrouillage |
| Produits | 5 | complet, filtres ET/OU sur catégorie et matériau |
| Boutiques | 6 | complet |
| Commandes | 5 | complet, plus la vue `?scope=ventes` |
| Avis | 4 | complet, plus l'endpoint d'éligibilité |
| Catégories | 1 | complet |
| Upload | 2 | complet |
| Recommandation IA | 4 | complet |
| Analyse concurrentielle | 1 | complet (hors périmètre CDC) |
| Santé | 1 | complet |

### Sécurité

- Verrouillage du compte : 5 échecs, 15 minutes, table
  `tentatives_connexion`.
- CAPTCHA maison signé en HMAC, sans état ni appel réseau, déclenché au
  3ᵉ échec.
- Journal d'activité en base (`journal_activite`), secrets masqués.
  **L'écran de consultation reste à écrire.**
- bcrypt coût 12, rehash à la connexion.
- Contrôles de propriété distincts des contrôles de rôle.

### Outillage

- Trois commandes artisan : `jwt:secret`, `db:adopter`, `db:reparer`.
- Job CI `api-tests` (PHP 8.2, SQLite en mémoire), garde-fou sur `.env`.
- CI déclenchée aussi sur `feat/**` et `fix/**`, pas seulement `main`.

### Front

Modifications limitées à des ajouts, sans toucher aux pages existantes :

- `RecommandationsIA` — bloc de suggestions, réutilisable.
- `AnalyseConcurrence` — onglet du tableau de bord vendeur.
- `ZoneAvis` — masque le formulaire d'avis quand le serveur le refuse.
- Historique de commandes : lignes détaillées, lien vers la fiche produit.
- Panier masqué pour un compte vendeur.
- Page « Mes statistiques » retirée.
- Correction d'une dépendance manquante dans `AISearchBar` qui cassait le
  build CI.

---

## 4. Décisions structurantes

À connaître avant de modifier quoi que ce soit.

**Les enveloppes de réponse sont cinq, pas trois.** `/auth/login`,
`/auth/register`, `/auth/refresh`, `/upload/image` et `/upload/images`
renvoient leurs données **hors** de `data`. Le front les lit ainsi. Les
envelopper proprement casse la connexion sans lever d'erreur.

**`note_moyenne` est maintenue par les triggers SQL, jamais recalculée.**
L'ancien back-end faisait les deux, ce qui produisait `"4.5000"` là où la
colonne dit `"4.50"`.

**Le rôle se choisit à l'inscription et n'évolue plus.** La promotion
automatique `client` → `vendeur` à la création d'une boutique a été retirée.

**L'avis exige une commande livrée**, pas seulement passée. La règle vit
dans `AvisController::evaluerEligibilite()`, utilisée à la fois pour
l'affichage du formulaire et pour le contrôle à l'écriture — une seule
implémentation.

**Les chiffres de l'analyse concurrentielle sont calculés, jamais générés.**
Le modèle de langage ne fait que commenter des statistiques établies par le
serveur.

**Le module IA imposé par le CDC est la recommandation personnalisée
(option C).** L'analyse concurrentielle est un ajout hors périmètre, assumé
et documenté. Voir `marketcraft-api/docs/ECARTS-CONTRAT.md` §5.10.

---

## 5. Ce qui reste à faire

Par ordre d'importance pour la notation.

### 5.1 Administration — 11 routes plus le journal

`GET /admin/stats`, `GET /admin/users`, `PUT /admin/users/:id/toggle`,
`GET /admin/boutiques`, `PUT /admin/boutiques/:id/toggle`,
`GET /admin/avis`, `DELETE /admin/avis/:id`, `GET /admin/categories`,
`POST /admin/categories`, `PUT /admin/categories/:id`,
`DELETE /admin/categories/:id`, plus `GET /admin/logs`.

Deux formes à respecter, décrites dans le brief :

- `GET /admin/categories` renvoie `{ data: { categories: [], racines: [] } }`,
  chaque catégorie portant `nb_produits` et `parent_nom` ;
- `DELETE /admin/categories/:id` répond **409** avec
  `details: { principale, liaisons }` si des produits sont rattachés, le
  client confirmant via `?force=1`.

**C'est le poste le plus important** : le CDC exige un journal d'activité
« consultable en back-office ». La table se remplit déjà, il manque l'écran.

### 5.2 Tableaux de bord

`GET /dashboard/stats` (vendeur) et `GET /dashboard/acheteur`.

L'onglet « Vue d'ensemble » du tableau de bord vendeur s'appuie dessus et
affiche actuellement des valeurs figées.

### 5.3 Recherche

`GET /search` et `POST /search/ai`.

Le modal de recherche IA du front appelle `/search/ai`. Le code de référence
est dans `marketcraft-backend/app/Controllers/SearchController.php` (507
lignes) — attention, `produits.images` y est renvoyé **en chaîne JSON**, pas
en tableau, contrairement aux autres endpoints.

### 5.4 Documentation OpenAPI

Le CDC exige les 45 endpoints documentés. **Aucun `openapi.yaml` n'existe
dans le dépôt**, contrairement à ce qu'indique le brief de reconstruction.
À écrire intégralement.

### 5.5 Divers

- Retirer le job CI `backend-tests` le jour où l'ancien back-end sort du
  dépôt.
- Passer `actions/checkout` et `actions/setup-node` en `@v5` (avertissements
  de dépréciation Node 20).
- Aligner `APP_URL` dans `.env.example` sur le port réellement utilisé.
- Supprimer `marketcraft-frontend/src/pages/BuyerStatsPage.jsx`, devenu
  orphelin.

---

## 6. Pièges rencontrés, à ne pas refaire

**Windows et PowerShell.** `curl` est un alias d'`Invoke-WebRequest` ;
utiliser `curl.exe`. La redirection `>` écrit en UTF-16 ; préférer
`curl.exe -o`.

**`Stop-Process -Name php` tue aussi le serveur Laravel.** Garder trois
terminaux distincts : serveur, front, commandes.

**Un `glob()` sur un répertoire partagé entre tests et données réelles est
dangereux.** La suite de tests a effacé les images du catalogue une fois :
les fichiers de test portaient le même préfixe `img_`. Les tests écrivent
désormais dans `public/uploads-test`.

**MySQL erreur #1442.** Un trigger ne peut pas modifier une table que la
requête appelante lit déjà. Toute insertion SQL dans `avis` doit résoudre
ses identifiants dans une table temporaire au préalable — voir la migration
`004`.

**GitHub bloque les pushes contenant des secrets, mais seulement ceux qu'il
reconnaît.** Trois clés API traînaient dans l'historique ; une seule a été
détectée. L'historique a été réécrit avec `git filter-repo` sur les commits
non poussés. **Les clés Groq et Mistral restent à révoquer.**

**`UploadedFile::fake()->image()` exige l'extension GD**, et le type MIME
qu'elle produit vient du nom de fichier, pas du contenu — un test écrit avec
elle ne prouve rien sur la détection de type.

---

## 7. Commandes utiles

```powershell
# Suite de tests
php artisan test
php artisan test --filter=NomDuTest

# Build front avec les avertissements traités en erreurs, comme la CI
$env:CI="true"; npm run build; $env:CI=""

# Diagnostic et réparation des données
php artisan db:reparer --dry-run --ancien-hote=http://localhost:8001

# Vérifier que le serveur répond
curl.exe -s http://localhost:8000/api/health
```

---

## 8. Documents liés

| Fichier | Contenu |
|---|---|
| `marketcraft-api/README.md` | Démarrage, architecture, sécurité, module IA |
| `marketcraft-api/docs/ECARTS-CONTRAT.md` | Écarts au brief, bugs non reproduits, périmètre CDC |
| `docs/19_brief_reconstruction_backend.md` | Brief d'origine |
| `docs/18_conformite_cahier_des_charges.md` | Audit de conformité |
