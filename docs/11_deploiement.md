# Diagramme de Déploiement - MarketCraft

Description : Ce diagramme représente l'infrastructure physique et logique de déploiement de MarketCraft en production. Il montre comment les différents nœuds matériels et logiciels communiquent entre eux, les protocoles utilisés, et l'organisation en zones de sécurité (DMZ, réseau interne).

```mermaid
graph TB
    subgraph INTERNET["Internet"]
        USER[Navigateur Client\nChrome / Firefox / Safari\nReact SPA]
        MOBILE[Application Mobile\niOS / Android\nReact Native]
    end

    subgraph DMZ["DMZ — Zone Démilitarisée"]
        direction TB

        CDN["CDN CloudFront\nAssets statiques\nbuild React, images produits\nCSS, JS, WebP\n— Cache 30 jours —"]

        subgraph NGINX_BOX["Serveur Nginx (Reverse Proxy)"]
            NGINX[Nginx 1.25\nPort 443 HTTPS\nSSL/TLS 1.3\nLet's Encrypt]
            NGINX_HTTP[Nginx\nPort 80 HTTP\nRedirection 301 → HTTPS]
            RATELIMIT[Rate Limiting\n100 req/s par IP\nDDOS protection]
        end
    end

    subgraph BACKEND_ZONE["Zone Backend — Réseau Privé"]
        direction TB

        subgraph APP_SERVER["Serveur Applicatif"]
            PHP_FPM[PHP-FPM 8.2\nProcess Manager\n16 workers\nPort 9000]
            subgraph PHP_APP["Application PHP MVC"]
                ROUTER[Router\nroutes/web.php]
                CONTROLLERS[Controllers\nAuth / Product / Order\nBoutique / Admin]
                MODELS[Models\nActive Record]
                UTILS[Utils\nMailer / JWT / ImageProcessor]
            end
        end

        subgraph CACHE_SERVER["Serveur Cache"]
            REDIS[Redis 7\nPort 6379\nSessions + Rate Limiting\nCache requêtes fréquentes\nTTL configurable]
        end
    end

    subgraph DB_ZONE["Zone Base de Données — Réseau Isolé"]
        direction LR

        subgraph DB_PRIMARY["Serveur DB Primaire"]
            MYSQL_PRIMARY[(MySQL 8.0 Primary\nPort 3306\nEcriture / Lecture\nInnoDB Engine)]
        end

        subgraph DB_REPLICA["Serveur DB Réplica (Lecture)"]
            MYSQL_REPLICA[(MySQL 8.0 Replica\nPort 3306\nLecture seule\nRéplication asynchrone)]
        end

        MYSQL_BACKUP[Backup automatique\nMySQLdump quotidien\nRetention 30j\nStockage S3]
    end

    subgraph STORAGE["Stockage Fichiers"]
        S3[AWS S3 / Compatible\nImages produits\nLogos boutiques\nBannieres\nJPEG/WEBP originals]
    end

    subgraph EXTERNAL["Services Tiers Externes"]
        STRIPE_API[Stripe API\nhttps://api.stripe.com\nPaiements CB\nWebhooks HTTPS]
        MAILGUN[Mailgun SMTP\nsmtp.mailgun.org:587\nEmails transactionnels\nSPF + DKIM configurés]
    end

    subgraph MONITORING["Supervision & Logs"]
        LOGS[Centralized Logs\nNginx access.log\nPHP error.log\nApplication logs]
        METRICS[Métriques serveur\nCPU / RAM / Disque\nRéponse API p95/p99]
    end

    %% ─── CONNEXIONS INTERNET → DMZ ───
    USER -->|"HTTPS :443\nHTTP/2"| CDN
    USER -->|"HTTPS :443\nHTTP/2"| NGINX
    MOBILE -->|"HTTPS :443\nHTTP/2"| NGINX
    USER -->|"HTTP :80"| NGINX_HTTP
    NGINX_HTTP -->|"301 Redirect"| NGINX

    %% ─── CDN ───
    CDN -->|"Origin Pull\nHTTPS"| S3

    %% ─── DMZ → BACKEND ───
    NGINX -->|"FastCGI\nfcgi://localhost:9000"| PHP_FPM
    NGINX --> RATELIMIT

    %% ─── BACKEND INTERNE ───
    PHP_FPM --> PHP_APP
    PHP_APP --> ROUTER
    ROUTER --> CONTROLLERS
    CONTROLLERS --> MODELS
    CONTROLLERS --> UTILS

    %% ─── BACKEND → CACHE ───
    CONTROLLERS -->|"TCP :6379\nRéseau privé"| REDIS
    NGINX -->|"Rate limit check"| REDIS

    %% ─── BACKEND → BASE DE DONNÉES ───
    MODELS -->|"MySQL :3306\nEcriture\nRéseau isolé"| MYSQL_PRIMARY
    MODELS -->|"MySQL :3306\nLecture seule"| MYSQL_REPLICA
    MYSQL_PRIMARY -->|"Binlog\nRéplication"| MYSQL_REPLICA
    MYSQL_PRIMARY -->|"Backup nocturne\n02:00 UTC"| MYSQL_BACKUP

    %% ─── BACKEND → STOCKAGE ───
    UTILS -->|"AWS SDK\nPUT objects HTTPS"| S3

    %% ─── SERVICES EXTERNES ───
    CONTROLLERS -->|"HTTPS POST\nAPI Stripe"| STRIPE_API
    STRIPE_API -->|"Webhook HTTPS POST\n/api/webhooks/stripe"| NGINX
    UTILS -->|"SMTP TLS :587"| MAILGUN
    MAILGUN -.->|"Email livré"| USER

    %% ─── MONITORING ───
    PHP_FPM --> LOGS
    NGINX --> LOGS
    PHP_FPM --> METRICS
    MYSQL_PRIMARY --> METRICS

    %% ─── STYLES ───
    style INTERNET fill:#E3F2FD,stroke:#1565C0,stroke-width:2px
    style DMZ fill:#FFF8E1,stroke:#F57F17,stroke-width:2px
    style BACKEND_ZONE fill:#E8F5E9,stroke:#2E7D32,stroke-width:2px
    style DB_ZONE fill:#FCE4EC,stroke:#C62828,stroke-width:2px
    style STORAGE fill:#F3E5F5,stroke:#6A1B9A,stroke-width:2px
    style EXTERNAL fill:#E0F7FA,stroke:#006064,stroke-width:2px
    style MONITORING fill:#EFEBE9,stroke:#4E342E,stroke-width:2px

    style USER fill:#2196F3,color:#fff,stroke:#1565C0
    style MOBILE fill:#2196F3,color:#fff,stroke:#1565C0
    style CDN fill:#FF9900,color:#fff,stroke:#CC7A00
    style NGINX fill:#009639,color:#fff,stroke:#005A23
    style PHP_FPM fill:#777BB4,color:#fff,stroke:#4A4D8C
    style REDIS fill:#DC382D,color:#fff,stroke:#A32821
    style MYSQL_PRIMARY fill:#4479A1,color:#fff,stroke:#2D5A7A
    style MYSQL_REPLICA fill:#6B9FC1,color:#fff,stroke:#4479A1
    style S3 fill:#FF9900,color:#fff,stroke:#CC7A00
    style STRIPE_API fill:#635BFF,color:#fff,stroke:#483BCC
    style MAILGUN fill:#F06B26,color:#fff,stroke:#B84E1A
    style MYSQL_BACKUP fill:#78909C,color:#fff,stroke:#546E7A
```

