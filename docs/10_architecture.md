# Diagramme d'Architecture - MarketCraft

Description : Ce diagramme présente l'architecture logicielle complète de MarketCraft, organisée en couches (layers). Il montre comment le frontend React communique avec le backend PHP via une API REST, comment les contrôleurs orchestrent les modèles, et comment les services externes (Stripe, Email) s'intègrent dans le système.

```mermaid
graph TB
    subgraph CLIENT["Couche Présentation — React SPA"]
        direction TB

        subgraph PAGES["Pages (React Router)"]
            P1[Accueil]
            P2[Catalogue / Recherche]
            P3[Fiche Produit]
            P4[Panier / Checkout]
            P5[Tableau de bord Vendeur]
            P6[Tableau de bord Admin]
            P7[Profil / Commandes]
        end

        subgraph COMPOSANTS["Composants Réutilisables"]
            C1[ProductCard]
            C2[CartItem]
            C3[ReviewForm]
            C4[ImageGallery]
            C5[OrderTimeline]
            C6[ProtectedRoute]
        end

        subgraph CONTEXTES["Contextes React (State Global)"]
            CTX1[AuthContext\nJWT + User]
            CTX2[CartContext\nlocalStorage]
            CTX3[NotificationContext\nToasts]
        end

        subgraph HOOKS["Hooks Personnalisés"]
            H1[useAuth]
            H2[useCart]
            H3[usePagination]
            H4[useDebounce]
        end
    end

    subgraph API_LAYER["Couche API — Services Axios"]
        direction LR
        AX1[authService\nlogin/register/refresh]
        AX2[productService\nCRUD produits]
        AX3[orderService\ncommandes]
        AX4[boutiqueService\nCRUD boutiques]
        AX5[adminService\nvalidation / litiges]
        AX6[axiosInstance\nIntercepteurs JWT]
    end

    subgraph BACKEND["Couche Backend — PHP MVC"]
        direction TB

        subgraph ROUTES["Router (routes/web.php)"]
            R1["/api/auth/*"]
            R2["/api/produits/*"]
            R3["/api/commandes/*"]
            R4["/api/boutiques/*"]
            R5["/api/admin/*"]
            R6["/api/avis/*"]
        end

        subgraph MIDDLEWARE["Middlewares"]
            MW1[JWTMiddleware\nValidation token]
            MW2[RoleMiddleware\nACL par rôle]
            MW3[RateLimitMiddleware\n100 req/min]
            MW4[CORSMiddleware\nOrigines autorisées]
        end

        subgraph CONTROLLERS["Controllers"]
            CT1[AuthController\nlogin/register/refresh/logout]
            CT2[ProductController\nCRUD + search + upload]
            CT3[OrderController\ncréation + statuts]
            CT4[BoutiqueController\nCRUD + validation admin]
            CT5[AvisController\nCRUD + modération]
            CT6[AdminController\ndashboard + ban + litiges]
            CT7[PaymentController\nStripe webhooks]
        end

        subgraph MODELS["Models (Active Record)"]
            M1[User\n→ utilisateurs]
            M2[Boutique\n→ boutiques]
            M3[Product\n→ produits]
            M4[Order\n→ commandes]
            M5[OrderLine\n→ lignes_commande]
            M6[Payment\n→ paiements]
            M7[Review\n→ avis]
            M8[Category\n→ categories]
        end

        subgraph UTILS["Utilitaires"]
            U1[JWTHelper\nsign/verify/refresh]
            U2[Mailer\nSMTP wrapper]
            U3[ImageProcessor\nresize/compress/webp]
            U4[Validator\nrègles de validation]
            U5[Paginator\noffset/limit]
        end
    end

    subgraph DATA["Couche Données"]
        DB[(MySQL 8\nBase de données\nprincipale)]
        REDIS[(Redis\nCache sessions\n+ rate limiting)]
    end

    subgraph EXTERNAL["Services Externes"]
        STRIPE[Stripe API\nPaiements + Webhooks]
        SMTP[Mailgun SMTP\nEmails transactionnels]
        CDN[CDN CloudFront\nAssets statiques\n+ images produits]
    end

    %% ─── FLUX PRÉSENTATION → API ───
    PAGES --> HOOKS
    HOOKS --> CONTEXTES
    CONTEXTES --> AX6
    AX6 --> AX1 & AX2 & AX3 & AX4 & AX5
    COMPOSANTS --> HOOKS

    %% ─── FLUX API → BACKEND ───
    AX1 --> R1
    AX2 --> R2
    AX3 --> R3
    AX4 --> R4
    AX5 --> R5

    %% ─── FLUX ROUTER → MIDDLEWARE → CONTROLLER ───
    R1 & R2 & R3 & R4 & R5 & R6 --> MW4
    MW4 --> MW3
    MW3 --> MW1
    MW1 --> MW2
    MW2 --> CT1 & CT2 & CT3 & CT4 & CT5 & CT6 & CT7

    %% ─── FLUX CONTROLLER → MODEL ───
    CT1 --> M1
    CT2 --> M3 & M8
    CT3 --> M4 & M5 & M3
    CT4 --> M2
    CT5 --> M7 & M3
    CT6 --> M1 & M2 & M4
    CT7 --> M6 & M4

    %% ─── FLUX MODEL → DB ───
    M1 & M2 & M3 & M4 & M5 & M6 & M7 & M8 --> DB

    %% ─── FLUX UTILS ───
    CT1 --> U1
    CT2 --> U3 & U4
    CT3 --> U4
    U1 --> REDIS
    MW3 --> REDIS

    %% ─── SERVICES EXTERNES ───
    CT7 --> STRIPE
    STRIPE -.->|Webhook événements| CT7
    U2 --> SMTP
    SMTP -.->|Emails| CLIENT
    CDN -.->|Assets CSS/JS/Images| CLIENT

    %% ─── STYLES ───
    style CLIENT fill:#E3F2FD,stroke:#1565C0,stroke-width:2px
    style BACKEND fill:#E8F5E9,stroke:#2E7D32,stroke-width:2px
    style DATA fill:#FFF3E0,stroke:#E65100,stroke-width:2px
    style EXTERNAL fill:#F3E5F5,stroke:#6A1B9A,stroke-width:2px
    style API_LAYER fill:#FCE4EC,stroke:#C62828,stroke-width:2px
    style DB fill:#FF9800,color:#fff,stroke:#E65100
    style REDIS fill:#FF5722,color:#fff,stroke:#BF360C
    style STRIPE fill:#635BFF,color:#fff,stroke:#483BCC
    style SMTP fill:#EA4335,color:#fff,stroke:#B31412
    style CDN fill:#FF9900,color:#fff,stroke:#CC7A00
```

