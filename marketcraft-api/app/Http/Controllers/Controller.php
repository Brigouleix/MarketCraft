<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Socle des controleurs de l'API.
 *
 * Les raccourcis de reponse passent tous par App\Support\ApiResponse :
 * un seul point de sortie, donc une seule enveloppe possible et des
 * drapeaux d'encodage JSON identiques partout.
 */
abstract class Controller
{
    protected function ok(mixed $data = null, string $message = 'OK', int $status = 200): JsonResponse
    {
        return ApiResponse::success($data, $message, $status);
    }

    protected function cree(mixed $data, string $message): JsonResponse
    {
        return ApiResponse::success($data, $message, 201);
    }

    protected function echec(string $message, int $status = 400, array $details = []): JsonResponse
    {
        return ApiResponse::error($message, $status, $details);
    }

    protected function nonTrouve(string $message): JsonResponse
    {
        return ApiResponse::error($message, 404);
    }

    protected function interdit(string $message = 'Forbidden.'): JsonResponse
    {
        return ApiResponse::error($message, 403);
    }

    protected function pagine(array $items, int $total, int $page, int $limit, array $extra = []): JsonResponse
    {
        return ApiResponse::paginated($items, $total, $page, $limit, $extra);
    }
}
