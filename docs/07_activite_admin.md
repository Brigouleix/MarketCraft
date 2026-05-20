# Diagramme d'Activité - Administration

Description : Ce diagramme décrit le rôle de l'administrateur MarketCraft dans la supervision de la plateforme : validation des boutiques, gestion des litiges, modération des utilisateurs et génération des rapports de performance.

```mermaid
flowchart TD
    A([Admin se connecte\nà /admin]) --> B{Authentification\nréussie ?}
    B -- Non --> C[Affiche erreur\nConnexion refusée]
    C --> A
    B -- Oui, rôle ADMIN --> D[Dashboard administrateur\nVue d'ensemble de la plateforme]

    D --> E[Affichage KPIs\nVentes, utilisateurs, boutiques, litiges]

    E --> F{Quelle section\nadministrative ?}

    %% ─── VALIDATION BOUTIQUES ───
    F -- Boutiques en attente --> G[Liste boutiques\nStatut: EN_ATTENTE]
    G --> H{Boutiques\nen file d'attente ?}
    H -- Non --> F
    H -- Oui --> I[Sélectionne une boutique\nà examiner]
    I --> J[Consulte le dossier complet\nNom, description, SIRET, photos, vendeur]
    J --> K[Vérifie les informations\nSIRET valide + cohérence dossier]
    K --> L{Décision\nsur la boutique}
    L -- Approuver --> M[Boutique ACTIVE\nEmail envoyé au vendeur]
    L -- Demander des infos --> N[Envoie message\nau vendeur via messagerie]
    N --> O{Vendeur\nrépond ?}
    O -- Non, 7j --> P[Boutique mise\nen ATTENTE_PROLONGEE]
    O -- Oui --> J
    L -- Rejeter --> Q[/Saisit motif du rejet/]
    Q --> R[Boutique REJETEE\nEmail avec motif envoyé au vendeur]
    M --> G
    R --> G
    P --> G

    %% ─── GESTION LITIGES ───
    F -- Litiges --> S[Liste des litiges\nouverts par les acheteurs]
    S --> T{Litiges\nen cours ?}
    T -- Non --> F
    T -- Oui --> U[Sélectionne un litige]
    U --> V[Consulte historique\nMessages, commande, preuves photos]
    V --> W[Contacte acheteur\net vendeur pour précisions]
    W --> X{Décision\nsur le litige}
    X -- En faveur acheteur --> Y[Ordonne remboursement\nStripe refund déclenché]
    X -- En faveur vendeur --> Z[Clôture litige\nPaiement libéré au vendeur]
    X -- Partage --> AA[Remboursement partiel\nNégociation des proportions]
    Y --> AB[Email aux deux parties\nExplication de la décision]
    Z --> AB
    AA --> AB
    AB --> AC{Sanction\nnécessaire ?}
    AC -- Oui --> AD[Sanctionne l'utilisateur\nAvertissement / Suspension]
    AC -- Non --> S
    AD --> S

    %% ─── GESTION UTILISATEURS ───
    F -- Utilisateurs --> AE[Liste des utilisateurs\nAvec filtres: rôle, statut, date]
    AE --> AF{Action\nsouhaitée ?}
    AF -- Rechercher --> AG[/Recherche par nom, email/]
    AG --> AH[Affiche profil utilisateur\nHistorique, commandes, avis]
    AH --> AI{Action sur\nle compte}
    AI -- Avertissement --> AJ[Envoie avertissement\nEmail officiel + note interne]
    AI -- Bannir temporairement --> AK[/Saisit durée du ban\net motif/]
    AK --> AL[Compte suspendu\nAccès bloqué]
    AL --> AM[Email envoyé\nau utilisateur banni]
    AI -- Bannir définitivement --> AN[Compte désactivé définitivement\nDonnées anonymisées si demande RGPD]
    AI -- Débannir --> AO[Compte réactivé\nEmail de réactivation envoyé]
    AJ --> AE
    AM --> AE
    AN --> AE
    AO --> AE
    AF -- Retour dashboard --> F

    %% ─── AVIS ET MODÉRATION ───
    F -- Avis signalés --> AP[Liste des avis\nsignalés par les vendeurs]
    AP --> AQ[Consulte l'avis signalé\nContexte + commande associée]
    AQ --> AR{L'avis viole\nles règles ?}
    AR -- Non --> AS[Maintient l'avis\nNotifie le vendeur]
    AR -- Oui --> AT[Supprime ou masque l'avis\nNotifie l'auteur]
    AS --> AP
    AT --> AP

    %% ─── STATISTIQUES & RAPPORTS ───
    F -- Statistiques --> AU[Tableau de bord analytique\nCA, commandes, utilisateurs actifs]
    AU --> AV[Graphiques temporels\nJour / Semaine / Mois / Année]
    AV --> AW[Métriques boutiques\nTop vendeurs, taux de conversion]
    AW --> AX[Métriques acheteurs\nRétention, panier moyen, fréquence]
    AX --> AY{Exporter\nle rapport ?}
    AY -- Oui --> AZ[/Choisit format\nCSV / PDF / Excel/]
    AZ --> BA[Rapport généré\net téléchargé]
    AY -- Non --> F
    BA --> F

    style A fill:#4CAF50,color:#fff,stroke:#388E3C
    style D fill:#2196F3,color:#fff,stroke:#1565C0
    style L fill:#FF9800,color:#fff,stroke:#E65100
    style X fill:#FF9800,color:#fff,stroke:#E65100
    style AI fill:#FF9800,color:#fff,stroke:#E65100
    style AN fill:#f44336,color:#fff,stroke:#c62828
    style Y fill:#4CAF50,color:#fff,stroke:#388E3C
    style M fill:#4CAF50,color:#fff,stroke:#388E3C
```

## Légende

| Symbole | Signification |
|---------|---------------|
| Ovale vert `([...])` | Entrée dans le module d'administration |
| Rectangle bleu `D` | Hub central (dashboard) |
| Losange orange `{...}` | Décision administrative |
| Rectangle rouge | Action irréversible (ban définitif) |
| Rectangle vert | Action positive (approbation, remboursement) |

### Sections du panneau d'administration
| Section | Responsabilité |
|---------|----------------|
| **Boutiques** | Valider ou rejeter les demandes de création de boutique |
| **Litiges** | Arbitrer les conflits acheteur/vendeur |
| **Utilisateurs** | Gérer les comptes (ban, débannissement, avertissements) |
| **Avis signalés** | Modérer les contenus inappropriés |
| **Statistiques** | Analyser les performances de la plateforme |

### Indicateurs clés (KPIs) du dashboard
- Chiffre d'affaires total du jour / mois / année
- Nombre de nouvelles inscriptions (acheteurs + vendeurs)
- Boutiques en attente de validation
- Litiges ouverts et non résolus
- Taux de conversion global de la plateforme
- Avis en attente de modération
