# Écarts entre le brief et le contrat réellement servi

> Relevé fait en lisant le code de l'ancien back-end (`marketcraft-backend/`)
> et le client React (`marketcraft-frontend/src`), pas le brief.
> Chaque point ci-dessous est une chose que le brief ne dit pas, ou dit
> autrement que ce que le code fait.

---

## 1. Les trois enveloppes sont en réalité cinq

Le brief annonce trois formes de réponse « et jamais d'autres ». Le code en
sert cinq.

### 1.1 Les routes d'authentification ne sont pas enveloppées

`POST /auth/register`, `POST /auth/login` et `POST /auth/refresh` renvoient
les jetons **au premier niveau**, hors de `data` :

```json
{
  "success": true,
  "message": "Login successful.",
  "access_token": "eyJ...",
  "refresh_token": "eyJ...",
  "user": { }
}
```

Ce n'est pas un oubli de documentation : l'intercepteur axios du front lit
`data.access_token` directement (`src/services/api.js`, ligne 66). Envelopper
proprement ces trois réponses casse la connexion **et** le rafraîchissement
silencieux du jeton — sans erreur en console, l'utilisateur est juste
redirigé vers `/login` en boucle.

`POST /auth/refresh` n'a en outre **pas de clé `message`**, contrairement aux
deux autres.

**Reproduit à l'identique** dans `AuthController`, via `ApiResponse::raw()`.

### 1.2 `GET /products/:id/avis` ajoute une clé `stats`

```json
{
  "success": true,
  "data": [ ],
  "stats": { "moyenne": 4.5, "total": 2, "repartition": { "5": 1, "4": 1, "3": 0, "2": 0, "1": 0 } },
  "pagination": { }
}
```

Le front s'en sert pour l'histogramme des étoiles.
**Reproduit** — `ApiResponse::paginated()` accepte un tableau `$extra`.

### 1.3 `GET /health` a sa propre forme

`{ success, service, version, time }` — ni `data`, ni `message`.
**Reproduit**, avec un champ `database` en plus.

---

## 2. Le front appelle trois routes qui n'existent pas

Relevé dans `src/services/api.js` :

| Appel du front | État côté back | Conséquence |
|---|---|---|
| `GET /products/:id/similar` | **absente** | `ProductDetailPage` l'appelle vraiment (ligne 145). Le bloc « produits similaires » est vide depuis toujours. |
| `GET /boutiques/:id/products` | absente | `boutiquesAPI.getProducts` n'est appelée nulle part — inoffensif. |
| `DELETE /products/:pid/avis/:aid` | absente (la vraie route est `DELETE /avis/:id`) | `avisAPI.delete` n'est appelée nulle part — inoffensif. |

`/products/:id/similar` est le seul point qui compte : c'est un 404 silencieux
sur une page visible en démonstration.

**Décision retenue** : le module IA est branché sur `/similar` **et** sur
`/recommendations`, les deux servant la même réponse. Le React n'est pas
modifié et le brief est respecté.

---

## 3. `note_moyenne` avait deux sources de vérité

Les triggers SQL alimentaient `produits.note_moyenne` et
`produits.nombre_avis`, mais **toutes les requêtes de lecture les écrasaient**
par un `AVG()` / `COUNT()` calculé à la volée (`Product::findAll`, ligne 68).
Deux mécanismes pour la même valeur, dont un seul était visible dans l'API.

**Décision retenue** : les triggers font foi, l'application lit les colonnes.

Conséquence sur le JSON, à vérifier à l'œil lors de la bascule :

