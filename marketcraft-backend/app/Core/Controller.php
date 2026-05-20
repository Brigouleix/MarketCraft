<?php

declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    // ------------------------------------------------------------------
    // Réponses JSON
    // ------------------------------------------------------------------

    /**
     * Envoie une réponse JSON avec le code HTTP donné.
     */
    protected function json(mixed $data, int $code = 200): void
    {
        http_response_code($code);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Réponse d'erreur standardisée.
     */
    protected function error(string $message, int $code = 400, array $details = []): void
    {
        $payload = [
            'success' => false,
            'error'   => $message,
        ];

        if (!empty($details)) {
            $payload['details'] = $details;
        }

        $this->json($payload, $code);
    }

    /**
     * Réponse de succès standardisée.
     */
    protected function success(mixed $data = null, string $message = 'OK', int $code = 200): void
    {
        $payload = [
            'success' => true,
            'message' => $message,
        ];

        if ($data !== null) {
            $payload['data'] = $data;
        }

        $this->json($payload, $code);
    }

    /**
     * Réponse de succès avec pagination.
     */
    protected function paginated(array $items, int $total, int $page, int $limit): void
    {
        $this->json([
            'success'    => true,
            'data'       => $items,
            'pagination' => [
                'total'       => $total,
                'page'        => $page,
                'limit'       => $limit,
                'total_pages' => (int) ceil($total / max(1, $limit)),
            ],
        ]);
    }

    // ------------------------------------------------------------------
    // Lecture du corps de la requête
    // ------------------------------------------------------------------

    /**
     * Retourne le corps JSON de la requête sous forme de tableau associatif.
     */
    protected function getBody(): array
    {
        $raw = file_get_contents('php://input');
        if (empty($raw)) {
            return [];
        }

        $data = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [];
        }

        return $data ?? [];
    }

    /**
     * Récupère et sanitize un paramètre de l'URL (query string ou route params).
     */
    protected function getParam(string $key, mixed $default = null, array $params = []): mixed
    {
        // 1. Paramètre de route dynamique (ex: :id)
        if (isset($params[$key])) {
            return $this->sanitize($params[$key]);
        }

        // 2. Query string
        if (isset($_GET[$key])) {
            return $this->sanitize($_GET[$key]);
        }

        return $default;
    }

    /**
     * Sanitize basique d'une valeur scalaire.
     */
    protected function sanitize(mixed $value): mixed
    {
        if (is_string($value)) {
            return htmlspecialchars(trim($value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        return $value;
    }

    // ------------------------------------------------------------------
    // Validation
    // ------------------------------------------------------------------

    /**
     * Vérifie que les champs requis sont présents et non vides.
     * Retourne un tableau d'erreurs (vide si OK).
     */
    protected function validate(array $data, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $rule) {
            $ruleList = explode('|', $rule);

            foreach ($ruleList as $r) {
                if ($r === 'required') {
                    if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
                        $errors[$field][] = "Le champ «{$field}» est obligatoire.";
                    }
                } elseif (str_starts_with($r, 'min:')) {
                    $min = (int) substr($r, 4);
                    $val = $data[$field] ?? '';
                    if (is_string($val) && mb_strlen($val) < $min) {
                        $errors[$field][] = "Le champ «{$field}» doit contenir au moins {$min} caractères.";
                    } elseif (is_numeric($val) && (float) $val < $min) {
                        $errors[$field][] = "Le champ «{$field}» doit être supérieur ou égal à {$min}.";
                    }
                } elseif (str_starts_with($r, 'max:')) {
                    $max = (int) substr($r, 4);
                    $val = $data[$field] ?? '';
                    if (is_string($val) && mb_strlen($val) > $max) {
                        $errors[$field][] = "Le champ «{$field}» ne doit pas dépasser {$max} caractères.";
                    }
                } elseif ($r === 'email') {
                    $val = $data[$field] ?? '';
                    if (!filter_var($val, FILTER_VALIDATE_EMAIL)) {
                        $errors[$field][] = "Le champ «{$field}» doit être une adresse e-mail valide.";
                    }
                } elseif ($r === 'numeric') {
                    $val = $data[$field] ?? null;
                    if ($val !== null && !is_numeric($val)) {
                        $errors[$field][] = "Le champ «{$field}» doit être numérique.";
                    }
                } elseif ($r === 'integer') {
                    $val = $data[$field] ?? null;
                    if ($val !== null && !filter_var($val, FILTER_VALIDATE_INT)) {
                        $errors[$field][] = "Le champ «{$field}» doit être un entier.";
                    }
                } elseif (str_starts_with($r, 'in:')) {
                    $allowed = explode(',', substr($r, 3));
                    $val = $data[$field] ?? null;
                    if ($val !== null && !in_array((string) $val, $allowed, true)) {
                        $errors[$field][] = "La valeur de «{$field}» n'est pas autorisée.";
                    }
                }
            }
        }

        return $errors;
    }
}
