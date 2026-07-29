# Brief — Slides de soutenance MarketCraft (40 min)

> **Message à transmettre tel quel à l'assistant design :**
> Crée une présentation de soutenance de 21 slides à partir de ce brief. Chaque slide est décrite ci-dessous avec son titre, le contenu à afficher et une suggestion visuelle. Les « Notes orales » sont à placer dans les notes du présentateur de chaque slide (pas sur la slide elle-même). Règle d'or : les slides portent des mots-clés et des visuels, jamais des paragraphes — tout le discours est dans les notes.

---

## Identité visuelle et ton

- **Projet** : MarketCraft — marketplace e-commerce dédiée à l'artisanat (projet de fin d'études, titre Concepteur Développeur d'Applications).
- **Présentateur** : Antoine Brigouleix.
- **Palette « artisanale »** : brun terre `#8B4513` (titres/accents), vert olive `#6B7C3F` (accents secondaires), crème `#F5F0E8` (fonds), brun foncé `#3B2A1A` (texte). Typo : une serif élégante pour les titres, une sans-serif lisible pour le corps.
- **Ton** : professionnel, chaleureux, orienté « fait main » (icônes simples, pas de stock photos criardes).
- **Structure imposée** (plan du jury, 40 minutes) : 9 sections avec minutage — respecter l'ordre.

## Contexte projet (pour que le designer comprenne de quoi on parle)

MarketCraft permet à des **artisans** (céramistes, ébénistes, bijoutiers…) de créer leur **boutique en ligne**, de publier leurs **créations** (photos, prix, stock) et de gérer leurs **commandes** ; les **acheteurs** parcourent un catalogue filtrable, remplissent un panier et commandent ; un **administrateur** supervise la plateforme. Stack : **React 18 + TailwindCSS + React Query** (frontend), **PHP 8.1 en MVC écrit sans framework** avec authentification **JWT** (API REST), **MySQL 8** avec logique de gestion embarquée (triggers, fonction, procédure stockée, événement planifié). Une **application mobile React Native** consomme la même API. Tests : **PHPUnit 16 tests / 31 assertions** et **Jest + React Testing Library 12 tests**, 100 % de réussite.

---

# Les slides

## SECTION 1 — Contexte et enjeux (3 min)

### Slide 1 — Titre
**Affiché** : « MarketCraft — Conception et développement d'une marketplace dédiée à l'artisanat » · Soutenance CDA · Antoine Brigouleix · [date]
**Visuel** : logo/monogramme MarketCraft, fond crème, touche bois/terre.
**🎤 Notes orales** :
« Bonjour à toutes et à tous, merci de m'accueillir pour cette soutenance. Je m'appelle Antoine Brigouleix et je vais vous présenter MarketCraft, une marketplace e-commerce dédiée à l'artisanat, que j'ai conçue et développée dans le cadre de mon titre de Concepteur Développeur d'Applications. Pendant les quarante prochaines minutes, je vais vous emmener du problème de départ jusqu'à la démonstration de l'application, en passant par la conception, les choix techniques et les tests. »

### Slide 2 — Le problème
**Affiché** : 3 constats en icônes : « Artisans invisibles sur les plateformes généralistes » · « Pas d'outils adaptés aux vendeurs indépendants » · « Confiance acheteur fragile (avis, paiement, litiges) »
**Visuel** : contraste entre une grille de produits industriels anonymes et une création artisanale mise en valeur.
**🎤 Notes orales** :
« Partons du constat. L'artisanat séduit de plus en plus d'acheteurs en quête de pièces uniques et d'authenticité. Pourtant, les créateurs restent mal servis par les grandes plateformes généralistes, pour trois raisons. D'abord la visibilité : une céramique faite main se retrouve noyée au milieu de millions de références industrielles, sans espace de marque pour l'artisan. Ensuite, les outils : gérer une boutique, un stock de pièces uniques, des commandes, cela demande un back-office simple, pensé pour des personnes dont le métier n'est pas l'informatique. Enfin, la confiance : l'acheteur d'une pièce à 80 euros veut des avis authentiques, un paiement sécurisé, et un recours en cas de problème. C'est cet espace entre les besoins des artisans et l'offre existante que MarketCraft vient occuper. »

