# Diagramme d'Activité - Gestion Vendeur

Description : Ce diagramme représente le cycle de vie complet d'un vendeur artisan sur MarketCraft, depuis son inscription jusqu'à la réception des paiements, en passant par la gestion de sa boutique, de ses produits, et le traitement des commandes et des litiges éventuels.

```mermaid
flowchart TD
    A([Vendeur s'inscrit\nsur MarketCraft]) --> B[Remplit formulaire inscription\nNom, email, mot de passe, rôle: VENDEUR]
    B --> C[Email de vérification\nenvoyé]
    C --> D{Email\nconfirmé ?}
    D -- Non, 24h écoulées --> E[Renvoie email\nde confirmation]
    E --> D
    D -- Oui --> F[Compte activé\nrôle VENDEUR]

    F --> G[Accède à /créer-ma-boutique\nFormulaire boutique]
    G --> H[/Saisit informations boutique\nNom, description, SIRET, logo, bannière, adresse/]
    H --> I{Informations\nvalides ?}
    I -- Non --> J[Affiche erreurs\nde validation]
    J --> H
    I -- Oui --> K[Boutique créée\nStatut: EN_ATTENTE]
    K --> L[Email envoyé\nà l'administrateur]
    L --> M{Admin examine\nla boutique}

    M -- Rejetée --> N[Email de rejet\nenvoyé au vendeur avec motif]
    N --> O{Vendeur corrige\net resoumet ?}
    O -- Oui --> H
    O -- Non --> Z1([Abandonne la démarche])

    M -- Approuvée --> P[Email de validation\nenvoyé au vendeur]
    P --> Q[Boutique ACTIVE\nTableau de bord accessible]

    %% ─── GESTION PRODUITS ───
    Q --> R{Quelle action\nproduits ?}

    R -- Ajouter --> S[/Remplit fiche produit\nNom, prix, stock, catégorie, description/]
    S --> T[Upload des photos\njusqu'à 10 images par produit]
    T --> U{Photos\nvalides ?}
    U -- Non --> V[Affiche erreur\nFormat ou taille invalide]
    V --> T
    U -- Oui --> W[Produit créé\nStatut: BROUILLON]
    W --> X{Publier\nmaintenant ?}
    X -- Non --> Q
    X -- Oui --> Y[Produit publié\nStatut: PUBLIE]
    Y --> Q

    R -- Modifier --> AA[/Sélectionne produit\nà modifier/]
    AA --> AB[/Modifie les champs\n(prix, stock, description, photos)/]
    AB --> AC[Sauvegarde\nmodifications]
    AC --> Q

    R -- Supprimer / Archiver --> AD{Produit dans\ndes commandes actives ?}
    AD -- Oui --> AE[Archive le produit\nStatut: ARCHIVE — pas de suppression]
    AD -- Non --> AF[Supprime ou archive\nle produit]
    AE --> Q
    AF --> Q

    R -- Gérer les promotions --> AG[/Saisit nouveau prix\nou % de réduction/]
    AG --> AH[Promotion activée\nBadge affiché sur le produit]
    AH --> Q

    %% ─── GESTION COMMANDES ───
    Q --> AI{Notification\nnouvelle commande ?}
    AI -- Oui --> AJ[Consulte détail commande\nArticles, quantités, adresse]
    AJ --> AK[Prépare l'article\nEmballage artisanal]
    AK --> AL[Choisit transporteur\nColissimo / Chronopost / etc.]
    AL --> AM[/Saisit numéro de suivi\ndans l'interface/]
    AM --> AN[Statut: EXPEDIEE\nEmail d'expédition envoyé à l'acheteur]

    AN --> AO{Problème\nsignalé ?}
    AO -- Non --> AP{Commande\nliv rée ?}
    AP -- Non --> AP
    AP -- Oui --> AQ[Statut: LIVREE\nautomatiquement ou par l'acheteur]
    AQ --> AR[Délai de rétractation\n14 jours]

    AO -- Oui, litige --> AS[Ouvre ticket litige\navec l'acheteur]
    AS --> AT{Résolution\namiable ?}
    AT -- Oui, remboursement --> AU[Remboursement émis\nStripe refund]
    AT -- Oui, renvoi --> AV[Vendeur renvoie\nun produit de remplacement]
    AT -- Non --> AW[Escalade à l'admin\nMarketCraft arbitre]
    AW --> AX{Décision\nadmin}
    AX -- Remboursement acheteur --> AU
    AX -- En faveur vendeur --> AY[Commande maintenue\nPaiement libéré]

    AU --> AQ
    AV --> AQ
    AY --> AQ

    AR --> AZ{Réclamation\npendante ?}
    AZ -- Non --> BA[Paiement libéré\nau vendeur — sous 7j]
    AZ -- Oui --> AR

    BA --> BB[Virement sur compte\nbancaire vendeur]
    BB --> BC[Dashboard finances\nMis à jour]
    BC --> Q

    style A fill:#4CAF50,color:#fff,stroke:#388E3C
    style Z1 fill:#f44336,color:#fff,stroke:#c62828
    style Q fill:#2196F3,color:#fff,stroke:#1565C0
    style M fill:#FF9800,color:#fff,stroke:#E65100
    style AX fill:#FF9800,color:#fff,stroke:#E65100
    style BA fill:#4CAF50,color:#fff,stroke:#388E3C
    style BB fill:#4CAF50,color:#fff,stroke:#388E3C
```

## Légende

| Symbole | Signification |
|---------|---------------|
| Ovale vert `([...])` | Début ou fin du processus |
| Rectangle `[...]` | Activité du système ou du vendeur |
| Losange `{...}` | Point de décision |
| Parallélogramme `/.../ ` | Saisie manuelle du vendeur |
| Boucle retour | Itération jusqu'à validation |

### Couleurs
| Couleur | Signification |
|---------|---------------|
| Vert | Début, fin positive, paiement reçu |
| Rouge | Abandon du parcours |
| Bleu | Tableau de bord (hub central) |
| Orange | Décision administrative ou critique |

### États d'une commande côté vendeur
```
[Commande reçue] → EN_PREPARATION → EXPEDIEE → LIVREE → [14j délai] → [Paiement libéré]
                                                           ↓
                                                      [Litige éventuel]
```

### Délais importants
| Événement | Délai |
|-----------|-------|
| Confirmation email d'inscription | 24 heures max |
| Examen boutique par l'admin | 2 jours ouvrés |
| Délai de rétractation acheteur | 14 jours après livraison |
| Libération du paiement | 7 jours après fin de délai de rétractation |
