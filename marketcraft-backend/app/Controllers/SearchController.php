<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Config\Database;

/**
 * SearchController – Recherche produits assistée par un modèle de langage.
 *
 * Le fournisseur est Groq, dont l'API est compatible OpenAI : l'URL et le
 * modèle sont configurables dans le .env, ce qui permet de basculer vers un
 * autre service compatible (Mistral, OpenRouter…) sans toucher au code.
 *
 * En cas d'indisponibilité — clé absente, quota atteint, réseau — la recherche
 * bascule sur une extraction de mots-clés locale : la fonctionnalité se
 * dégrade mais ne casse jamais.
 *
 * Routes :
 *   POST /search/ai  – Recherche en langage naturel
 *   GET  /search     – Recherche simple par mots-clés
 */
class SearchController extends Controller
{
    // Nombre maximum de résultats retournés
    private const MAX_RESULTS = 20;

    // Endpoint par défaut : Groq, compatible avec le format OpenAI.
    private const DEFAULT_API_URL = 'https://api.groq.com/openai/v1/chat/completions';

    // Modèle par défaut, disponible sur le palier gratuit de Groq.
    // Attention : Groq retire regulierement des modeles (llama-3.3-70b-versatile
    // a ete deprecie le 17/06/2026). En cas de 400 « model_decommissioned »,
    // consulter console.groq.com/docs/deprecations et surcharger GROQ_MODEL
    // dans le .env — aucune modification de code n'est necessaire.
    private const DEFAULT_MODEL = 'openai/gpt-oss-120b';

    // Nombre maximum de tokens pour la réponse du modèle
    private const MAX_TOKENS = 512;

    // Préfixe attendu des clés Groq, pour distinguer une clé réelle d'une
    // valeur d'exemple laissée dans le .env.
    private const KEY_PREFIX = 'gsk_';

    /**
     * Motif du dernier échec de l'appel IA. Exposé dans la réponse HTTP
     * uniquement en développement, pour éviter d'aller fouiller les logs.
     */
    private ?string $iaErreur = null;

    // -------------------------------------------------------------------------
    // POST /search/ai
    // -------------------------------------------------------------------------

