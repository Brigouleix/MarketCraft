# Brief — Reconstruction du back-end MarketCraft sous Laravel

> **Document à transmettre tel quel en ouverture d'une nouvelle conversation.**
> Il contient tout le contexte nécessaire : contraintes académiques, contrat
> d'API à respecter au caractère près, schéma de données et plan de bascule.

---

## 0. Mission

Reconstruire depuis zéro le back-end de MarketCraft, une marketplace d'artisanat, sous **Laravel 11**, en respectant **exactement** le contrat d'API décrit plus bas.

Le front-end React et l'application mobile React Native **ne seront pas modifiés**. Ils consomment déjà cette API. Toute divergence, même minime — un champ renommé, une enveloppe de réponse différente, un code HTTP changé — casse des pages en silence, sans erreur visible.

**C'est la contrainte numéro un de ce projet.** Le back-end actuel est un MVC PHP écrit à la main ; il fonctionne et sert de référence, mais il ne satisfait pas les exigences du cahier des charges (aucun framework, aucun ORM).

---

## 1. Contraintes du cahier des charges

Projet de Master 1 CDA, 4ᵉ année, équipe de 2. Exigences applicables au back-end :

| Exigence | Détail |
|---|---|
| Framework | Laravel (imposé parmi Laravel/Symfony pour PHP) |
| ORM | Eloquent |
| Architecture | MVC ou couches Repository + Service + Controller |
| API | RESTful, codes HTTP appropriés |
| Documentation | Swagger / OpenAPI couvrant **tous** les endpoints |
| Base | Relationnelle — MySQL/MariaDB existant |
| Config | Variables d'environnement en `.env`, jamais commité |
| CI/CD | Pipeline automatisé (GitHub Actions déjà en place) |

Exigences de sécurité, toutes obligatoires :

| Menace | Mesure attendue | Critère de validation |
|---|---|---|
| Broken Access Control | Contrôle de rôle sur chaque route | Accès non autorisé refusé |
| Cryptographic Failures | bcrypt ou argon2, SHA-1/MD5 interdits | Aucun mot de passe lisible en base |
| Injection SQL | Eloquent / requêtes préparées | Test SQLMap sans vulnérabilité |
| Security Misconfiguration | `.env`, HTTPS, en-têtes de sécurité | `.env` jamais commité |
| Auth Failures | Complexité MDP, **verrouillage après 5 tentatives**, CAPTCHA | Brute-force bloqué |
| Logging Failures | **Journal d'activité** : connexions, erreurs, actions admin | Logs consultables en back-office |

---

## 2. Contrat d'API — à respecter à l'identique

### 2.1 Préfixe et enveloppes

Toutes les routes sont préfixées par `/api`. Le serveur retire ce préfixe avant routage.

**Trois formes de réponse, jamais d'autres.**

Succès simple :

```json
{ "success": true, "message": "OK", "data": { } }
```

`data` est **omis** quand il n'y a rien à renvoyer. `message` est toujours présent.

Succès paginé :

```json
{
  "success": true,
  "data": [ ],
  "pagination": { "total": 42, "page": 1, "limit": 12, "total_pages": 4 }
}
```

Erreur :

```json
{ "success": false, "error": "Message lisible", "details": { } }
```

`details` n'apparaît qu'en cas d'erreur de validation ou de rejet détaillé.

> **Piège vécu** : le front déballe `data.data`. Une réponse renvoyée sans son enveloppe laisse l'interface vide sans aucune erreur en console. Ne jamais renvoyer une ressource nue.

Encodage JSON : `JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES`. Les accents et les slashs d'URL ne doivent pas être échappés.

### 2.2 Authentification

JWT via en-tête `Authorization: Bearer <token>`. Payload attendu, le front lit `sub` et `role` :

```json
{ "iat": 0, "exp": 0, "sub": "12", "email": "a@b.fr", "role": "client" }
```

`sub` est une **chaîne**, pas un entier. Access token : 24 h. Refresh token : 7 jours.

Rôles : `client`, `vendeur`, `admin`.