### Slide 3 — La réponse MarketCraft
**Affiché** : phrase-pilier « Une marketplace où chaque artisan a sa boutique » + 3 rôles : Acheteur / Vendeur / Administrateur.
**Visuel** : schéma simple à 3 personas reliés à la plateforme.
**🎤 Notes orales** :
« MarketCraft répond à ce besoin avec une plateforme organisée autour de trois rôles. L'acheteur découvre, filtre et commande des créations. Le vendeur — l'artisan — crée sa boutique à son image, publie ses produits et suit ses commandes en autonomie. Et l'administrateur supervise l'ensemble : comptes, boutiques, litiges. La problématique que je me suis posée est donc la suivante : comment concevoir une marketplace multi-vendeurs qui offre aux artisans une gestion simple, et aux acheteurs un parcours d'achat fluide et digne de confiance, tout en garantissant l'intégrité des données critiques du e-commerce — les stocks, les commandes et les paiements ? »

## SECTION 2 — Objectifs fonctionnels et techniques (3 min)

### Slide 4 — Objectifs fonctionnels
**Affiché** : 3 colonnes (une par rôle). Acheteur : catalogue filtrable, fiche produit, panier, commande, avis. Vendeur : boutique personnalisée, produits + stocks, commandes, dashboard CA. Admin : comptes, rôles, supervision.
**Visuel** : tableau à 3 colonnes avec icônes.
**🎤 Notes orales** :
« Côté fonctionnel, chaque rôle a ses objectifs. Pour l'acheteur : un catalogue que l'on peut filtrer par mots-clés, catégorie, prix et note ; une fiche produit complète avec galerie photos et disponibilité du stock ; un panier persistant et propre à chaque compte ; un tunnel de commande avec suivi de statut ; et la possibilité de laisser un avis — un seul par produit, pour garantir leur crédibilité. Pour le vendeur : créer sa boutique avec logo et bannière, gérer ses produits de A à Z — y compris les photos et le stock —, suivre les commandes reçues et changer leur statut, et piloter son activité depuis un dashboard : chiffre d'affaires, commandes, note moyenne. Pour l'administrateur : la gestion des comptes et des rôles, et la supervision des boutiques. »

### Slide 5 — Objectifs techniques
**Affiché** : 4 blocs : « API REST sécurisée (JWT) » · « Intégrité stocks & paiements garantie en base » · « Responsive web + mobile » · « Qualité : tests automatisés »
**Visuel** : 4 cartes avec icônes bouclier / base de données / écrans / coche.
**🎤 Notes orales** :
« Côté technique, quatre objectifs ont guidé le projet. Un : une architecture découplée, avec une API REST sécurisée par tokens JWT, consommée par le site web — et, on le verra, par une application mobile. Deux : l'intégrité des données critiques garantie au plus près de la base : impossible de vendre au-delà du stock, paiements libérés au vendeur seulement quatorze jours après livraison. Trois : une expérience fluide sur tous les écrans. Et quatre : une démarche qualité avec des tests automatisés côté serveur comme côté client. »

## SECTION 3 — Analyse des besoins et modélisation (6 min)

### Slide 6 — Cahier des charges
**Affiché** : périmètre MVP en 6 puces : authentification 3 rôles · boutiques · catalogue filtrable · panier/commandes · avis · dashboards. Hors périmètre : paiement réel, messagerie.
**Visuel** : liste « inclus » / « hors périmètre v1 » en deux encarts.
**🎤 Notes orales** :
« L'analyse des besoins a d'abord fixé le périmètre. Dans la première version : l'authentification avec trois rôles, la création de boutiques, le catalogue avec recherche et filtres, le panier et les commandes avec gestion de stock, les avis, et les tableaux de bord. J'ai volontairement placé hors périmètre le paiement réel — simulé pour l'instant — et la messagerie acheteur-vendeur, qui sont des perspectives. Ce cadrage m'a permis de livrer un produit complet et cohérent plutôt qu'un produit large et inachevé. »