    /**
     * Recherche IA : interprète la requête en langage naturel via le modèle,
     * extrait des mots-clés et interroge la base de données produits.
     */
    public function aiSearch(array $params = []): void
    {
        $body  = $this->getBody();
        $query = trim($body['query'] ?? '');

        // Validation de la requête
        if ($query === '') {
            $this->error('Le champ "query" est obligatoire.', 422);
            return;
        }

        if (mb_strlen($query) > 500) {
            $this->error('La requête ne doit pas dépasser 500 caractères.', 422);
            return;
        }

        // Tentative d'extraction via le modèle de langage
        $aiResult = $this->callAiApi($query);

        if ($aiResult !== null) {
            // Succès : utiliser les mots-clés extraits par le modèle
            $keywords  = $aiResult['keywords']  ?? [];
            $prixMin   = isset($aiResult['prix_min'])  && is_numeric($aiResult['prix_min'])
                ? (float) $aiResult['prix_min']  : null;
            $prixMax   = isset($aiResult['prix_max'])  && is_numeric($aiResult['prix_max'])
                ? (float) $aiResult['prix_max']  : null;
            $aiMessage = $aiResult['message'] ?? 'Voici les résultats pour ta recherche !';
        } else {
            // Fallback : recherche simple avec le texte brut
            $keywords  = $this->extractKeywordsFallback($query);
            $prixMin   = null;
            $prixMax   = null;
            $aiMessage = 'Je cherche des produits correspondant à ta demande !';
        }

        // Recherche en base de données
        $products = $this->searchProducts($keywords, $prixMin, $prixMax);

        $this->json([
            'success' => true,
            'data'    => [
                // Permet au client de distinguer une vraie interprétation IA
                // d'un simple repli sur les mots-clés.
                'ia_active'  => $aiResult !== null,
                // Diagnostic visible uniquement hors production.
                'ia_erreur'  => (($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'production') !== 'production')
                    ? $this->iaErreur : null,
                'ai_message' => $aiMessage,
                'keywords'   => $keywords,
                'products'   => $products,
                'total'      => count($products),
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // GET /search
    // -------------------------------------------------------------------------

    /**
     * Recherche simple (sans IA) : accepte un paramètre GET "q".
     */
    public function search(array $params = []): void
    {
        $query = trim($this->getParam('q', '') ?? '');

        if ($query === '') {
            $this->error('Le paramètre "q" est obligatoire.', 422);
            return;
        }

        $keywords = $this->extractKeywordsFallback($query);
        $products = $this->searchProducts($keywords);

        $this->json([
            'success' => true,
            'data'    => [
                'keywords' => $keywords,
                'products' => $products,
                'total'    => count($products),
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // Appel au modèle de langage (Groq, format OpenAI)
    // -------------------------------------------------------------------------

    /**
     * Envoie la requête utilisateur au modèle et retourne le tableau JSON
     * extrait, ou null en cas d'échec (clé absente, quota, réseau, JSON
     * malformé…). Le motif est alors disponible dans $this->iaErreur.
     *
     * @param string $userQuery Requête en langage naturel de l'utilisateur
     * @return array<string, mixed>|null
     */
    private function callAiApi(string $userQuery): ?array
    {
        $apiKey = trim((string) ($_ENV['GROQ_API_KEY'] ?? getenv('GROQ_API_KEY') ?: ''));
        $apiUrl = trim((string) ($_ENV['GROQ_API_URL'] ?? getenv('GROQ_API_URL') ?: '')) ?: self::DEFAULT_API_URL;
        $model  = trim((string) ($_ENV['GROQ_MODEL']   ?? getenv('GROQ_MODEL')   ?: '')) ?: self::DEFAULT_MODEL;

        // Une valeur d'exemple laissée dans le .env passerait le test « non
        // vide » et provoquerait un 401 : on vérifie aussi le préfixe.
        if ($apiKey === '' || !str_starts_with($apiKey, self::KEY_PREFIX)) {
            $this->iaErreur = $apiKey === ''
                ? 'GROQ_API_KEY absente du .env (ou serveur PHP non redémarré).'
                : 'GROQ_API_KEY présente mais ne commence pas par « ' . self::KEY_PREFIX . ' ».';
            error_log('[SearchController] ' . $this->iaErreur);
            return null;
        }

        // Construction du prompt système
        $systemPrompt = <<<'SYSTEM'
Tu es un assistant e-commerce. Quand l'utilisateur décrit ce qu'il cherche, extrais les mots-clés pertinents pour chercher des produits dans une base de données. Réponds UNIQUEMENT en JSON valide avec cette structure exacte:
{
  "keywords": ["mot1", "mot2", "mot3"],
  "categories": ["categorie1"],
  "prix_max": null,
  "prix_min": null,
  "message": "Message convivial expliquant ce que tu cherches (1 phrase max, tutoyer)"
}
Les keywords doivent être les termes de recherche SQL (noms de matériaux, types d'objets, etc.). Par exemple pour "j'aimerais un ensemble de couverts dorés avec une table en bois": keywords: ["couverts", "dorés", "métal", "table", "bois", "mobilier", "or"], message: "Je cherche des couverts dorés et une table en bois pour toi ! 🎨"
SYSTEM;

        // Corps de la requête au format OpenAI (chat completions).
        // response_format force une sortie JSON stricte, ce qui évite d'avoir
        // à récupérer le JSON au milieu d'un texte libre.
        $requestBody = json_encode([
            'model'           => $model,
            'max_tokens'      => self::MAX_TOKENS,
            'temperature'     => 0.2,
            'response_format' => ['type' => 'json_object'],
            'messages'        => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user',   'content' => $userQuery],
            ],
        ], JSON_UNESCAPED_UNICODE);

        if ($requestBody === false) {
            $this->iaErreur = 'Encodage JSON de la requête impossible.';
            error_log('[SearchController] ' . $this->iaErreur);
            return null;
        }

        $ch = curl_init($apiUrl);

        if ($ch === false) {
            $this->iaErreur = 'Initialisation cURL impossible.';
            error_log('[SearchController] ' . $this->iaErreur);
            return null;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $requestBody,
            CURLOPT_TIMEOUT        => 15, // 15 secondes max
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        // Vérification des erreurs réseau
        if ($response === false || $curlError !== '') {
            // Sous XAMPP/Windows, php.ini ne définit souvent pas curl.cainfo :
            // toute requête HTTPS échoue alors sur la vérification du certificat.
            $this->iaErreur = str_contains($curlError, 'certificate') || str_contains($curlError, 'SSL')
                ? "cURL ne peut pas vérifier le certificat TLS ({$curlError}). Renseignez curl.cainfo dans php.ini."
                : "Appel réseau vers le fournisseur IA échoué : {$curlError}";
            error_log('[SearchController] ' . $this->iaErreur);
            return null;
        }

        // Vérification du code HTTP
        if ($httpCode < 200 || $httpCode >= 300) {
            // Le corps de la réponse contient le motif exact du refus
            // (clé invalide, modèle inconnu, quota dépassé…).
            // 429 = quota du palier gratuit atteint, cas le plus courant.
            $this->iaErreur = match (true) {
                $httpCode === 429 => 'Quota du palier gratuit atteint (429). Réessayez dans une minute.',
                $httpCode === 401 => 'Clé API refusée (401). Vérifiez GROQ_API_KEY.',
                default => "Le fournisseur IA a répondu {$httpCode} pour le modèle « {$model} » : "
                    . substr((string) $response, 0, 300),
            };
            error_log('[SearchController] ' . $this->iaErreur);
            return null;
        }

        $apiResponse = json_decode((string) $response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->iaErreur = 'Réponse du fournisseur IA illisible : ' . json_last_error_msg();
            error_log('[SearchController] ' . $this->iaErreur);
            return null;
        }

        // Format OpenAI : le texte généré se trouve dans choices[0].message.content
        $content = $apiResponse['choices'][0]['message']['content'] ?? null;

        if ($content === null || !is_string($content)) {
            $this->iaErreur = 'Réponse du fournisseur IA sans contenu exploitable.';
            error_log('[SearchController] ' . $this->iaErreur);
            return null;
        }

        // Filet de sécurité si le modèle encadre le JSON de texte libre
        $content = $this->extractJsonFromText($content);

        // Décodage du JSON retourné par le modèle
        $decoded = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            $this->iaErreur = 'Le modèle n\'a pas renvoyé de JSON exploitable : ' . substr($content, 0, 200);
            error_log('[SearchController] ' . $this->iaErreur);
            return null;
        }

        // Validation minimale : doit contenir un tableau keywords
        if (!isset($decoded['keywords']) || !is_array($decoded['keywords'])) {
            $this->iaErreur = 'JSON reçu sans tableau « keywords ».';
            error_log('[SearchController] ' . $this->iaErreur);
            return null;
        }

        return $decoded;
    }

    // -------------------------------------------------------------------------
    // Recherche en base de données
    // -------------------------------------------------------------------------

    /**
     * Interroge la table `produits` avec des LIKE sur chaque mot-clé,
     * en joignant `boutiques` pour récupérer le nom de la boutique.
     *
     * @param string[]          $keywords Mots-clés de recherche
     * @param float|null        $prixMin  Prix minimum (optionnel)
     * @param float|null        $prixMax  Prix maximum (optionnel)
     * @return array<int, array<string, mixed>>
     */
    private function searchProducts(array $keywords, ?float $prixMin = null, ?float $prixMax = null): array
    {
        if (empty($keywords)) {
            return [];
        }

        $pdo = Database::getInstance()->getConnection();

        // Construction dynamique des conditions WHERE avec LIKE sur nom, description et tags
        $conditions = [];
        $bindings   = [];

        foreach ($keywords as $index => $keyword) {
            $keyword = trim((string) $keyword);
            if ($keyword === '') {
                continue;
            }

            $paramNom  = ':kw_nom_'  . $index;
            $paramDesc = ':kw_desc_' . $index;
            $paramTags = ':kw_tags_' . $index;

            // Un produit matche si le mot-clé apparaît dans au moins une des colonnes
            $conditions[] = "(p.nom LIKE {$paramNom} OR p.description LIKE {$paramDesc} OR p.tags LIKE {$paramTags})";

            $likeValue = '%' . $keyword . '%';
            $bindings[$paramNom]  = $likeValue;
            $bindings[$paramDesc] = $likeValue;
            $bindings[$paramTags] = $likeValue;
        }

        // Si tous les mots-clés étaient vides après trim
        if (empty($conditions)) {
            return [];
        }

        // Conditions sur les prix
        $priceConditions = [];
        if ($prixMin !== null) {
            $priceConditions[] = 'p.prix >= :prix_min';
            $bindings[':prix_min'] = $prixMin;
        }
        if ($prixMax !== null) {
            $priceConditions[] = 'p.prix <= :prix_max';
            $bindings[':prix_max'] = $prixMax;
        }

        // Assemblage de la requête SQL
        $whereKeywords = implode(' OR ', $conditions);
        $wherePrice    = !empty($priceConditions) ? ' AND ' . implode(' AND ', $priceConditions) : '';

        $sql = "
            SELECT
                p.id,
                p.nom,
                p.description,
                p.prix,
                p.stock,
                p.images,
                p.tags,
                p.est_fait_main,
                p.created_at,
                b.id   AS boutique_id,
                b.nom  AS boutique_nom
            FROM produits p
            LEFT JOIN boutiques b ON b.id = p.boutique_id
            WHERE ({$whereKeywords}){$wherePrice}
              AND p.stock > 0
            ORDER BY p.created_at DESC
            LIMIT " . self::MAX_RESULTS;

        try {
            $stmt = $pdo->prepare($sql);

            foreach ($bindings as $param => $value) {
                // Les paramètres de prix sont numériques, les LIKE sont des chaînes
                if ($param === ':prix_min' || $param === ':prix_max') {
                    $stmt->bindValue($param, $value, \PDO::PARAM_STR);
                } else {
                    $stmt->bindValue($param, $value, \PDO::PARAM_STR);
                }
            }

            $stmt->execute();
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            // En cas d'erreur SQL, on retourne un tableau vide plutôt que de planter
            return [];
        }
    }

    // -------------------------------------------------------------------------
    // Méthodes utilitaires
    // -------------------------------------------------------------------------

    /**
     * Fallback : découpe la requête brute en mots-clés simples
     * (suppression des mots vides français, tokenisation).
     *
     * @param string $query Requête brute de l'utilisateur
     * @return string[]
     */
    private function extractKeywordsFallback(string $query): array
    {
        // Mots vides français à ignorer
        $stopWords = [
            'le', 'la', 'les', 'un', 'une', 'des', 'de', 'du', 'et', 'ou',
            'en', 'au', 'aux', 'ce', 'cet', 'cette', 'ces', 'mon', 'ma', 'mes',
            'ton', 'ta', 'tes', 'son', 'sa', 'ses', 'je', 'tu', 'il', 'elle',
            'nous', 'vous', 'ils', 'elles', 'qui', 'que', 'quoi', 'avec', 'pour',
            'sur', 'dans', 'par', 'pas', 'ne', 'se', 'si', 'plus', 'tout',
            'mais', 'donc', 'car', 'ni', 'comme', 'veux', 'cherche', 'aimerais',
            'voudrais', 'trouve', 'montrer', 'voir', 'avoir', 'besoin',
        ];

        // Conversion en minuscules et suppression des caractères non-alphabétiques
        $normalized = mb_strtolower($query, 'UTF-8');
        $normalized = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $normalized) ?? $normalized;

        // Tokenisation
        $words = preg_split('/\s+/', trim($normalized), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        // Filtrage des mots vides et des tokens trop courts
        $keywords = array_values(array_filter(
            $words,
            fn(string $word) => mb_strlen($word, 'UTF-8') >= 3 && !in_array($word, $stopWords, true)
        ));

        // Dédoublonnage
        return array_values(array_unique($keywords));
    }

    /**
     * Tente d'extraire un bloc JSON valide depuis un texte qui pourrait
     * contenir du texte additionnel autour (backticks, phrases introductives…).
     *
     * @param string $text Texte brut retourné par le modèle
     * @return string JSON extrait ou texte original
     */
    private function extractJsonFromText(string $text): string
    {
        // Suppression des blocs de code Markdown (```json … ```)
        if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $text, $matches)) {
            return $matches[1];
        }

        // Extraction du premier objet JSON trouvé dans le texte
        if (preg_match('/\{.*\}/s', $text, $matches)) {
            return $matches[0];
        }

        return $text;
    }
}
