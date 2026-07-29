<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Boutique;
use App\Services\AnalyseConcurrentielleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Analyse concurrentielle du catalogue d'un vendeur.
 *
 * Ajout hors perimetre du cahier des charges, assume comme tel : le module
 * IA impose est la recommandation personnalisee, servie ailleurs. Voir
 * l'en-tete de AnalyseConcurrentielleService.
 */
class AnalyseConcurrentielleController extends Controller
{
    public function __construct(private readonly AnalyseConcurrentielleService $service)
    {
    }

    // ------------------------------------------------------------------
    // GET /dashboard/concurrence
    // ------------------------------------------------------------------

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        // Un administrateur peut inspecter n'importe quelle boutique ; un
        // vendeur, uniquement la sienne. Sans ce cloisonnement, l'endpoint
        // livrerait le positionnement tarifaire de la concurrence a qui le
        // demande.
        $boutiqueId = $request->query('boutique_id');

        $boutique = ($user->estAdmin() && $boutiqueId !== null)
            ? Boutique::query()->find((int) $boutiqueId)
            : Boutique::query()->where('vendeur_id', $user->id)->first();

        if ($boutique === null) {
            return $this->nonTrouve(
                $user->estAdmin()
                    ? 'Boutique not found.'
                    : "Vous n'avez pas encore de boutique."
            );
        }

        $resultat = $this->service->pourBoutique($boutique);

        return $this->ok([
            'boutique' => [
                'id'  => (int) $boutique->id,
                'nom' => $boutique->nom,
            ],
            // Distingue une vraie analyse d'un repli statistique : le front
            // l'affiche sous forme de badge.
            'ia_active' => $resultat['ia_active'],
            'source'    => $resultat['source'],
            'synthese'  => $resultat['synthese'],
            'resume'    => $resultat['resume'],
            'produits'  => $resultat['produits'],
        ]);
    }
}