### 2.3 Les 45 endpoints

`[A]` = authentification requise. Les contrôles de propriétaire et de rôle sont indiqués.

**Authentification**

| Méthode | Route | Notes |
|---|---|---|
| POST | `/auth/register` | |
| POST | `/auth/login` | verrouillage après 5 échecs, 15 min |
| POST | `/auth/logout` | `[A]` |
| POST | `/auth/refresh` | |
| GET | `/auth/me` | `[A]` |
| PUT | `/auth/me` | `[A]` |
| DELETE | `/auth/me` | `[A]` |

**Produits**

| Méthode | Route | Notes |
|---|---|---|
| GET | `/products` | paginé, filtres ci-dessous |
| GET | `/products/:id` | |
| POST | `/products` | `[A]` vendeur/admin |
| PUT | `/products/:id` | `[A]` propriétaire/admin |
| DELETE | `/products/:id` | `[A]` propriétaire/admin |

Paramètres de `GET /products`, tous optionnels :
`page`, `per_page` (ou `limit`), `search`, `categorie`, `materiau`, `boutique_id` (ou `boutique`), `prix_min`, `prix_max`, `note_min`, `tri`, `sort`, `order`.

- `categorie` et `materiau` acceptent une **liste de slugs ou d'ids séparés par des virgules**.
- À l'intérieur d'un même paramètre, les valeurs se combinent en **OU**.
- Entre `categorie` et `materiau`, la combinaison est un **ET**. « Céramique » + « Argile » renvoie les céramiques *en* argile, pas leur union.
- `tri` accepte `prix_asc`, `prix_desc`, `recent`, `populaire`.

**Boutiques**

| Méthode | Route | Notes |
|---|---|---|
| GET | `/boutiques` | paginé |
| GET | `/boutiques/me` | `[A]` |
| GET | `/boutiques/:id` | |
| POST | `/boutiques` | `[A]` — une seule boutique par vendeur |
| PUT | `/boutiques/:id` | `[A]` propriétaire/admin |
| DELETE | `/boutiques/:id` | `[A]` propriétaire/admin |

**Commandes**

| Méthode | Route | Notes |
|---|---|---|
| GET | `/orders` | `[A]` |
| GET | `/orders/:id` | `[A]` |
| POST | `/orders` | `[A]` |
| PUT | `/orders/:id/status` | `[A]` vendeur/admin |
| DELETE | `/orders/:id` | `[A]` |

**Avis**

| Méthode | Route | Notes |
|---|---|---|
| GET | `/products/:id/avis` | |
| POST | `/products/:id/avis` | `[A]` |
| DELETE | `/avis/:id` | `[A]` propriétaire/admin |

**Catégories, recherche, tableaux de bord, upload**

| Méthode | Route | Notes |
|---|---|---|
| GET | `/categories` | public — renvoie `parent_id`, indispensable au regroupement |
| POST | `/search/ai` | corps `{ "query": "..." }` |
| GET | `/search` | recherche simple par mots-clés |
| GET | `/dashboard/stats` | `[A]` vendeur/admin |
| GET | `/dashboard/acheteur` | `[A]` |
| POST | `/upload/image` | `[A]` — champ `image`, renvoie `{ url, filename }` |
| POST | `/upload/images` | `[A]` — champ `images[]`, max 5, renvoie `{ urls, rejets }` |
| GET | `/health` | sonde publique |

**Administration** — toutes en `[A]` avec rôle `admin` vérifié dans le contrôleur

| Méthode | Route |
|---|---|
| GET | `/admin/stats` |
| GET | `/admin/users` |
| PUT | `/admin/users/:id/toggle` |
| GET | `/admin/boutiques` |
| PUT | `/admin/boutiques/:id/toggle` |
| GET | `/admin/avis` |
| DELETE | `/admin/avis/:id` |
| GET | `/admin/categories` |
| POST | `/admin/categories` |
| PUT | `/admin/categories/:id` |
| DELETE | `/admin/categories/:id` |

`GET /admin/categories` renvoie une forme particulière, à conserver :

