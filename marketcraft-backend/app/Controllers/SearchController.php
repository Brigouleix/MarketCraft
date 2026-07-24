<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Config\Database;

/**
 * SearchController – Gestion de la recherche produits avec IA (API Claude Anthropic)
 *
 * Routes :
 *   POST /search/ai  – Recherche intelligente via Claude
 *   GET  /search     – Recherche simple par mots-clés (fallback)
 */
class SearchController extends Controller
{
    // Nombre maximum de résultats retournés
    private const MAX_RESULTS = 20;

    // URL de l'API Anthropic
    private const ANTHROPIC_API_URL = 'https://api.anthropic.com/v1/messages';

    // Modèle Claude à utiliser
    private const CLAUDE_MODEL = 'claude-opus-4-7';

    // Nombre maximum de tokens pour la réponse Claude
    private const MAX_TOKENS = 512;

    // -------------------------------------------------------------------------
    // POST /search/ai
    // -------------------------------------------------------------------------

    /**
     * Recherche IA : interprète la requête en langage naturel via Claude,
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

        // Tentative d'extraction IA via Claude
        $aiResult = $this->callClaudeApi($query);

        if ($aiResult !== null) {
            // Succès : utiliser les mots-clés extraits par Claude
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
    // Appel à l'API Claude (Anthropic)
    // -------------------------------------------------------------------------

    /**
     * Envoie la requête utilisateur à Claude et retourne le tableau JSON extrait,
     * ou null en cas d'erreur (timeout, clé invalide, JSON malformé…).
     *
     * @param string $userQuery Requête en langage naturel de l'utilisateur
     * @return array<string, mixed>|null
     */
    private function callClaudeApi(string $userQuery): ?array
    {
        // Récupération de la clé API depuis les variables d'environnement
        $apiKey = $_ENV['ANTHROPIC_API_KEY'] ?? getenv('ANTHROPIC_API_KEY') ?: '';

        if ($apiKey === '') {
            // Pas de clé configurée : on passe directement au fallback
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

        // Corps de la requête JSON pour l'API Anthropic
        $requestBody = json_encode([
            'model'      => self::CLAUDE_MODEL,
            'max_tokens' => self::MAX_TOKENS,
            'system'     => $systemPrompt,
            'messages'   => [
                [
                    'role'    => 'user',
                    'content' => $userQuery,
                ],
            ],
        ], JSON_UNESCAPED_UNICODE);

        if ($requestBody === false) {
            return null;
        }

        // Initialisation cURL
        $ch = curl_init(self::ANTHROPIC_API_URL);

        if ($ch === false) {
            return null;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $requestBody,
            CURLOPT_TIMEOUT        => 15, // 15 secondes max
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-api-key: ' . $apiKey,
                'anthropic-version: 2023-06-01',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        // Vérification des erreurs réseau
        if ($response === false || $curlError !== '') {
            return null;
        }

        // Vérification du code HTTP
        if ($httpCode < 200 || $httpCode >= 300) {
            return null;
        }

        // Décodage de la réponse Anthropic
        $apiResponse = json_decode((string) $response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        // Extraction du texte généré par Claude
        $content = $apiResponse['content'][0]['text'] ?? null;

        if ($content === null || !is_string($content)) {
            return null;
        }

        // Nettoyage : on extrait le bloc JSON même si Claude ajoute du texte autour
        $content = $this->extractJsonFromText($content);

        // Décodage du JSON retourné par Claude
        $decoded = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return null;
        }

        // Validation minimale : doit contenir un tableau keywords
        if (!isset($decoded['keywords']) || !is_array($decoded['keywords'])) {
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
     * @param string $text Texte brut retourné par Claude
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
