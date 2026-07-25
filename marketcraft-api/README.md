# MarketCraft API — back-end Laravel 11

Reconstruction du back-end de MarketCraft sous Laravel 11 + Eloquent, en
respectant le contrat d'API servi par l'ancien MVC PHP. Le front React et
l'application mobile ne sont pas modifiés.

> **À lire avant toute chose :** [`docs/ECARTS-CONTRAT.md`](docs/ECARTS-CONTRAT.md)
> recense les endroits où le contrat réel diffère du brief, et les bugs de
> l'ancien back-end qui n'ont volontairement pas été reproduits.

---

## Démarrage

```bash
cd marketcraft-api

composer install

cp .env.example .env
php artisan key:generate
php artisan jwt:secret          # commande fournie, écrit JWT_SECRET dans .env
```

Renseigner la base dans `.env`, puis :

```bash
php artisan migrate --seed

# Port 8001 : l'ancien back-end reste joignable sur 8000 jusqu'à la bascule
php artisan serve --port=8001
```

Vérification : `curl http://localhost:8001/api/health`

### Comptes de démonstration

Mot de passe commun : **`Password123`**

| Rôle | Adresse |
|---|---|
| admin | `marie.dupont@example.com` |
| vendeur | `paul.martin@example.com` |
| vendeur | `sophie.bernard@example.com` |
| client | `jules.lemoine@example.com` |
| client | `camille.petit@example.com` |

Ce mot de passe respecte la politique de complexité appliquée à
l'inscription (8 caractères, majuscule, minuscule, chiffre) — utile pour
démontrer la règle sans avoir à la contourner.

### Bascule du front

Le front tombe par défaut sur `http://localhost:8000/api`. Pour le pointer
ici, créer `marketcraft-frontend/.env.local` :

```
REACT_APP_API_URL=http://localhost:8001/api
```

Une seule variable à changer, et retour arrière immédiat en la supprimant.

---

## Architecture

```
app/
├── Console/Commands/     jwt:secret
├── Http/
│   ├── Controllers/      un contrôleur par ressource
│   ├── Middleware/        CORS, en-têtes de sécurité, JWT, rôles
│   └── Resources/         sérialisation explicite, champ par champ
├── Models/                Eloquent, tables et colonnes en français
├── Services/              journal, verrouillage, captcha, filtres, IA
└── Support/               ApiResponse, Jwt, Slug
```

**Trois partis pris structurants.**

*Une seule sortie JSON.* Tout passe par `App\Support\ApiResponse`. Une
enveloppe unique, des drapeaux d'encodage identiques partout
(`JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES`), et les exceptions
elles-mêmes y sont converties dans `bootstrap/app.php`. Le front déballe
`data` sans vérifier : une ressource renvoyée nue vide l'interface sans la
moindre erreur en console.

*Sérialisation explicite.* Les classes de `Http/Resources` construisent le
tableau champ par champ au lieu de sérialiser le modèle. Ajouter une colonne
en base ne la publie donc pas par accident, et les alias historiques
(`boutique_nom`, `categorie`, `nb_avis`) sont visibles à la lecture.

*Contrôle de rôle dans les routes.* `routes/api.php` porte les middlewares
`jwt` et `role:`. Une route ajoutée sans protection saute aux yeux à la
relecture du fichier ; un contrôle oublié au fond d'une méthode, non.

### Conventions de base héritées

La base vient de l'ancien projet : tables et colonnes en français, pluriels
irréguliers. Les conventions d'Eloquent ne s'y appliquent pas.

- `$table` déclaré explicitement partout (`Avis` deviendrait sinon `avis`
  déduit de `Avi`).
- La colonne de mot de passe s'appelle `password_hash` : `User` surcharge
  `getAuthPassword()` et `getAuthPasswordName()`.
- Les tables sans `updated_at` déclarent `const UPDATED_AT = null` ;
  `lignes_commande`, sans horodatage du tout, déclare `$timestamps = false`.
- `serializeDate()` force `Y-m-d H:i:s` — Eloquent sérialiserait en ISO 8601.
- `prix` et `note_moyenne` sont castés en `decimal:2`, donc renvoyés en
  **chaîne**. Les booléens SQL ne sont pas castés : ils sortent en `0`/`1`.

---

## Sécurité

