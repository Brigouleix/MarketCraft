<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Recherche produits assistee par un modele de langage.
 *
 * L'utilisateur decrit ce qu'il cherche en langage naturel ; le modele en
 * extrait des mots-cles SQL et, le cas echeant, une fourchette de prix. Le
 * controleur se charge ensuite d'interroger le catalogue avec ces mots-cles.
 *
 * Le fournisseur est configure dans config/marketcraft.php (« ai »), au format
 * OpenAI (chat completions), comme le module de recommandation. Les deux
 * services partagent la meme cle et le meme point d'acces.
 *
 * ---------------------------------------------------------------------
 * Repli — a documenter en soutenance
 * ---------------------------------------------------------------------
 * Si l'IA echoue (cle absente, quota, reseau, JSON invalide), on retombe sur
 * une extraction locale de mots-cles : suppression des mots vides francais,
 * tokenisation. La recherche se degrade — elle ne comprend plus la phrase,
 * seulement les mots — mais ne casse jamais. `ia_active` passe a false pour
 * que le front affiche le badge « mode degrade », et le motif part dans
 * storage/logs/ia.log. Jamais de retour muet.
 */
class RechercheIaService
{
    /** Les mots-cles sont combines en OU : au-dela, une requete ramene tout. */
    private const KEYWORDS_MAX = 6;

    /**
     * Interprete une requete en langage naturel.
     *
     * @return array{keywords: string[], prix_min: ?float, prix_max: ?float, message: string, ia_active: bool, ia_erreur: ?string}
     */
    public function interpreter(string $query): array
    {
        $ia = $this->appelerModele($query);

        if ($ia !== null) {
            return [
                'keywords'  => $ia['keywords'],
                'prix_min'  => $ia['prix_min'],
                'prix_max'  => $ia['prix_max'],
                'message'   => $ia['message'],
                'ia_active' => true,
                'ia_erreur' => null,
            ];
        }

        // Repli local : la phrase n'est plus comprise, seuls ses mots restent.
        return [
            'keywords'  => $this->motsClesRepli($query),
            'prix_min'  => null,
            'prix_max'  => null,
            'message'   => 'Je cherche des produits correspondant a ta demande !',
            'ia_active' => false,
            // Motif expose au front hors production uniquement (diagnostic).
            'ia_erreur' => app()->environment('production') ? null : $this->dernierMotif,
        ];
    }

    /**
     * Motif du dernier echec, expose au front hors production pour eviter
     * d'aller fouiller les logs pendant une demonstration.
     */
    private ?string $dernierMotif = null;

    // ------------------------------------------------------------------
    // Appel au modele de langage
    // ------------------------------------------------------------------

    /**
     * @return array{keywords: string[], prix_min: ?float, prix_max: ?float, message: string}|null
     */
    private function appelerModele(string $query): ?array
    {
        $cle = (string) config('marketcraft.ai.key');

        if ($cle === '') {
            return $this->echec('cle_absente', 'AI_API_KEY non renseignee : repli sur les mots-cles.');
        }

        try {
            $reponse = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $cle,
                    'Content-Type'  => 'application/json',
                    // Sans User-Agent, Cloudflare renvoie 403 avant meme de
                    // verifier la cle. Ne pas retirer cet en-tete.
                    'User-Agent'    => (string) config('marketcraft.ai.user_agent'),
                ])
                ->timeout((int) config('marketcraft.ai.timeout', 12))
                ->post((string) config('marketcraft.ai.url'), [
                    'model'    => (string) config('marketcraft.ai.model'),
                    'messages' => [
                        ['role' => 'system', 'content' => $this->consigneSysteme()],
                        ['role' => 'user',   'content' => $query],
                    ],
                    // Sortie JSON stricte : sans cela le modele encadre sa
                    // reponse de texte libre et le decodage echoue.
                    'response_format' => ['type' => 'json_object'],
                    'temperature'     => 0.2,
                    'max_tokens'      => 512,
                ]);

            if (! $reponse->successful()) {
                return $this->echec('http_' . $reponse->status(), $this->motifHttp($reponse->status(), $reponse->body()));
            }

            $contenu = $reponse->json('choices.0.message.content');

            if (! is_string($contenu)) {
                return $this->echec('reponse_vide', 'Le champ content est absent de la reponse.');
            }

            $decode = json_decode($contenu, true);

            if (! is_array($decode) || ! isset($decode['keywords']) || ! is_array($decode['keywords'])) {
                return $this->echec('json_invalide', mb_substr($contenu, 0, 300));
            }

            $keywords = $this->nettoyerMotsCles($decode['keywords']);

            if ($keywords === []) {
                return $this->echec('aucun_mot_cle', 'Le modele n a renvoye aucun mot-cle exploitable.');
            }