### Slide 7 — Maquettes
**Affiché** : 3-4 captures des maquettes (accueil, catalogue, fiche produit, dashboard vendeur). *(Le présentateur fournira les images.)*
**Visuel** : mosaïque de maquettes avec légendes.
**🎤 Notes orales** :
« Avant de coder, j'ai maquetté les écrans principaux. Ces maquettes ont fixé l'identité visuelle — une palette chaleureuse, tons terre et vert olive, en cohérence avec l'univers artisanal — et surtout les parcours : la découverte des produits, l'ajout au panier, et le back-office vendeur pensé pour des non-techniciens. Elles ont servi de contrat visuel pendant tout le développement du frontend. Vous retrouverez d'ailleurs cette fidélité maquette-produit dans la démonstration tout à l'heure. »

### Slide 8 — Cas d'utilisation
**Affiché** : diagramme de cas d'utilisation avec les 3 acteurs. *(Image fournie par le présentateur.)*
**Visuel** : le diagramme UML, épuré, avec les 3 acteurs colorés selon la palette.
**🎤 Notes orales** :
« Voici le diagramme de cas d'utilisation qui synthétise tout cela. Trois acteurs. Le client : s'inscrire, s'authentifier, consulter et filtrer le catalogue, gérer son panier, commander, suivre ses commandes, publier un avis. Le vendeur hérite de tout ce que peut faire un client, et ajoute la gestion de sa boutique, de ses produits et des commandes reçues. L'administrateur gère les utilisateurs et supervise la plateforme. Ce diagramme a été mon référentiel fonctionnel : chaque cas d'utilisation correspond aujourd'hui à des routes d'API et à des écrans identifiables. »

## SECTION 4 — Choix technologiques (5 min)

### Slide 9 — La stack
**Affiché** : 3 colonnes logos/noms : Frontend « React 18 · TailwindCSS · React Query » / Backend « PHP 8.1 — MVC from scratch · JWT » / BDD « MySQL 8 ». En bandeau : « + React Native (mobile) ».
**Visuel** : schéma en 3 blocs reliés par des flèches « API REST / JSON ».
**🎤 Notes orales** :
« Venons-en aux choix techniques. Côté frontend, React 18 avec TailwindCSS pour le style et React Query pour la gestion des données serveur. Côté backend, un choix fort : PHP 8.1 en architecture MVC, écrit sans framework. Et MySQL 8 pour la persistance. Le tout communique exclusivement par une API REST en JSON, ce qui a permis, sans modifier une seule ligne du serveur, de développer aussi une application mobile React Native. »

### Slide 10 — Pourquoi sans framework ?
**Affiché** : « Un backend écrit à la main » : Routeur · Contrôleurs + validation · Modèles PDO · Auth JWT. Bandeau : « Comprendre ce que les frameworks industrialisent ».
**Visuel** : schéma du flux d'une requête : Route → Middleware auth → Contrôleur → Modèle → JSON.
**🎤 Notes orales** :
« Pourquoi se passer d'un framework ? C'est un choix pédagogique assumé, et je peux le défendre techniquement. En écrivant moi-même le routeur, la validation des entrées, la couche d'accès aux données en PDO et l'authentification JWT — avec access token et refresh token —, je démontre la maîtrise des mécanismes que les frameworks industrialisent. Chaque requête suit un chemin que je contrôle de bout en bout : elle arrive sur le routeur, passe par le middleware d'authentification si la route l'exige, atteint le contrôleur qui valide les données, interroge le modèle, et repart en JSON normalisé. La limite de ce choix, j'en suis conscient : plus de code à ma charge, pas de migrations toutes faites ni d'ORM. Mais pour un projet de certification, la transparence totale du code me semblait plus formatrice. »

