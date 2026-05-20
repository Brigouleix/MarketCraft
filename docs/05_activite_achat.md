# Diagramme d'Activité - Parcours Acheteur

Description : Ce diagramme modélise l'ensemble du parcours utilisateur côté acheteur sur MarketCraft, depuis l'arrivée sur le site jusqu'au suivi de la commande, en représentant toutes les décisions, branchements et boucles possibles.

```mermaid
flowchart TD
    A([Arrivée sur MarketCraft]) --> B[Affichage page d'accueil\nProduits mis en avant + catégories]

    B --> C{L'utilisateur cherche\nquelque chose ?}
    C -- Oui --> D[/Saisit une recherche\nou clique une catégorie/]
    C -- Non --> E[Parcourt les produits\nen vedette / nouveautés]

    D --> F[Affichage résultats filtrés\nTriable par prix, note, date]
    E --> F

    F --> G{Un produit\nl'intéresse ?}
    G -- Non --> H{Affiner\nla recherche ?}
    H -- Oui --> D
    H -- Non --> Z1([Quitte le site])

    G -- Oui --> I[Consulte la fiche produit\nPhotos, description, avis, boutique]
    I --> J{Stock\ndisponible ?}
    J -- Non --> K[Affiche "Rupture de stock"\nOption : notifier quand dispo]
    K --> L{Continuer\nles achats ?}

    J -- Oui --> M{L'acheteur est-il\nconnecté ?}
    M -- Non --> N[Affiche modal\nConnexion / Inscription]
    N --> O{Choix}
    O -- Connexion --> P[/Saisit email + mot de passe/]
    O -- Inscription --> Q[/Remplit formulaire\nd'inscription/]
    Q --> R[Vérification email\nenvoye]
    R --> S{Email\nconfirmé ?}
    S -- Non --> S
    S -- Oui --> P
    P --> T{Authentification\nréussie ?}
    T -- Non --> U[Affiche erreur\nIdentifiants invalides]
    U --> P
    T -- Oui --> M
    M -- Oui --> V

    V[Choisit quantité\net options] --> W[Clique Ajouter au panier]
    W --> X{Stock suffisant\npour la quantité ?}
    X -- Non --> X1[Affiche quantité max\ndisponible]
    X1 --> V
    X -- Oui --> Y[Produit ajouté au panier\nNotification + compteur mis à jour]

    Y --> L{Continuer\nles achats ?}
    L -- Oui --> B
    L -- Non --> AA[Accède au panier\n/panier]

    AA --> AB[Révise le panier\nModifie quantités ou supprime]
    AB --> AC{Panier\nvalide ?}
    AC -- Non --> AB
    AC -- Oui --> AD[Clique Passer la commande]

    AD --> AE[Sélectionne ou saisit\nl'adresse de livraison]
    AE --> AF[Choisit le mode de livraison\nStandard / Express / Retrait]
    AF --> AG[Affichage récapitulatif\nSous-total + Livraison + TVA = Total]
    AG --> AH[Saisit les informations\nde paiement Stripe]

    AH --> AI{Paiement\ntraité ?}
    AI -- Échec --> AJ[Affiche raison d'échec\nCarte refusée / Fonds insuffisants]
    AJ --> AK{Réessayer\navec autre carte ?}
    AK -- Oui --> AH
    AK -- Non --> AL([Abandonne la commande])

    AI -- Succès --> AM[Commande enregistrée\nStatut: PAYEE]
    AM --> AN[Email de confirmation\nenvoyé à l'acheteur]
    AN --> AO[Email de notification\nenvoyé au vendeur]
    AO --> AP[Affichage page de succès\nRécapitulatif + N° de commande]

    AP --> AQ[Accède à /mes-commandes\nSuivi de l'état]

    AQ --> AR{Statut\ncommande ?}
    AR -- EN_PREPARATION --> AS[Affiche timeline\nEn préparation]
    AR -- EXPEDIEE --> AT[Affiche numéro\nde suivi + lien transporteur]
    AR -- LIVREE --> AU{Laisser\nun avis ?}
    AU -- Oui --> AV[/Rédige avis\n(note 1-5 + commentaire)/]
    AV --> AW[Avis soumis et\nen attente de modération]
    AU -- Non --> AX([Fin du parcours])
    AW --> AX

    AS --> AQ
    AT --> AQ

    style A fill:#4CAF50,color:#fff,stroke:#388E3C
    style Z1 fill:#f44336,color:#fff,stroke:#c62828
    style AL fill:#f44336,color:#fff,stroke:#c62828
    style AX fill:#4CAF50,color:#fff,stroke:#388E3C
    style AM fill:#2196F3,color:#fff,stroke:#1565C0
    style AI fill:#FF9800,color:#fff,stroke:#E65100
    style M fill:#9C27B0,color:#fff,stroke:#6A1B9A
    style X fill:#FF9800,color:#fff,stroke:#E65100
```

## Légende

| Symbole | Signification |
|---------|---------------|
| Ovale vert `([...])` | Début ou fin du parcours |
| Rectangle `[...]` | Action ou état du système |
| Losange `{...}` | Point de décision (oui/non ou choix multiple) |
| Parallélogramme `/.../ ` | Saisie utilisateur |
| Flèche `-- texte -->` | Transition avec condition |
| Boucle `→ B` | Retour en arrière dans le parcours |

### Couleurs
| Couleur | Signification |
|---------|---------------|
| Vert | Début / Fin positive |
| Rouge | Abandon du parcours |
| Bleu | Action clé (commande créée) |
| Orange | Point de décision critique |
| Violet | Vérification d'authentification |

### Points critiques du parcours
1. **Authentification** : barrier obligatoire avant ajout au panier
2. **Vérification stock** : contrôle côté serveur avant création de commande
3. **Paiement** : l'utilisateur peut réessayer avec une autre carte
4. **Suivi** : le parcours continue après achat avec le suivi et les avis