| | Ancien back | Nouveau back |
|---|---|---|
| `note_moyenne` | `"4.5000"` (résultat d'`AVG`) | `"4.50"` (colonne `DECIMAL(3,2)`) |
| `nb_avis` | calculé | alias de `nombre_avis`, valeur identique |

Le front affiche des étoiles à partir d'un `parseFloat`, donc l'écart de
précision est sans effet. `nb_avis` **et** `nombre_avis` sont tous deux
exposés, comme avant.

---

## 4. Champs du produit que le brief ne mentionne pas

Le brief cite `boutique`, `categories` et `categorie_nom`. Le code en renvoie
davantage, et le front en lit une partie :

| Champ | Présence |
|---|---|
| `boutique_nom` | liste + détail (doublon à plat de `boutique.nom`) |
| `categorie` | chaîne, doublon de `categorie_nom` |
| `nb_avis` | liste + détail |
| `nombre_avis` | liste + détail |
| `vendeur_id` | **détail uniquement** |
| `tags` | tableau JSON décodé, souvent `null` |

Tous **reproduits**. `ProduitResource` les construit explicitement plutôt que
de sérialiser le modèle : un champ ajouté en base n'apparaît pas dans l'API
par accident.

---

## 5. Bugs de l'ancien back-end, non reproduits

Reproduire à l'identique s'arrête là où le comportement est fautif.

### 5.1 Les paramètres d'URL étaient passés dans `htmlspecialchars()`

`Controller::getParam()` appliquait `htmlspecialchars()` à **tout** paramètre
de requête avant usage. Chercher `d'art` produisait donc un `LIKE
'%d&#039;art%'` et ne remontait rien. L'échappement HTML sert à l'affichage,
pas à la lecture d'un paramètre.

**Non reproduit.** La protection contre l'injection vient des requêtes
préparées d'Eloquent ; l'échappement à l'affichage est le travail de React.

### 5.2 « Une boutique par vendeur » n'était pas vérifiée

Seule la clé unique en base l'empêchait, donc la seconde tentative renvoyait
une erreur SQL brute en 500. **Corrigé** : contrôle explicite, 409 avec un
message lisible, clé unique conservée comme filet.

### 5.3 La promotion `client` → `vendeur` était commentée, jamais faite

Un client qui créait une boutique restait client, et ne pouvait donc pas y
déposer de produit — `POST /products` exige le rôle vendeur. **Implémenté.**

### 5.4 L'annulation d'une commande ne remettait pas le stock

`Order::cancel()` changeait le statut, sans plus. Chaque annulation retirait
définitivement les articles de la vente. **Corrigé**, dans une transaction.

### 5.5 Les messages d'exception partaient au client

Plusieurs `catch` renvoyaient `$e->getMessage()` en clair dans la réponse
(`'Failed to create product: ' . $e->getMessage()`), exposant requêtes SQL et
chemins de fichiers. **Non reproduit** : le détail n'est visible que si
`APP_DEBUG=true`.

### 5.6 Le jeton de rafraîchissement était accepté comme jeton d'accès

`Auth::validateToken()` ne regardait pas le champ `type`. Un refresh token
présenté en `Bearer` ouvrait donc les routes protégées — avec 7 jours de
validité au lieu de 24 h. **Corrigé** dans `JwtAuthenticate`.

### 5.7 Un compte désactivé gardait l'accès jusqu'à expiration du jeton

Le JWT était la seule source de vérité. Désactiver un compte en back-office
ne le déconnectait pas. **Corrigé** : `est_actif` est revérifié à chaque
requête.

---

## 5.8 Un vendeur ne peut pas lire les commandes qu'il doit traiter

`PUT /orders/:id/status` est ouvert aux rôles `vendeur` et `admin`, mais
`GET /orders/:id` reste réservé au propriétaire de la commande et à
l'administrateur. Un vendeur peut donc faire passer une commande à
« expédiée » sans jamais pouvoir en consulter le contenu — ni savoir quels
articles préparer.

Le comportement est **hérité tel quel** de l'ancien back-end, où le même
cloisonnement existe. Il est reproduit pour ne pas élargir les droits sans
décision explicite.

À trancher avant de porter `GET /dashboard/stats`, qui suppose qu'un vendeur
accède aux commandes contenant ses produits. Deux options :

- autoriser `GET /orders` et `GET /orders/:id` à un vendeur **pour les
  commandes contenant au moins un de ses produits**, en filtrant par
  `lignes_commande.produit_id` → `produits.boutique_id` → `boutiques.vendeur_id` ;
- laisser le cloisonnement et exposer les commandes du vendeur uniquement à
  travers un endpoint dédié au tableau de bord.

La première est plus simple et ne casse rien côté front : elle transforme un
403 en 200 sur des routes que le vendeur n'utilise pas aujourd'hui.

---

## 6. Point de sécurité à traiter en dehors du code

`marketcraft-backend/.env.example` contient une **clé API Mistral réelle** —
et ce fichier est commité, `.gitignore` n'excluant que `.env`.

```
AI_API_KEY=
```

À révoquer sur console.mistral.ai. La retirer du fichier ne suffit pas :
elle reste lisible dans l'historique Git.

Le nouveau `.env.example` ne contient aucune valeur secrète.

---

## 7. Ce qui n'est pas encore porté

Le chemin critique est couvert. Restent à écrire, dans l'ordre suggéré :

- `GET /dashboard/stats` et `GET /dashboard/acheteur`
- `POST /upload/image` et `POST /upload/images`
- `GET /search` et `POST /search/ai`
- les onze routes `/admin/*`, plus `GET /admin/logs` (table déjà créée)
- le fichier `openapi.yaml` — **aucun n'existe dans le dépôt actuel**,
  contrairement à ce qu'indique le brief au point 5.3

Tant qu'elles ne sont pas portées, l'ancien back-end reste la référence pour
ces routes : c'est exactement le scénario de repli prévu au point 7 du brief.