## Légende

### Zones de sécurité réseau
| Zone | Description | Accès |
|------|-------------|-------|
| **Internet** | Clients finaux (navigateurs, mobiles) | Public |
| **DMZ** | Reverse proxy exposé publiquement | Port 80/443 uniquement |
| **Zone Backend** | Serveurs applicatifs PHP + Redis | Depuis DMZ uniquement |
| **Zone DB** | Bases de données MySQL | Depuis Backend uniquement |
| **Stockage** | Fichiers binaires S3 | Backend (écriture) + CDN (lecture) |
| **Externe** | APIs tierces (Stripe, Mailgun) | Sortant depuis Backend |

### Composants d'infrastructure
| Composant | Version | Port | Rôle |
|-----------|---------|------|------|
| **Nginx** | 1.25 | 443 (HTTPS) | Reverse proxy, SSL termination, compression gzip |
| **PHP-FPM** | 8.2 | 9000 (FastCGI) | Exécution de l'application PHP |
| **MySQL Primary** | 8.0 | 3306 | Base de données principale (lectures + écritures) |
| **MySQL Replica** | 8.0 | 3306 | Réplique lecture (scalabilité horizontale) |
| **Redis** | 7 | 6379 | Cache sessions, rate limiting, cache requêtes |
| **AWS S3** | — | HTTPS | Stockage objet pour les fichiers uploadés |
| **CloudFront CDN** | — | 443 | Distribution des assets statiques |

### Protocoles de communication
| Connexion | Protocole | Sécurité |
|-----------|-----------|----------|
| Client → Nginx | HTTPS / HTTP2 | TLS 1.3, certificat Let's Encrypt |
| Nginx → PHP-FPM | FastCGI | Réseau local (127.0.0.1) |
| PHP → Redis | TCP | Réseau privé interne |
| PHP → MySQL | TCP MySQL | Réseau isolé, authentification |
| PHP → Stripe | HTTPS REST | TLS 1.2+, clé API secrète |
| PHP → Mailgun | SMTP TLS | Port 587, authentification SMTP |
| Stripe → Nginx | HTTPS Webhook | Signature HMAC vérifiée |