```json
{ "success": true, "data": { "categories": [ ], "racines": [ ] } }
```

Chaque catégorie porte `nb_produits` et `parent_nom`.

`DELETE /admin/categories/:id` répond **409** avec `details: { principale, liaisons }` si des produits sont rattachés. Le client confirme alors via `?force=1`.

### 2.4 Règles de sérialisation à ne pas casser

- **`produits.images`** : colonne JSON contenant un tableau d'**URL absolues**. Les endpoints de liste et de détail la renvoient **décodée** en tableau ; `/search/ai` la renvoie encore sous forme de chaîne JSON. Le front gère les deux, mais tout autre format casse l'affichage.
- Les URL d'images sont construites à partir de `APP_URL` + `/uploads/<fichier>`. Stocker une URL absolue est un choix contestable, mais le front en dépend en l'état.
- **`prix`** est renvoyé en **chaîne** (`"225.00"`), pas en nombre.
- Les booléens SQL (`est_actif`, `est_fait_main`, `est_active`) sortent en `0`/`1`.
- Les dates sont au format `Y-m-d H:i:s`.
- Un produit expose `boutique: { id, nom }` imbriqué, plus `categories: [{ id, nom }]` et `categorie_nom`.

---

## 3. Schéma de données

Base MySQL/MariaDB existante, à conserver — le script SQL est un livrable et les données de démonstration doivent rester exploitables. Créer les migrations Laravel correspondantes.

| Table | Colonnes |
|---|---|
| `utilisateurs` | id, nom, prenom, email, password_hash, role, avatar_url, telephone, est_actif, created_at, updated_at |
| `adresses_livraison` | id, utilisateur_id, nom_complet, ligne1, ligne2, ville, code_postal, pays, est_principale, created_at |
| `boutiques` | id, vendeur_id, nom, slug, description, logo_url, banniere_url, est_active, note_moyenne, created_at, updated_at |
| `categories` | id, parent_id, nom, slug, description, image_url, ordre, created_at |
| `produits` | id, boutique_id, categorie_id, nom, slug, description, prix, stock, note_moyenne, nombre_avis, images, tags, est_actif, est_fait_main, created_at, updated_at |
| `produit_categorie` | produit_id, categorie_id |
| `commandes` | id, utilisateur_id, adresse_livraison_id, statut, montant_total, frais_livraison, note, numero_suivi, date_livraison, created_at, updated_at |
| `lignes_commande` | id, commande_id, produit_id, quantite, prix_unitaire, nom_produit |
| `paiements` | id, commande_id, methode, statut, montant, transaction_id, date_liberation, payload, created_at, updated_at |
| `avis` | id, produit_id, utilisateur_id, note, titre, commentaire, est_verifie, created_at |

Énumérations :

- `utilisateurs.role` : `client`, `vendeur`, `admin`
- `commandes.statut` : `en_attente`, `confirmee`, `en_preparation`, `expediee`, `livree`, `annulee`
- `paiements.methode` : `carte`, `virement`, `paypal`, `cheque`
- `paiements.statut` : `en_attente`, `valide`, `refuse`, `rembourse`, `libere`

Points structurants :

- La colonne de mot de passe s'appelle **`password_hash`**, pas `password`. Le modèle User Laravel doit surcharger `getAuthPassword()`.
- Les **noms de tables et de colonnes sont en français** et au pluriel irrégulier. Déclarer `$table` et `$primaryKey` explicitement, désactiver les conventions Eloquent qui ne s'appliquent pas.
- `categories.parent_id` est auto-référent : deux racines, « Objet » et « Matériau », regroupent leurs sous-catégories. Le front s'appuie dessus pour ses deux onglets de filtres.
- Des **triggers SQL** recalculent `produits.note_moyenne`, `produits.nombre_avis` et `boutiques.note_moyenne`. Les conserver, ou reproduire le calcul côté application — mais pas les deux.
- Contrainte métier : **une seule boutique par vendeur** (clé unique sur `vendeur_id`).

---

