# Gestion de projet — MarketCraft (CP9)

Ce document décrit l'organisation collaborative retenue pour le développement de MarketCraft : méthode de travail, outillage, backlog produit, board Kanban et convention Git. Il formalise a posteriori l'organisation réellement suivie par l'équipe (2 contributeurs : **Antoine** et **Brigoo**), en s'appuyant sur l'historique Git du dépôt comme trace d'avancement.

## 1. Méthode de travail

Le projet a été mené en méthode **Kanban allégée** plutôt qu'en Scrum strict, adaptée à une équipe de deux personnes travaillant en asynchrone :

- pas de sprints à durée fixe imposée, mais des **jalons fonctionnels** (cadrage → back-end/mobile → tests → durcissement/soutenance) ;
- un **board Kanban** unique (repris ci-dessous, tenu en markdown dans le dépôt faute d'outil externe partagé) faisant office de source de vérité sur l'avancement ;
- une **revue de code par relecture croisée** avant fusion sur `main`, via Pull Request GitHub ;
- des **points d'équipe courts** (15–30 min) à chaque changement de phase, dont le compte-rendu est archivé dans [`16_comptes_rendus_reunion.md`](16_comptes_rendus_reunion.md).

## 2. Outillage collaboratif

| Outil | Usage |
|---|---|
| **GitHub** (`Brigouleix/MarketCraft_3emeDev`) | Hébergement du dépôt, historique de commits, branches, Pull Requests |
| **Board Kanban markdown** (ce document) | Suivi visuel des tâches (Backlog / À faire / En cours / En revue / Terminé), à défaut d'un Trello/Jira partagé |
| **Comptes-rendus de réunion markdown** | Traçabilité des décisions et actions entre les points d'équipe |
| **Discussion directe (présentiel / appel)** | Coordination courante entre les deux contributeurs, non tracée nominalement |

> Choix assumé : à l'échelle de cette équipe (2 personnes), un board markdown versionné avec le code apporte la même traçabilité qu'un Trello/Jira externe, sans dépendance à un compte tiers ni risque de perte de synchronisation avec le dépôt.

## 3. Convention Git

- **Branche `main`** : version stable, déployable, celle documentée dans [`11_deploiement.md`](11_deploiement.md).
- **Branches de fonctionnalité** : créées pour les évolutions à risque avant fusion dans `main`. Exemple réel : `version-sans-ia`, utilisée pour retirer la fonctionnalité de recherche assistée par IA du périmètre final (voir commit `4b17c887`) sans perturber `main` pendant les tests.
- **`master`** conservé côté distant comme référence historique du nom de branche par défaut initial, avant renommage en `main`.
- **Convention de messages de commit** de type *Conventional Commits* : préfixes `feat:`, `fix:`, `chore:`, `test:` utilisés tout au long du projet pour catégoriser chaque changement (visible dans `git log`).
- **`.gitignore`** strict : exclusion de `.env`, `vendor/`, `node_modules/` — un oubli initial a d'ailleurs été corrigé rapidement (commits `1298ee22` et `178e194e`), illustrant la vigilance apportée à la non-exposition de secrets dans l'historique.

## 4. Backlog produit

Backlog priorisé (MoSCoW) tel que constitué en début de projet et fait évoluer au fil des jalons :

| # | User story | Priorité | Statut |
|---|---|---|---|
| US-01 | En tant que visiteur, je peux consulter le catalogue de produits artisanaux | Must | Terminé |
| US-02 | En tant qu'acheteur, je peux créer un compte et me connecter (JWT) | Must | Terminé |
| US-03 | En tant qu'acheteur, je peux ajouter des produits au panier et passer commande | Must | Terminé |
| US-04 | En tant qu'acheteur, je peux payer ma commande en ligne | Must | Terminé |
| US-05 | En tant que vendeur, je dispose d'un tableau de bord avec mes statistiques de vente | Must | Terminé |
| US-06 | En tant que vendeur, je peux gérer ma boutique et mes produits (upload d'images inclus) | Must | Terminé |
| US-07 | En tant qu'acheteur, je peux laisser un avis noté sur un produit acheté | Should | Terminé |
| US-08 | En tant qu'utilisateur mobile, je peux utiliser les fonctionnalités clés depuis l'application React Native | Should | Terminé |
| US-09 | En tant qu'acheteur, je peux rechercher des produits assistée par IA | Could | **Retiré du périmètre** (commit `4b17c887`) |
| US-10 | En tant que vendeur, mes fonds sont libérés automatiquement 14 jours après livraison | Should | Terminé (triggers/procédure SGBD) |
| US-11 | En tant qu'équipe, je dispose de suites de tests automatisés (back-end, front-end, mobile) | Must | Terminé (commit `c63d9a73`) |

La suppression de l'US-09 illustre un arbitrage de gestion de projet assumé : la recherche assistée par IA, jugée hors périmètre soutenable pour la soutenance (dépendance à une API externe, coût, complexité de test), a été retirée volontairement plutôt que livrée incomplète — décision actée en réunion (voir compte-rendu du 13/07/2026).

## 5. Board Kanban

État du board à la date de rédaction de ce document (14/07/2026), reconstitué à partir de l'historique du dépôt et des tâches restantes pour la soutenance :

### 📋 Backlog

- Étude d'une v2 avec relance de la recherche IA sur une infrastructure dédiée (hors périmètre soutenance)
- Notifications email transactionnelles (confirmation de commande, libération de paiement)

### 📝 À faire

- Relecture finale du dossier de compétences CDA avant dépôt

### 🔨 En cours

- Vérification manuelle des composants SGBD ajoutés (triggers/fonction/procédure) en l'absence d'environnement MySQL de test local

### 👀 En revue

- Documentation du Modèle Physique de Données (MPD) — section composants programmables SGBD

### ✅ Terminé

| Carte | Référence |
|---|---|
| Cadrage projet, MCD/MLD, mise en place du dépôt et du `.gitignore` | `9a682eff`, `178e194e`, `1298ee22` (20/05/2026) |
| Maquette Figma & moodboard de marque | `5d41cf95` (04/06/2026) |
| Application mobile React Native v1 | `37c9f911` (04/06/2026) |
| Routes back-end, dashboard, upload d'images, statistiques acheteur/vendeur | `044f80e7` (04/06/2026) |
| Suites de tests automatisés back-end / front-end / mobile | `c63d9a73` (18/06/2026) |
| Retrait de la recherche IA (recentrage du périmètre) | `4b17c887` (13/07/2026) |
| Ajout des triggers, fonction et procédure SGBD (CP8) | migration `migrations.sql`, section « Composants SGBD » (14/07/2026) |
| Séparation MLD / MPD et documentation associée | `09_merise_mld.md`, `13_merise_mpd.md` (14/07/2026) |

## 6. Répartition des rôles

| Rôle | Contributeur | Périmètre principal |
|---|---|---|
| Développement back-end & base de données | Antoine | API PHP, modèle de données, tests, SGBD |
| Développement mobile & maquettes | Antoine | Application React Native, intégration Figma |
| Cadrage, infrastructure dépôt, arbitrages de périmètre | Brigoo | `.gitignore`/sécurité, décision de recentrage (retrait IA), revue |

Cette répartition n'est pas figée : les deux contributeurs interviennent en relecture croisée sur l'ensemble du code, la distinction ci-dessus reflète la responsabilité principale de chaque jalon plutôt qu'un cloisonnement strict.
