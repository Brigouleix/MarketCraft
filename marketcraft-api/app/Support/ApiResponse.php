<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\JsonResponse;

/**
 * Fabrique des reponses JSON conformes au contrat d'API.
 *
 * Le front deballe `data` sans verification prealable : une ressource
 * renvoyee nue, hors enveloppe, laisse l'interface vide sans la moindre
 * erreur en console. Tout ce qui sort de l'API passe donc par ici.
 *
 * Trois formes, et seulement trois :
 *
 *   succes         { "success": true,  "message": "OK", "data": {...} }
 *   succes pagine  { "success": true,  "data": [...], "pagination": {...} }
 *   erreur         { "success": false, "error": "...", "details": {...} }
 */
final class ApiResponse
{
    /**
     * Les accents et les slashs d'URL ne doivent pas etre echappes :
     * « Céramique » reste lisible et « http://... » ne devient pas
     * « http:\/\/... ».
     */
    public const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    /**
     * Reponse brute : a n'utiliser que pour les quelques endpoints dont la
     * forme historique s'ecarte des trois enveloppes (auth, health).
     */
    public static function raw(array $payload, int $status = 200): JsonResponse
    {
        return response()
            ->json($payload, $status)
            ->setEncodingOptions(self::JSON_FLAGS);
    }

    /**
     * Succes simple. `data` est omis lorsqu'il n'y a rien a renvoyer ;
     * `message` est toujours present.
     */
    public static function success(mixed $data = null, string $message = 'OK', int $status = 200): JsonResponse
    {
        $payload = [
            'success' => true,
            'message' => $message,
        ];

        if ($data !== null) {
            $payload['data'] = $data;
        }

        return self::raw($payload, $status);
    }

    /**
     * Erreur. `details` n'apparait qu'en cas de validation ou de rejet
     * detaille : un tableau vide est omis, pas serialise en {}.
     */
    public static function error(string $message, int $status = 400, array $details = []): JsonResponse
    {
        $payload = [
            'success' => false,
            'error'   => $message,
        ];

        if ($details !== []) {
            $payload['details'] = $details;
        }

        return self::raw($payload, $status);
    }

    /**
     * Succes pagine. Volontairement sans `message` : le contrat historique
     * n'en expose pas sur cette forme.
     *
     * @param  array $extra  Cles supplementaires fusionnees a la racine
     *                       (ex. `stats` sur la liste des avis d'un produit).
     */
    public static function paginated(
        array $items,
        int $total,
        int $page,
        int $limit,
        array $extra = []
    ): JsonResponse {
        $payload = [
            'success' => true,
            'data'    => array_values($items),
        ];

        // `stats` se place entre `data` et `pagination`, comme dans
        // l'ancien back-end. L'ordre des cles n'a pas d'incidence
        // fonctionnelle, mais facilite la comparaison des reponses.
        $payload += $extra;

        $payload['pagination'] = [
            'total'       => $total,
            'page'        => $page,
            'limit'       => $limit,
            'total_pages' => (int) ceil($total / max(1, $limit)),
        ];

        return self::raw($payload, 200);
    }
}
