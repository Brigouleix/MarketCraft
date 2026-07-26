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

## 5.8 Un vendeur ne pouvait pas voir les commandes à préparer — corrigé

Dans l'ancien back-end, `GET /orders` filtrait sur `utilisateur_id`. Un
vendeur ne voyait donc que ses propres achats, jamais les commandes
contenant ses produits : l'onglet « Mes commandes » du tableau de bord était
structurellement vide.

Pire, `PUT /orders/:id/status` n'exigeait que le rôle `vendeur`. **N'importe
quel vendeur inscrit pouvait faire passer à « livrée » ou « annulée » la
commande d'une autre boutique**, en incrémentant un identifiant dans l'URL.
Un cas d'école de *Broken Access Control*.

Corrigé sur trois points :

- `GET /orders?scope=ventes` renvoie les commandes contenant au moins un
  produit du vendeur. Le point de vue est un paramètre explicite, pas une
  déduction du rôle : un vendeur est aussi un compte, et sa page « Mon
  compte » ne doit pas se remplir de ses ventes.
- `GET /orders/:id` s'ouvre au vendeur concerné — il doit savoir quoi
  préparer.
- `PUT /orders/:id/status` vérifie désormais que la commande contient un de
  ses produits.

Le principe : **le rôle dit ce qu'on a le droit de faire, jamais sur quoi.**
Les deux contrôles sont distincts, et l'oubli du second est l'erreur la plus
fréquente.

---

## 5.11 Séparation stricte des rôles

Règle métier appliquée après le portage : un compte vendeur vend et n'achète
pas ; un compte acheteur achète et ne vend pas. Le rôle se choisit à
l'inscription et n'évolue plus.

Conséquences sur le code hérité :

- `POST /orders` refuse un compte `vendeur` (403).
- `POST /boutiques` exige le rôle `vendeur`. **La promotion automatique
  `client` → `vendeur` qui existait à la création d'une boutique a été
  retirée** : elle contredisait la séparation, et faisait perdre à
  l'utilisateur la possibilité d'acheter sans qu'il l'ait demandé.
- Côté interface, le panier et le bouton « Ajouter » disparaissent pour un
  vendeur. Ce n'est qu'un confort : le refus qui fait foi est côté serveur,
  l'API restant appelable directement.

À noter pour la démonstration : le parcours complet exige donc **deux
comptes**, un acheteur et un vendeur.

---

## 5.9 Endpoints ajoutés, absents des 45 du contrat

Cinq routes n'existent pas dans l'ancien back-end. Aucune ne modifie une
réponse existante : ce sont des ajouts, donc sans risque de régression sur
le front.

| Route | Statut vis-à-vis du CDC |
|---|---|
| `GET /products/:id/similar` | **Attendue par le front**, jamais implémentée — voir §2 |
| `GET /products/:id/recommendations` | Module IA option C, nom du brief |
| `POST /cart/recommendations` | Module IA option C, nom du brief |
| `GET /me/recommendations` | Module IA option C, extension à l'historique d'achat |
| `GET /auth/captcha` | Support du CAPTCHA exigé par le CDC |
| `GET /dashboard/concurrence` | **Hors périmètre — voir ci-dessous** |

---

## 5.10 L'analyse concurrentielle est hors périmètre, et c'est assumé

Le cahier des charges impose **un seul** module IA, à choisir parmi trois :
chatbot SAV, génération de fiches produits, ou recommandation personnalisée.
C'est l'option C qui a été retenue.

`GET /dashboard/concurrence` ne relève d'aucune des trois. C'est un ajout
délibéré, pas une confusion sur le périmètre.

**À dire ainsi en soutenance :** le module imposé est la recommandation
personnalisée, servie par `RecommandationService` sur quatre routes.
L'analyse concurrentielle est un dépassement, construit sur la même
architecture de repli. Présentée comme un bonus documenté, elle valorise ;
présentée comme le module imposé, elle exposerait à la question « et les
deux autres options, vous les avez écartées pourquoi ? ».

La mention figure à trois endroits du code, pour qu'elle ne se perde pas :
en-tête de `AnalyseConcurrentielleService`, en-tête du contrôleur, et
commentaire sur l'onglet du tableau de bord React.

### Le principe qui structure ce service

**Les chiffres sont calculés, jamais générés.** Le modèle de langage ne
reçoit que des statistiques déjà établies — médiane, min, max, écart en
pourcentage — et se contente de les commenter. Un prix médian inventé et
affiché à un vendeur qui s'en servirait pour fixer ses tarifs serait pire
qu'une absence d'analyse.

Deux garde-fous appliquent cette règle :

- la consigne système interdit explicitement de citer un montant absent des
  données fournies ;
- tout identifiant de produit renvoyé par le modèle et absent de la
  boutique analysée est écarté avant affichage.

Le positionnement se mesure par rapport à la **médiane** et non à la
moyenne : une pièce d'exception à 900 € ferait passer tout le reste du
catalogue pour bon marché. Le seuil d'alignement est fixé à 15 % d'écart.

Les tests `ConcurrenceTest` vérifient cette arithmétique sans jamais
solliciter l'IA — `Http::preventStrayRequests()` interdit tout appel sortant
pendant la suite.

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
