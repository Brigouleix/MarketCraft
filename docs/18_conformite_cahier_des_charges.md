# Conformité au cahier des charges — Master 1 CDA, 4ᵉ année

**Projet audité** : MarketCraft, branche `TestBranch` (issue de `import-3emedev`)
**Référentiel** : *Version finale du cahier de charge pour projet 4ème année_IA*
**Date de l'audit** : 25 juillet 2026

---

## Verdict en une phrase

Le projet est **techniquement abouti mais non conforme sur trois points structurants** : le module IA ne correspond à aucune des trois options imposées, le back-end n'utilise ni framework ni ORM autorisés, et une partie du travail de mise en conformité déjà réalisé se trouve sur une branche que vous n'utilisez pas.

---

## 1. Le point le plus urgent : le module IA hors périmètre

Le cahier des charges impose de choisir **un seul module parmi trois** :

| Option imposée | Présent ? |
|---|---|
| A — Chatbot d'assistance client (SAV) | Non |
| B — Génération automatique de fiches produits | Non |
| C — Recommandation personnalisée de produits | Non |

MarketCraft propose une **recherche produits en langage naturel**. C'est une fonctionnalité IA réelle et fonctionnelle, mais elle ne figure pas dans la liste. Le document précise « Choisir un seul parmi les trois », sans mention d'alternative libre.

C'est le risque le plus sérieux du dossier : un jury peut considérer l'exigence centrale du sujet comme non satisfaite, quelle que soit la qualité du reste.

**Ce qui joue en votre faveur** : l'infrastructure est déjà en place. Appel à un fournisseur LLM, prompt structuré, parsing JSON, repli en cas d'indisponibilité, journalisation des échecs — tout est réutilisable. Basculer vers l'**option C (recommandation)** est le chemin le plus court : mêmes briques, un nouvel endpoint, un bloc « Vous pourriez aussi aimer » sur la fiche produit. L'option B est aussi accessible puisque le back-office produit existe déjà.

À noter : le commit de `origin/master` mentionne « IA recommandations » — il est possible qu'une amorce existe déjà de ce côté (voir section 5).

---

## 2. Écarts sur l'architecture technique

### Framework back-end — non conforme

Le CDC impose Laravel ou Symfony pour PHP (ou Node/NestJS, Django/FastAPI, ASP.NET Core). MarketCraft utilise un **MVC maison** : routeur, contrôleurs et modèles écrits à la main, sans framework.

C'est un travail plus difficile qu'un projet Laravel et qui démontre une meilleure compréhension des mécanismes sous-jacents — mais ce n'est pas ce qui est demandé. Une réécriture est hors de portée à ce stade ; **la seule option réaliste est de l'assumer et de le justifier par écrit** dans le dossier de conception, que le CDC exige de toute façon (§3.5 : « Le choix technologique doit être justifié par écrit […] avec une analyse des avantages et inconvénients »). Un argumentaire honnête sur ce point vaut mieux qu'un silence.

### ORM — absent

Le CDC exige explicitement un ORM (Eloquent, Doctrine, Sequelize, Entity Framework). MarketCraft utilise **PDO en requêtes préparées**.

Bonne nouvelle : cela satisfait pleinement l'exigence de sécurité contre l'injection SQL (§4). Mauvaise : l'exigence d'architecture n'est pas remplie. Même remarque que ci-dessus — à justifier.

### Couches Service / Repository — absentes

Le CDC accepte « MVC **ou** architecture en couches ». Vous êtes en MVC, c'est donc conforme. Les contrôleurs portent toutefois de la logique métier qui gagnerait à être isolée si vous avez le temps.

---

## 3. Exigences de sécurité (§4)

| Exigence | État | Détail |
|---|---|---|
| Contrôle de rôle sur chaque route | **Conforme** | Middleware `auth` + `requireAdmin()` en tête d'action |
| Hachage bcrypt / argon2 | **Conforme** | `password_hash(PASSWORD_BCRYPT, cost 12)` |
| Requêtes préparées | **Conforme** | PDO partout, `ATTR_EMULATE_PREPARES => false` |
| `.env` jamais commité | **Conforme** | Couvert par `.gitignore`, vérifié dans l'index Git |
| En-têtes sécurisés | **Conforme** | `X-Frame-Options`, `nosniff`, `Referrer-Policy` dans `.htaccess` |
| HTTPS local | À vérifier | Non testable depuis l'audit |
| **Verrouillage après 5 tentatives** | **Manquant** | Absent de cette branche — **présent sur `origin/master`** |
| **CAPTCHA** | **Manquant** | Aucune trace dans le projet |
| **Journal d'activité en back-office** | **Manquant** | `ActivityLogModel` existe sur `origin/master`, mais aucun écran de consultation |

