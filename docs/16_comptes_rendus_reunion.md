# Comptes-rendus de réunion — MarketCraft (CP9)

Points d'équipe tenus à chaque changement de jalon, cohérents avec l'historique Git du dépôt. Format court : décisions, actions, échéance.

---

## CR n°1 — Cadrage du projet

**Date :** 20/05/2026
**Participants :** Antoine, Brigoo
**Support :** point de démarrage (présentiel)

### Ordre du jour
- Définition du périmètre fonctionnel de MarketCraft (marketplace artisanale)
- Choix de la stack technique
- Mise en place du dépôt Git

### Décisions
1. Stack retenue : back-end PHP (MVC maison), front-end React, application mobile React Native.
2. Authentification par JWT (HS256), hashage des mots de passe en bcrypt.
3. Modélisation des données selon la méthode Merise (MCD → MLD → MPD) avant tout développement.
4. Board Kanban tenu en markdown dans le dépôt (pas d'outil externe pour une équipe de 2 personnes).

### Actions
| Action | Responsable | Échéance |
|---|---|---|
| Initialiser le dépôt GitHub et la structure des dossiers | Brigoo | Immédiat |
| Rédiger le MCD/MLD initial | Antoine | Avant développement back-end |
| Vérifier l'absence de secrets commités (`.env`, `vendor/`) | Brigoo | Immédiat |

### Constat post-réunion
Un premier commit (`9a682eff`) incluait par erreur `.env`, `vendor/` et `node_modules/`. Corrigé le jour même via `1298ee22` et `178e194e` (ajout du `.gitignore`, purge de l'historique local). Point de vigilance retenu pour la suite : toujours vérifier `git status` avant le premier commit d'un nouveau dossier.

---

## CR n°2 — Point d'avancement back-end, mobile et maquettes

**Date :** 04/06/2026
**Participants :** Antoine, Brigoo
**Support :** appel

### Ordre du jour
- Avancement des routes back-end (dashboard, upload d'images, statistiques)
- Avancement de l'application mobile
- Validation de la maquette Figma et du moodboard de marque

### Décisions
1. Le moodboard de marque (couleurs, typographies) est validé et servira de référence pour toutes les interfaces (web + mobile).
2. Les statistiques vendeur/acheteur sont calculées côté back-end à la demande (pas de cache pour le MVP) — à revoir si problème de performance en production.
3. L'application mobile v1 couvre le parcours d'achat minimal ; les fonctionnalités vendeur avancées restent sur le front web pour cette version.

### Actions
| Action | Responsable | Échéance |
|---|---|---|
| Livrer les routes back-end (dashboard, upload, stats) | Antoine | Fait le jour même (`044f80e7`) |
| Livrer la v1 mobile React Native | Antoine | Fait le jour même (`37c9f911`) |
| Finaliser maquette & moodboard | Antoine | Fait le jour même (`5d41cf95`) |
| Planifier une phase de tests automatisés | Antoine / Brigoo | Prochain jalon |

### Points de vigilance soulevés
Brigoo signale l'absence de tests automatisés à ce stade : risque de régression à mesure que le périmètre grandit. Retenu comme priorité du jalon suivant.

---

## CR n°3 — Mise en place des tests automatisés

**Date :** 18/06/2026
**Participants :** Antoine
**Support :** point solo, compte-rendu partagé à Brigoo en asynchrone

### Ordre du jour
- Couverture de tests back-end, front-end et mobile

### Décisions
1. Priorité aux tests des parcours critiques : authentification, création de commande, paiement, upload produit.
2. Pas d'objectif de couverture chiffrée imposé pour le MVP — priorité à la couverture des chemins métier sensibles plutôt qu'à un pourcentage global.

### Actions
| Action | Responsable | Échéance |
|---|---|---|
| Ajouter les suites de tests back-end/front-end/mobile | Antoine | Fait le jour même (`c63d9a73`) |
| Relire et valider les suites de tests | Brigoo | Sous 48h |

---

## CR n°4 — Recentrage du périmètre avant soutenance

**Date :** 13/07/2026
**Participants :** Antoine, Brigoo
**Support :** appel

### Ordre du jour
- Revue du périmètre restant avant soutenance CDA
- Sort de la fonctionnalité de recherche assistée par IA (US-09)

### Décisions
1. La recherche assistée par IA (US-09) est **retirée du périmètre livré**. Justification : dépendance à une API externe non maîtrisée pour une démonstration en soutenance (coût, latence, disponibilité), et risque de complexifier l'évaluation des compétences techniques cœur du projet (SGBD, back-end, front-end, mobile) au profit d'une fonctionnalité annexe.
2. Le retrait est fait proprement : suppression du code mort plutôt que désactivation par flag, pour ne pas laisser de code non maintenu dans le dépôt final.
3. Le temps ainsi libéré est réaffecté à la complétude du dossier de compétences CDA (diagrammes Merise, composants SGBD programmables, documentation de gestion de projet).

### Actions
| Action | Responsable | Échéance |
|---|---|---|
| Retirer la recherche IA du code back-end/front-end | Brigoo | Fait le jour même (`4b17c887`) |
| Compléter le MPD avec le détail des choix techniques | Antoine | 14/07/2026 |
| Ajouter des composants SGBD programmables (triggers, procédure) démontrant la maîtrise du SGBD (CP8) | Antoine | 14/07/2026 |
| Documenter la gestion de projet (board Kanban, comptes-rendus) pour le dossier CDA (CP9) | Antoine | 14/07/2026 |

---

## CR n°5 — Point de clôture technique (SGBD & documentation)

**Date :** 14/07/2026
**Participants :** Antoine
**Support :** point solo

### Ordre du jour
- Validation des composants SGBD ajoutés (triggers, fonction, procédure, event)
- État du dossier de compétences CDA

### Décisions
1. Les composants SGBD sont vérifiés par relecture manuelle du script SQL (pas d'instance MySQL disponible pour exécution immédiate) ; des instructions de test manuel sont jointes dans `migrations.sql` pour validation à la première mise en place d'un environnement de base de données.
2. Le board Kanban et les comptes-rendus sont formalisés a posteriori à partir de l'historique Git réel du projet, pour refléter fidèlement l'organisation suivie plutôt que d'inventer un déroulé fictif.

### Actions restantes
| Action | Responsable | Échéance |
|---|---|---|
| Exécuter et valider les triggers/procédure sur un environnement MySQL réel | Antoine | Avant soutenance |
| Mettre à jour le tableau de correspondance des compétences CDA (CP8/CP9 → Validé) | Antoine | 14/07/2026 |