            return [
                'keywords' => $keywords,
                'prix_min' => isset($decode['prix_min']) && is_numeric($decode['prix_min']) ? (float) $decode['prix_min'] : null,
                'prix_max' => isset($decode['prix_max']) && is_numeric($decode['prix_max']) ? (float) $decode['prix_max'] : null,
                'message'  => is_string($decode['message'] ?? null) && $decode['message'] !== ''
                    ? mb_substr($decode['message'], 0, 200)
                    : 'Voici les resultats pour ta recherche !',
            ];
        } catch (\Throwable $e) {
            // Reseau coupe, DNS, timeout : la recherche doit repondre quand meme.
            return $this->echec('exception', $e->getMessage());
        }
    }

    private function consigneSysteme(): string
    {
        return implode("\n", [
            "Tu es un assistant e-commerce pour une marketplace d'artisanat francaise.",
            "Quand l'utilisateur decrit ce qu'il cherche, extrais les mots-cles pertinents pour",
            'chercher des produits dans une base de donnees. Reponds UNIQUEMENT en JSON valide,',
            'sans texte autour, selon cette structure exacte :',
            '{"keywords":["mot1","mot2"],"prix_min":null,"prix_max":null,"message":"<une phrase courte, tutoiement>"}',
            'Les keywords sont les termes de recherche SQL (matieres, types d objets, couleurs).',
            'REGLE : 6 mots-cles maximum, uniquement les plus discriminants. Les mots-cles sont',
            'combines en OU : un terme trop generique ramenerait tout le catalogue. N invente pas',
            'de variantes non demandees et n ajoute jamais de termes vagues comme « design »,',
            '« moderne », « qualite » ou « artisanal ».',
            'Exemple pour « un ensemble de couverts dores avec une table en bois » :',
            'keywords: ["couverts","dore","table","bois"], message: "Je cherche des couverts dores et une table en bois pour toi !"',
        ]);
    }

    /**
     * @param  array<int, mixed> $brut
     * @return string[]
     */
    private function nettoyerMotsCles(array $brut): array
    {
        $mots = array_map(static fn ($m) => trim((string) $m), $brut);
        $mots = array_values(array_filter($mots, static fn (string $m) => $m !== ''));

        // Quelle que soit la consigne, le modele reste libre de sur-generer :
        // on plafonne cote serveur.
        return array_slice(array_values(array_unique($mots)), 0, self::KEYWORDS_MAX);
    }

    private function motifHttp(int $code, string $corps): string
    {
        return match (true) {
            $code === 429 => 'Quota du palier gratuit atteint (429). Reessayez dans une minute.',
            $code === 401 => 'Cle API refusee (401). Verifiez AI_API_KEY.',
            $code === 403 => 'Requete bloquee en amont (403). User-Agent manquant, ou IP filtree.',
            default       => "Le fournisseur IA a repondu {$code} : " . mb_substr($corps, 0, 200),
        };
    }

    // ------------------------------------------------------------------
    // Repli local
    // ------------------------------------------------------------------

    /**
     * Decoupe la requete brute en mots-cles : minuscules, suppression des
     * mots vides francais, tokens d'au moins 3 caracteres, dedoublonnes.
     *
     * @return string[]
     */
    private function motsClesRepli(string $query): array
    {
        static $motsVides = [
            'le', 'la', 'les', 'un', 'une', 'des', 'de', 'du', 'et', 'ou',
            'en', 'au', 'aux', 'ce', 'cet', 'cette', 'ces', 'mon', 'ma', 'mes',
            'ton', 'ta', 'tes', 'son', 'sa', 'ses', 'je', 'tu', 'il', 'elle',
            'nous', 'vous', 'ils', 'elles', 'qui', 'que', 'quoi', 'avec', 'pour',
            'sur', 'dans', 'par', 'pas', 'ne', 'se', 'si', 'plus', 'tout',
            'mais', 'donc', 'car', 'ni', 'comme', 'veux', 'cherche', 'aimerais',
            'voudrais', 'trouve', 'montrer', 'voir', 'avoir', 'besoin',
        ];

        $normalise = mb_strtolower($query, 'UTF-8');
        $normalise = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $normalise) ?? $normalise;

        $mots = preg_split('/\s+/', trim($normalise), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $mots = array_filter(
            $mots,
            static fn (string $m) => mb_strlen($m, 'UTF-8') >= 3 && ! in_array($m, $motsVides, true)
        );

        return array_slice(array_values(array_unique($mots)), 0, self::KEYWORDS_MAX);
    }

    /**
     * Chaque echec est trace avec son motif. Un `return null` muet rendrait
     * impossible de distinguer « rien trouve » de « la cle a expire ».
     */
    private function echec(string $motif, string $detail): null
    {
        $this->dernierMotif = $detail;

        Log::channel('ia')->warning('Recherche IA : repli sur les mots-cles', [
            'motif'  => $motif,
            'detail' => $detail,
            'modele' => config('marketcraft.ai.model'),
        ]);

        return null;
    }
}