Le CDC demande que les logs soient « consultables en back-office ». Même en récupérant le modèle de `origin/master`, il manquera l'onglet d'affichage — à ajouter dans l'espace admin, qui s'y prête déjà.

---

## 4. Livrables attendus (§5)

| Livrable | État |
|---|---|
| 1. Dossier de conception (MCD, MLD, UML, maquettes) | **Conforme** — `docs/` contient MCD, MLD, MPD, classes, 3 diagrammes de séquence, use case, maquettes |
| 2. Code source, dépôt structuré, README | **Partiel** — README complet, mais pas de branche `dev` (CDC : « main/dev/feature ») |
| 3. Documentation API Swagger/OpenAPI | **Manquant sur cette branche** — `openapi.yaml` (514 lignes) existe sur `origin/master` |
| 4. Application mobile — APK installable | **Partiel** — React Native présent et conforme au CDC, mais aucun build APK livré |
| 5. Script BDD + jeu de données | **Conforme** — `migrations.sql` avec données de démo, plus `002`/`003` |
| 6. Plan de projet — Gantt, Kanban, RACI, risques | **Partiel** — Kanban, backlog et rôles présents ; **Gantt, RACI et tableau des risques absents** |
| 7. Rapport de PFE, 30 pages | À vérifier — `PFE_MarketCraft.pdf` présent, volume non audité |
| 8. Support de soutenance | **Conforme** — deck et captures d'écran présents |

Le tableau des risques est explicitement demandé au §2.2 avec « probabilité, impact, plan de mitigation ». C'est rapide à produire et facile à oublier.

---

## 5. Point critique : votre travail est éclaté sur deux lignées

`origin/master` contient un commit intitulé **« feat: CDC compliance — PWA, CI/CD, Swagger, MPD, sécurité brute-force, IA recommandations »** absent de votre branche de travail. Il apporte :

- `openapi.yaml` — la documentation API exigée (livrable 3)
- verrouillage du compte après 5 tentatives, 15 minutes — exigence §4
- `ActivityLogModel.php` — amorce du journal d'activité
- `manifest.json`, `sw.js`, `serviceWorkerRegistration.js` — la PWA
- `seeds.sql`, `docs/mpd.sql`

**`TestBranch` et `origin/master` ont divergé de 36 commits contre 1.** Autrement dit : vous développez depuis des semaines sur une lignée qui ignore le travail de mise en conformité, pendant que ce travail dort sur une autre branche.

C'est à régler avant toute chose. Une fusion des deux lignées comblerait d'un coup quatre écarts de ce rapport.

---

## 6. Détail administratif à clarifier

Le cahier des charges vise la **4ᵉ année, Master 1 CDA, équipe de 2 étudiants**. Le projet est nommé et documenté comme un projet de **3ᵉ année** (base `marketcraft_3eme_dev`, branche `import-3emedev`, dossier `14_competences_CDA.docx`).

Si vous représentez un projet de 3ᵉ année pour une soutenance de 4ᵉ année, c'est un point qu'un jury relèvera. Rien ne l'interdit forcément, mais l'écart doit être assumé et expliqué plutôt que découvert pendant les questions.

---

## Ordre de priorité recommandé

1. **Fusionner `origin/master` dans votre branche de travail** — récupère Swagger, brute-force, PWA et le modèle de logs en une opération
2. **Traiter le module IA** — basculer ou compléter vers l'option C (recommandation), la plus proche de l'existant
3. **Écran de consultation des logs** dans le back-office admin
4. **Gantt, RACI et tableau des risques** — rapide, purement documentaire
5. **Build APK** de l'application mobile
6. **Justification écrite** des choix MVC maison et PDO dans le dossier de conception
7. **CAPTCHA** sur le formulaire de connexion
8. Créer une branche `dev` pour respecter la convention `main` / `dev` / `feature`
