# Diagramme de Séquence - Authentification JWT

Description : Ce diagramme décrit en détail le mécanisme d'authentification JWT de MarketCraft, couvrant l'inscription, la connexion, la validation de token sur chaque requête protégée, et le mécanisme de refresh token pour renouveler la session sans redemander le mot de passe.

```mermaid
sequenceDiagram
    actor Utilisateur
    participant Client as Client (React App)
    participant AuthController as Auth Controller
    participant Middleware as JWT Middleware
    participant DB as Base de données
    participant EmailService as Email Service

    %% ─────────────────────────────────────────────
    %% 1. INSCRIPTION (REGISTER)
    %% ─────────────────────────────────────────────
    rect rgb(230, 245, 255)
        Note over Utilisateur, EmailService: Étape 1 — Inscription et vérification email
        Utilisateur->>Client: Remplit formulaire inscription
        Note right of Client: {nom, prenom, email, password, confirmPassword}
        Client->>Client: Validation front-end (format email, force mot de passe)

        Client->>AuthController: POST /api/auth/register {nom, prenom, email, password}
        AuthController->>DB: SELECT * FROM utilisateurs WHERE email = ?
        DB-->>AuthController: Résultat (vide = email libre)

        alt Email déjà utilisé
            AuthController-->>Client: 409 { erreur: "Cet email est déjà enregistré" }
            Client-->>Utilisateur: "Un compte existe déjà avec cet email"
        else Email disponible
            AuthController->>AuthController: bcrypt.hash(password, 12)
            Note right of AuthController: Coût 12 = ~250ms, résistant aux GPUs
            AuthController->>AuthController: crypto.randomBytes(32) → tokenVerification
            AuthController->>DB: INSERT utilisateur {nom, prenom, email, hash, tokenVerif, estActif: false}
            DB-->>AuthController: utilisateur.id = 5

            AuthController->>EmailService: sendVerificationEmail({ email, token: tokenVerification })
            EmailService-->>Utilisateur: Email "Confirmez votre adresse email" (lien 24h)
            AuthController-->>Client: 201 { message: "Vérifiez votre email pour activer votre compte" }
            Client-->>Utilisateur: Page "Email de confirmation envoyé"
        end

        Note over Utilisateur, EmailService: L'utilisateur clique sur le lien dans l'email
        Utilisateur->>Client: GET /verify-email?token=abc123xyz
        Client->>AuthController: GET /api/auth/verify-email?token=abc123xyz
        AuthController->>DB: SELECT utilisateur WHERE token_verification=? AND created_at > NOW()-24h
        DB-->>AuthController: Utilisateur trouvé
        AuthController->>DB: UPDATE utilisateur SET est_actif=true, token_verification=NULL
        AuthController-->>Client: 200 { message: "Email confirmé, vous pouvez vous connecter" }
        Client-->>Utilisateur: Redirige vers /connexion avec message de succès
    end

    %% ─────────────────────────────────────────────
    %% 2. CONNEXION (LOGIN) + GÉNÉRATION JWT
    %% ─────────────────────────────────────────────
    rect rgb(230, 255, 237)
        Note over Utilisateur, EmailService: Étape 2 — Connexion et génération des tokens JWT
        Utilisateur->>Client: Saisit email + mot de passe
        Client->>AuthController: POST /api/auth/login {email, password}

        AuthController->>DB: SELECT * FROM utilisateurs WHERE email = ?
        DB-->>AuthController: { id, nom, email, hash, role, est_actif }

        alt Utilisateur non trouvé ou inactif
            AuthController-->>Client: 401 { erreur: "Identifiants invalides" }
            Note right of AuthController: Message volontairement générique (sécurité)
        else Utilisateur trouvé et actif
            AuthController->>AuthController: bcrypt.compare(password, hash)
            alt Mot de passe incorrect
                AuthController->>DB: INCREMENT tentatives_echec WHERE id=?
                AuthController->>AuthController: Si tentatives >= 5 : verrouiller 15min
                AuthController-->>Client: 401 { erreur: "Identifiants invalides" }
            else Mot de passe correct
                AuthController->>DB: RESET tentatives_echec = 0
                AuthController->>AuthController: jwt.sign({id, email, role}, ACCESS_SECRET, {expiresIn: '15m'})
                Note right of AuthController: accessToken court (15min) — stocké en mémoire
                AuthController->>AuthController: jwt.sign({id}, REFRESH_SECRET, {expiresIn: '7d'})
                Note right of AuthController: refreshToken long (7j) — stocké en cookie httpOnly
                AuthController->>DB: INSERT refresh_tokens {token, userId, expire_at}
                AuthController->>DB: UPDATE utilisateurs SET derniere_connexion=NOW()
                AuthController-->>Client: 200 { accessToken, user: {id, nom, role} }
                Note right of AuthController: refreshToken en Set-Cookie httpOnly; Secure; SameSite=Strict
                Client->>Client: Stocke accessToken en variable React (mémoire uniquement)
                Client-->>Utilisateur: Redirige vers tableau de bord
            end
        end
    end

    %% ─────────────────────────────────────────────
    %% 3. REQUÊTE AUTHENTIFIÉE
    %% ─────────────────────────────────────────────
    rect rgb(255, 250, 225)
        Note over Utilisateur, EmailService: Étape 3 — Requête protégée avec JWT dans le header
        Utilisateur->>Client: Accède à une ressource protégée (ex: /tableau-de-bord)
        Client->>Middleware: GET /api/commandes {Authorization: "Bearer eyJhbGci..."}
        Middleware->>Middleware: Extrait token du header Authorization

        alt Token absent
            Middleware-->>Client: 401 { erreur: "Token manquant" }
        else Token présent
            Middleware->>Middleware: jwt.verify(token, ACCESS_SECRET)
            alt Token valide
                Middleware->>Middleware: Décode payload {id: 5, email, role, exp: ...}
                Middleware->>DB: SELECT utilisateur WHERE id=5 AND est_actif=true
                DB-->>Middleware: Utilisateur actif confirmé
                Middleware->>Middleware: Attache req.user = { id, role }
                Note right of Middleware: Passe au controller suivant
                Middleware-->>Client: Accès autorisé → données retournées
            else Token invalide (signature incorrecte)
                Middleware-->>Client: 401 { erreur: "Token invalide" }
            end
        end
    end

    %% ─────────────────────────────────────────────
    %% 4. TOKEN EXPIRÉ → REFRESH
    %% ─────────────────────────────────────────────
    rect rgb(255, 237, 237)
        Note over Utilisateur, EmailService: Étape 4 — Token expiré et mécanisme de refresh
        Client->>Middleware: GET /api/commandes {Authorization: "Bearer [token expiré]"}
        Middleware->>Middleware: jwt.verify() → TokenExpiredError

        Middleware-->>Client: 401 { erreur: "TokenExpired", code: 'TOKEN_EXPIRED' }

        Client->>Client: Intercepteur Axios détecte code TOKEN_EXPIRED
        Client->>AuthController: POST /api/auth/refresh
        Note right of Client: Le refreshToken est envoyé automatiquement via le cookie httpOnly

        AuthController->>AuthController: Lit refreshToken depuis req.cookies
        AuthController->>DB: SELECT * FROM refresh_tokens WHERE token=? AND expire_at > NOW()
        DB-->>AuthController: Token de refresh valide

        alt RefreshToken valide et non révoqué
            AuthController->>AuthController: jwt.verify(refreshToken, REFRESH_SECRET)
            AuthController->>AuthController: jwt.sign({id, email, role}, ACCESS_SECRET, {expiresIn: '15m'})
            AuthController->>DB: Rotation : DELETE ancien refreshToken, INSERT nouveau
            AuthController-->>Client: 200 { accessToken: "[nouveau token]" }
            Note right of AuthController: Nouveau refreshToken en cookie httpOnly
            Client->>Client: Remplace accessToken en mémoire
            Client->>Middleware: Rejoue la requête initiale avec le nouveau token
            Middleware-->>Client: Données de la ressource demandée
            Client-->>Utilisateur: Contenu affiché (transparent pour l'utilisateur)
        else RefreshToken expiré ou révoqué
            AuthController->>DB: DELETE FROM refresh_tokens WHERE userId=?
            AuthController-->>Client: 401 { erreur: "Session expirée, reconnectez-vous" }
            Client->>Client: Vide le state utilisateur
            Client-->>Utilisateur: Redirige vers /connexion
        end
    end

    %% ─────────────────────────────────────────────
    %% 5. DÉCONNEXION
    %% ─────────────────────────────────────────────
    rect rgb(245, 230, 255)
        Note over Utilisateur, EmailService: Étape 5 — Déconnexion sécurisée
        Utilisateur->>Client: Clique "Se déconnecter"
        Client->>AuthController: POST /api/auth/logout
        AuthController->>DB: DELETE FROM refresh_tokens WHERE token = [cookie]
        DB-->>AuthController: Token révoqué
        AuthController-->>Client: 200 + Set-Cookie: refreshToken=; Max-Age=0; httpOnly
        Client->>Client: Vide accessToken de la mémoire
        Client->>Client: Vide le contexte utilisateur React
        Client-->>Utilisateur: Redirige vers /accueil
    end
```

## Légende

| Élément | Signification |
|---------|---------------|
| `accessToken` | JWT de courte durée (15 min), stocké en mémoire JavaScript |
| `refreshToken` | JWT longue durée (7 jours), stocké en cookie httpOnly inaccessible au JS |
| `bcrypt.hash(pwd, 12)` | Hachage avec sel, facteur de coût 12 (~250ms délibérément) |
| `jwt.sign(payload, secret, options)` | Génération d'un JWT signé avec HMAC-SHA256 |
| `jwt.verify(token, secret)` | Vérification de la signature et de l'expiration |
| `httpOnly` | Cookie non accessible via `document.cookie` — protège des XSS |
| `SameSite=Strict` | Protège contre les attaques CSRF |
| `Token Rotation` | À chaque refresh, l'ancien refreshToken est invalidé |

### Stratégie de sécurité
| Menace | Contre-mesure |
|--------|---------------|
| Vol d'accessToken | Durée de vie 15 min seulement |
| XSS | refreshToken en cookie httpOnly |
| CSRF | SameSite=Strict + vérification Origin |
| Brute force | Verrouillage après 5 tentatives (15 min) |
| Réutilisation de refreshToken | Rotation + révocation en base |