| Menace OWASP | Mesure | Où |
|---|---|---|
| Broken Access Control | middlewares `jwt` + `role:`, contrôle de propriété | `routes/api.php`, contrôleurs |
| Cryptographic Failures | bcrypt coût 12, rehash à la connexion | `config/hashing.php` |
| Injection | Eloquent, colonnes de tri en liste blanche, jokers `LIKE` échappés | `ProduitQuery` |
| Security Misconfiguration | `.env` jamais commité, en-têtes de sécurité, CORS par variable | `SecurityHeaders`, `HandleCors` |
| Auth Failures | verrouillage 5 échecs / 15 min, captcha dès le 3ᵉ | `LoginThrottle`, `CaptchaService` |
| Logging Failures | table `journal_activite`, secrets masqués | `ActivityLogger` |

**Verrouillage.** Compté par email sur une fenêtre glissante, dans la table
`tentatives_connexion` — pas en cache : la trace survit à un redémarrage et
reste opposable. Une connexion réussie purge les échecs. Un compteur par IP,
au seuil quatre fois plus large, couvre le balayage d'adresses sans que
plusieurs utilisateurs derrière un même NAT se bloquent mutuellement.

**Captcha.** Défi arithmétique signé en HMAC, sans état serveur ni appel
réseau. La soutenance comporte huit minutes de démonstration live : un appel
sortant vers reCAPTCHA y est un point de défaillance qui ne se rattrape pas.
Il se déclenche au 3ᵉ échec, donc jamais sur le parcours nominal. Sa fonction
est de ralentir le remplissage automatique ; c'est le verrouillage qui bloque
réellement le brute-force.

**Journal.** Les clés sensibles (`password`, `token`, `authorization`…) sont
retirées récursivement du contexte avant écriture. Une panne d'écriture du
journal ne fait jamais échouer la requête métier.

---

## Module IA — recommandation personnalisée (option C)

`GET /products/{id}/similar` · `GET /products/{id}/recommendations` ·
`POST /cart/recommendations`

Les deux premières servent la même réponse : le front appelle déjà `/similar`,
le brief documente `/recommendations`.

**L'algorithme de sélection, à présenter en soutenance.**

1. **Présélection locale, toujours exécutée.** Le catalogue entier n'est
   jamais soumis au modèle — coûteux, lent, et la fenêtre de contexte le
   tronquerait. Un score classe les candidats :

   | Critère | Points |
   |---|---|
   | Catégorie principale identique | +40 |
   | Par catégorie secondaire partagée | +25 |
   | Même boutique | +15 |
   | Écart de prix < 30 % | +20 |
   | Écart de prix < 60 % | +10 |
   | Note moyenne ≥ 4 | +5 |
   | Rupture de stock | −50 |

   Les 15 meilleurs partent au modèle.

2. **Classement par l'IA.** Le modèle reçoit ces 15 fiches réduites et rend
   un tableau ordonné d'identifiants, avec un motif par produit. Il ne crée
   rien : il trie et justifie. Tout identifiant absent de la présélection est
   écarté — le modèle ne peut donc pas inventer de produit.

3. **Repli systématique.** Clé absente, quota, réseau, JSON invalide,
   identifiant inconnu : on renvoie les N premiers de la présélection.
   `ia_active` passe à `false`, le front l'affiche en badge, et le motif de
   l'échec part dans `storage/logs/ia.log`. Jamais de retour muet.

Fournisseur au format OpenAI, configuré par `AI_API_KEY`, `AI_MODEL`,
`AI_API_URL` — changer de fournisseur ne touche pas au code.
`User-Agent` est obligatoire : sans lui, Cloudflare renvoie 403 avant même
de lire la clé.

---

## Tests

```bash
php artisan test
```

SQLite en mémoire : ni base à provisionner, ni état résiduel. Les triggers et
la procédure stockée sont propres à MySQL et leur migration les ignore sous
SQLite.

Couvert : enveloppes de réponse, JWT, inscription et connexion, verrouillage,
rôles et propriété, CRUD produits, croisement ET/OU des filtres, parcours de
commande, stock, avis.

---

## Composants SGBD (CP8)

Portés dans `2026_01_01_000013_create_composants_sgbd.php` :

- 3 triggers sur `avis` → `produits.note_moyenne`, `produits.nombre_avis`
- 1 trigger sur `produits` → `boutiques.note_moyenne`
- `fn_stock_suffisant()`
- `sp_liberer_paiements_echus()` — règle du J+14
- `evt_liberation_paiements_quotidien` (nécessite `SET GLOBAL event_scheduler = ON`)

Ces triggers sont **la seule** source de vérité pour la note moyenne.
L'application les lit, elle ne recalcule jamais.

---

## Reste à porter

Dashboards, upload, recherche, routes `/admin/*` + `GET /admin/logs`, et
`openapi.yaml`. Voir le point 7 de `docs/ECARTS-CONTRAT.md`.

Tant qu'elles ne sont pas portées, l'ancien back-end reste la référence pour
ces routes.