### Slide 11 — Justification côté client
**Affiché** : React Query = « cache + invalidation », Context API = « auth + panier », Tailwind = « cohérence + responsive ». 
**Visuel** : 3 cartes.
**🎤 Notes orales** :
« Côté client, mêmes principes de sobriété : React Query gère le cache des données et leur invalidation après chaque mutation — quand un vendeur modifie un produit, la liste se met à jour toute seule. La Context API, native à React, porte la session et le panier sans bibliothèque d'état supplémentaire. Et TailwindCSS garantit la cohérence visuelle et le responsive avec une productivité élevée. À chaque étage, j'ai choisi l'outil le plus léger qui couvre le besoin. »

## SECTION 5 — Architecture technique (4 min)

### Slide 12 — Schéma global
**Affiché** : schéma 3-tiers : [Navigateur React SPA] + [Mobile React Native] → HTTPS/JSON → [API PHP MVC — middleware JWT] → PDO → [MySQL 8 : tables + triggers/procédures]. 
**Visuel** : le schéma d'architecture, propre, aux couleurs du projet.
**🎤 Notes orales** :
« Voici l'architecture d'ensemble, en trois couches. La couche présentation existe en deux déclinaisons — le site React et l'application mobile — qui parlent toutes deux à la même API en HTTPS. La couche métier, c'est l'API PHP : routeur, middleware JWT, contrôleurs, modèles. Et la couche persistance, MySQL, qui n'est pas un simple entrepôt : elle embarque une partie de la logique de gestion avec ses triggers et sa procédure stockée — j'y reviens dans deux minutes. Cette séparation stricte fait que chaque couche peut évoluer ou être remplacée indépendamment. »

### Slide 13 — Diagramme de séquence : la commande
**Affiché** : diagramme de séquence du passage de commande : Client → API (JWT) → vérif stock → décrément atomique → création commande + paiement → confirmation. *(Image fournie.)*
**Visuel** : diagramme de séquence épuré, avec le point « stock insuffisant → 422 » mis en évidence.
**🎤 Notes orales** :
« J'ai choisi de détailler la séquence la plus critique : le passage de commande. Le client valide son panier ; la requête part avec son token JWT ; le middleware l'authentifie. Ensuite, pour chaque ligne du panier, l'API vérifie que le produit existe et que le stock est suffisant — sinon, erreur explicite et rien n'est créé. Puis le stock est décrémenté de façon atomique : la requête SQL ne s'exécute que si le stock reste positif, ce qui élimine le risque de survente même si deux clients commandent la dernière pièce au même instant. Enfin, la commande, ses lignes — qui capturent un instantané du prix — et le paiement sont créés, et le client reçoit sa confirmation. »

## SECTION 6 — Conception des données et des processus (6 min)

### Slide 14 — MCD / MPD
**Affiché** : le MCD (ou MPD) : utilisateurs, boutiques, catégories, produits, commandes, lignes_commande, paiements, avis, adresses. *(Image fournie.)*
**Visuel** : le schéma, avec les entités colorées par domaine (personnes / catalogue / ventes).
**🎤 Notes orales** :
« Le modèle de données compte neuf tables organisées en trois domaines. Les personnes : utilisateurs avec leur rôle, et adresses de livraison. Le catalogue : boutiques, catégories hiérarchiques, produits avec leur stock et leur galerie d'images. Les ventes : commandes, lignes de commande, paiements et avis. Quelques choix de conception à souligner : les lignes de commande capturent le nom et le prix du produit au moment de l'achat — l'historique reste juste même si le produit change ; la contrainte d'unicité sur le couple produit-utilisateur garantit un seul avis par personne ; et les suppressions sont pensées pour préserver l'historique : un produit supprimé est désactivé, pas effacé. »