## Légende

### Couches architecturales
| Couche | Couleur | Technologie | Responsabilité |
|--------|---------|-------------|----------------|
| **Présentation** | Bleu | React 18 + Tailwind CSS | Interface utilisateur, état local, routing |
| **API Layer** | Rose | Axios + Intercepteurs | Communication HTTP, gestion des tokens |
| **Backend** | Vert | PHP 8.2 + MVC custom | Logique métier, validation, orchestration |
| **Données** | Orange | MySQL 8 + Redis | Persistance, cache, sessions |
| **Services externes** | Violet | Stripe, Mailgun, CDN | Paiement, email, assets |

### Flux de données principaux
| Flux | Description |
|------|-------------|
| `Contextes → axiosInstance` | Tous les appels API passent par l'instance centralisée qui injecte le JWT |
| `MW1 → MW2` | Toute requête est d'abord authentifiée puis ses permissions sont vérifiées |
| `Controller → Utils` | Les contrôleurs délèguent les opérations techniques aux utilitaires |
| `Stripe → Webhook → CT7` | Stripe notifie le backend des événements de paiement en temps réel |
| `REDIS ← MW3` | Le rate limiting utilise Redis pour compter les requêtes par IP |

### Principes d'architecture
- **Séparation des préoccupations** : chaque couche a une responsabilité unique
- **Stateless API** : le backend ne stocke pas l'état de session (JWT)
- **Pattern Repository** implicite dans les modèles (Active Record)
- **Intercepteurs Axios** : gestion centralisée du refresh token
- **Snapshots de prix** : les prix sont copiés dans `lignes_commande` pour l'immuabilité historique
