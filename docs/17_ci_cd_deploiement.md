# CI/CD et déploiement — MarketCraft (CP14 / CP15)

Ce document décrit la chaîne d'intégration continue (tests automatisés à chaque changement) et la procédure de déploiement du frontend sur **Vercel**.

---

## 1. Intégration continue — GitHub Actions

> Le dépôt étant hébergé sur **GitHub** (`Brigouleix/MarketCraft_3emeDev`), la CI utilise **GitHub Actions** — l'équivalent direct de GitLab CI (même principe : un fichier YAML versionné décrit les jobs exécutés sur des runners à chaque push/PR).

Le workflow [` .github/workflows/ci.yml`](../.github/workflows/ci.yml) se déclenche **à chaque push sur `main` et à chaque Pull Request**, et exécute 3 jobs en parallèle :

| Job | Environnement | Étapes |
|---|---|---|
| **Backend — PHPUnit** | PHP 8.2 (ubuntu) | `composer install` → analyse syntaxique (`php -l`) → `phpunit` (16 tests / 31 assertions) |
| **Frontend — Jest + build** | Node 20 | `npm ci` → tests Jest + React Testing Library (12 tests) → `npm run build` avec `CI=true` (**tout warning ESLint fait échouer le build**) |
| **Mobile — Jest** | Node 20 | `npm ci` → tests Jest du service API (TypeScript) |

Effets concrets :

- **aucune fusion ne peut dégrader silencieusement l'existant** : une PR dont un test échoue est marquée ❌ ;
- le badge de statut est visible sur la page du dépôt (onglet *Actions*) ;
- les tests tournent sur un environnement neuf à chaque fois — ce qui détecte les oublis de dépendances (`package-lock.json` fait foi via `npm ci`).

### Reproduire les tests en local

```bash
# Backend
cd marketcraft-backend && composer install && vendor/bin/phpunit

# Frontend
cd marketcraft-frontend && npm ci && npx react-scripts test --watchAll=false

# Mobile
cd marketcraft-mobile && npm ci && npm test
```

---

## 2. Déploiement du frontend — Vercel

Le frontend React est un **site statique après build** : c'est le cas d'usage idéal de Vercel (CDN mondial, HTTPS automatique, déploiement continu branché sur GitHub).

### 2.1 Mise en place (une seule fois)

1. Sur [vercel.com](https://vercel.com), **Add New → Project** → importer le dépôt GitHub `MarketCraft_3emeDev`.
2. Paramètres du projet :
   - **Root Directory** : `marketcraft-frontend`
   - **Framework Preset** : Create React App (détecté automatiquement)
   - **Build Command** : `npm run build` — **Output Directory** : `build`
3. **Variable d'environnement** (Settings → Environment Variables) :
   - `REACT_APP_API_URL` = URL publique de l'API backend, ex. `https://api.marketcraft.fr/api`
   - (le code utilise déjà cette variable : `src/services/api.js`, repli sur `http://localhost:8000/api` en dev)
4. Déployer. Chaque **push sur `main` redéploie automatiquement** ; chaque PR obtient une **Preview URL** isolée.

Le fichier [`marketcraft-frontend/vercel.json`](../marketcraft-frontend/vercel.json) versionne la configuration :
- **rewrites SPA** : toute URL (ex. `/produits/12`) est réécrite vers `index.html` pour que React Router gère la navigation (sinon 404 au rafraîchissement) ;
- **en-têtes de sécurité** : `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`.

### 2.2 Le backend n'est pas sur Vercel — pourquoi et où

Vercel n'exécute pas PHP ni MySQL : il héberge du statique et des fonctions serverless JS. La répartition retenue :

| Couche | Hébergement | Justification |
|---|---|---|
| Frontend React | **Vercel** | Statique, CDN, HTTPS et CI/CD intégrés, déjà maîtrisé |
| API PHP 8 + MySQL | VPS (ou hébergeur PHP : o2switch, Hostinger…) | PHP-FPM + MySQL natifs, contrôle des triggers/procédures |
| App mobile | Build APK/TestFlight (hors périmètre soutenance) | Consomme la même API |

Points d'attention lors de la mise en production de l'API :
- `ALLOWED_ORIGINS` du backend doit lister l'URL Vercel (ex. `https://marketcraft.vercel.app`) — le CORS est déjà configurable via `.env` ;
- `APP_URL` doit être l'URL publique de l'API (les URLs d'images uploadées en dérivent) ;
- HTTPS obligatoire des deux côtés (Vercel le fournit ; Let's Encrypt côté API).

### 2.3 Chaîne complète (schéma)

```
push sur main ──► GitHub Actions : phpunit + jest + build  ──► ✅/❌
      │
      └────────► Vercel : build + déploiement automatique du frontend
                        (Preview URL sur chaque Pull Request)
```

---

## 3. Correspondance avec le référentiel CDA

- **CP14 (plans de tests)** : les suites automatisées sont exécutées *systématiquement* par la CI — la preuve d'exécution est l'historique de l'onglet Actions du dépôt.
- **CP15 (déploiement)** : procédure de déploiement documentée, reproductible et automatisée (déploiement continu Vercel) ; environnements séparés (Preview par PR / Production sur `main`).