### Slide 15 — La logique de gestion dans MySQL (CP8)
**Affiché** : 3 encarts : « 4 triggers — notes moyennes recalculées » · « fn_stock_suffisant() » · « sp_liberer_paiements_echus() + event quotidien (J+14) ».
**Visuel** : extrait court du trigger en code stylisé + icône engrenage base de données.
**🎤 Notes orales** :
« Point fort du projet : une partie des règles de gestion vit directement dans la base, indépendamment du code PHP. Premièrement, quatre triggers : à chaque avis créé, modifié ou supprimé, la note moyenne et le nombre d'avis du produit sont recalculés automatiquement, et la note de la boutique suit par cascade. Aucun risque d'incohérence, quel que soit le client qui écrit dans la base. Deuxièmement, une fonction stockée vérifie qu'un stock est suffisant — la règle métier existe en SQL, testable directement. Troisièmement, la règle des quatorze jours : une procédure stockée libère les paiements des commandes livrées depuis plus de deux semaines sans litige, et un événement planifié MySQL l'exécute chaque nuit. C'est une protection de l'acheteur totalement automatisée. »

### Slide 16 — Processus applicatifs clés
**Affiché** : 3 mini-flux : « Panier par compte (invité → fusion à la connexion) » · « Refresh token transparent » · « Vendeur : propriété vérifiée sur chaque écriture ».
**Visuel** : 3 petits diagrammes de flux horizontaux.
**🎤 Notes orales** :
« Trois processus applicatifs illustrent la logique côté code. Le panier d'abord : il est persistant et propre à chaque compte — si vous remplissez un panier en tant qu'invité puis vous connectez, il vous suit ; si un autre utilisateur se connecte sur le même navigateur, il retrouve le sien, pas le vôtre. La session ensuite : quand l'access token expire, l'application le renouvelle en arrière-plan avec le refresh token et rejoue la requête — l'utilisateur ne voit rien. La sécurité vendeur enfin : sur chaque écriture, l'API vérifie non seulement le rôle, mais la propriété — un vendeur ne peut modifier que les produits de sa propre boutique, un point que je considère comme non négociable sur une marketplace. »

## SECTION 7 — Phase de test et validation (3 min)

### Slide 17 — Stratégie et résultats
**Affiché** : 4 lignes avec compteurs : « PHPUnit : 16 tests / 31 assertions — 100 % » · « Jest + RTL : 12 tests — 100 % » · « Tests d'API : endpoints critiques validés » · « Sécurité : revue Top 10 OWASP ». 
**Visuel** : compteurs verts, badge « 100 % ».
**🎤 Notes orales** :
« La qualité repose sur quatre niveaux de tests. Côté serveur, PHPUnit : seize tests et trente-et-une assertions couvrent l'authentification JWT, la sécurité des mots de passe et le routeur — cent pour cent de réussite. Côté client, Jest et React Testing Library : douze tests sur la connexion, le panier et le client API. S'y ajoutent des tests d'API directs sur les endpoints critiques, et une revue de sécurité adossée au Top 10 OWASP : requêtes préparées contre l'injection, bcrypt et messages d'erreur génériques contre l'énumération de comptes, contrôles de rôle et de propriété, échappement contre le XSS. Et je veux souligner une chose : ces tests ont réellement servi. Ils ont détecté, entre autres, un jeu de données de test corrompu et un défaut d'isolation du panier entre comptes — des bugs corrigés parce que les tests les ont révélés. C'est ça, pour moi, la validation. »

## SECTION 8 — Démonstration (7 min)