## 4. Module IA — option C, recommandation personnalisée

Le cahier des charges impose de choisir **un seul module parmi trois** : chatbot SAV, génération de fiches produits, ou recommandation personnalisée. **Retenir l'option C.**

L'implémentation actuelle est une *recherche* en langage naturel — elle ne correspond à aucune des trois options et doit être remplacée ou complétée.

Attendu par le CDC : « Sur la base de la fiche produit et du contenu du panier, l'IA suggère des articles complémentaires ou similaires pertinents. Les recommandations changent selon le produit consulté. L'algorithme de sélection doit être documenté. »

Architecture à reprendre de l'existant, qui fonctionne :

- Fournisseur **Mistral**, endpoint au format OpenAI : `https://api.mistral.ai/v1/chat/completions`, modèle `mistral-small-latest`
- Variables `AI_API_KEY`, `AI_MODEL`, `AI_API_URL` — génériques, pour pouvoir changer de fournisseur sans toucher au code
- **`CURLOPT_USERAGENT` obligatoire** : sans User-Agent, Cloudflare renvoie un 403 avant toute vérification de clé
- `response_format: {"type":"json_object"}` pour une sortie JSON stricte
- **Repli systématique** : si l'IA échoue — clé absente, quota, réseau, JSON invalide — retomber sur une sélection par similarité de catégorie et de gamme de prix. Le CDC l'exige explicitement pour l'option A et c'est de bonne pratique ici.
- Exposer un indicateur `ia_active` dans la réponse, pour distinguer une vraie recommandation d'un repli. Le front l'affiche sous forme de badge.
- Journaliser chaque échec avec son motif, jamais de `return null` muet.

Endpoints suggérés : `GET /products/:id/recommendations` et `POST /cart/recommendations`.

---

## 5. Livrables attendus du nouveau back-end

1. **Application Laravel 11** avec Eloquent, respectant le contrat ci-dessus à l'identique
2. **Migrations et seeders** reproduisant le schéma et le jeu de données de démonstration
3. **Documentation OpenAPI** couvrant les 45 endpoints — un `openapi.yaml` de référence existe déjà dans le projet et peut servir de base
4. **Verrouillage de compte** : 5 tentatives, blocage 15 minutes
5. **CAPTCHA** sur le formulaire de connexion
6. **Journal d'activité** : table dédiée, enregistrement des connexions, erreurs et actions admin, plus un endpoint `GET /admin/logs` paginé — le back-office devra afficher cet écran
7. **Tests** : unitaires et d'intégration, au minimum sur l'authentification, les rôles et le CRUD produits
8. **En-têtes de sécurité** : `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`
9. **CORS** configuré, `ALLOWED_ORIGINS` en variable d'environnement

---

## 6. Plan de bascule

Ne **pas** remplacer le back-end existant tant que le nouveau n'est pas validé.

1. Développer le projet Laravel dans un dossier distinct, par exemple `marketcraft-api/`
2. Le faire écouter sur un **autre port**, par exemple `8001`
3. Basculer le front en changeant la seule variable `REACT_APP_API_URL` — actuellement absente, le front tombe sur le défaut `http://localhost:8000/api`
4. Dérouler le parcours complet : inscription, connexion, création de boutique, création de produit avec upload d'images, filtres par catégorie et matériau, panier, commande, avis, back-office admin, recommandations IA
5. Comparer les réponses des deux back-ends endpoint par endpoint avant de retirer l'ancien
6. Travailler sur une **branche dédiée**, l'ancien back-end restant démontrable jusqu'au dernier moment

---

## 7. Contexte de risque à garder en tête

La soutenance comporte **8 minutes de démonstration live** : inscription → commande → facture, plus l'interface mobile. Une application non fonctionnelle le jour J coûte davantage qu'un écart d'architecture assumé par écrit.

**Priorité absolue : que le parcours utilisateur complet fonctionne.** Si le temps manque, il vaut mieux un Laravel partiel mais fonctionnel sur les routes critiques, l'ancien back-end assurant le reste, qu'une migration complète mais instable.