### Slide 18 — Scénario de démonstration
**Affiché** : le fil de la démo en 5 étapes : 1. Créer la boutique (vendeur) → 2. Publier un produit (photos, stock) → 3. Acheter (client : filtres, panier, commande) → 4. Laisser un avis → 5. Dashboard vendeur (commande reçue, CA). Bandeau : « Démonstration en direct ».
**Visuel** : timeline horizontale à 5 étapes. Prévoir en slides suivantes (18b, cachées) des captures d'écran de secours de chaque étape.
**🎤 Notes orales** :
« Place à la démonstration, en cinq étapes, avec deux comptes. D'abord côté artisan : je crée ma boutique — nom, description, logo — et l'application m'enchaîne directement sur la création de mon premier produit : une boutique vide n'apparaît pas dans le catalogue, c'est une règle de la plateforme. Je publie donc un produit : photos par glisser-déposer, prix, stock, catégorie choisie dans la liste. Ensuite côté client, dans un autre navigateur : je retrouve ce produit par les filtres, je consulte sa fiche, je l'ajoute au panier — et vous verrez qu'un produit épuisé, lui, ne peut pas être ajouté. Je passe commande : adresse de livraison, puis l'étape de paiement — simulé pour cette version, avec une carte de test, mais le parcours est complet : la transaction est enregistrée en base avec son identifiant, que vous voyez sur l'écran de confirmation. Je laisse ensuite un avis — et j'insiste : seul un client qui a réellement commandé le produit peut en laisser un, c'est vérifié côté serveur — l'avis apparaît signé de mon prénom et la note moyenne se met à jour instantanément grâce au trigger. Enfin, retour côté artisan : la commande est arrivée sur le dashboard, je la passe en "expédiée", et mon chiffre d'affaires s'est actualisé. [Dérouler la démo en direct — en cas de pépin technique, basculer sur les captures de secours.] »

## SECTION 9 — Conclusion et perspectives (3 min)

### Slide 19 — Bilan
**Affiché** : 3 acquis : « Une marketplace complète et testée » · « Une architecture maîtrisée de bout en bout » · « Des règles métier garanties en base ».
**Visuel** : 3 médaillons.
**🎤 Notes orales** :
« En conclusion, trois acquis. Le produit d'abord : une marketplace fonctionnelle qui couvre tout le cycle — boutiques, catalogue, panier, commandes, paiements, avis — validée par des tests au vert. La démarche ensuite : une architecture trois-tiers dont je maîtrise chaque couche, jusqu'au routeur et à l'authentification écrits à la main. La rigueur enfin : les règles critiques du e-commerce — stocks, notes, paiements — sont garanties au niveau de la base de données elle-même. Ce projet m'a fait progresser en sécurité applicative, en modélisation, et dans la conduite complète d'un projet. »

### Slide 20 — Perspectives
**Affiché** : roadmap 4 items : « Paiement réel (Stripe) » · « Finalisation de l'app mobile » · « Messagerie acheteur-vendeur » · « CI/CD et mise en production ».
**Visuel** : frise chronologique simple.
**🎤 Notes orales** :
« Pour la suite : intégrer un vrai prestataire de paiement comme Stripe, en remplaçant la simulation actuelle — l'architecture des paiements est déjà prête à l'accueillir ; finaliser l'application mobile React Native, qui consomme déjà l'API ; ajouter la messagerie entre acheteurs et vendeurs pour renforcer la dimension humaine de la plateforme ; et automatiser le déploiement avec un pipeline d'intégration continue, la procédure étant déjà entièrement scriptable. »

### Slide 21 — Merci / Questions
**Affiché** : « Merci de votre attention — Des questions ? » + rappel visuel du logo.
**Visuel** : sobre, palette du projet.
**🎤 Notes orales** :
« Merci de votre attention. Je suis maintenant à votre disposition pour vos questions — sur les choix d'architecture, la sécurité, la base de données ou tout autre aspect du projet. »

---

## Checklist pour le présentateur (ne pas mettre en slide)

- Préparer **2 comptes de démo** (un vendeur avec boutique vide, un client) et un produit à 0 stock pour montrer le blocage panier.
- Générer les **captures de secours** de chaque étape de la démo (slides cachées après la 18).
- Fournir au designer les **images** : maquettes, diagramme de cas d'utilisation, diagramme de séquence, MCD/MPD.
- Chronométrage cible : 3+3+6+5+4+6+3+7+3 = 40 min. Répéter au moins une fois avec minuteur.
