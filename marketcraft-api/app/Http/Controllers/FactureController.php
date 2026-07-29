<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Commande;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Facture PDF d'une commande — GET /orders/{id}/facture.
 *
 * Le justificatif est celui de l'acheteur : seul le client proprietaire de
 * la commande (ou un administrateur) peut le telecharger.
 */
class FactureController extends Controller
{
    public function __invoke(Request $request, int $id): Response
    {
        $commande = Commande::query()
            ->with(['utilisateur:id,prenom,nom,email', 'adresse', 'lignes'])
            ->find($id);

        if ($commande === null) {
            return $this->nonTrouve('Commande introuvable.');
        }

        $user = $request->user();

        if ((int) $commande->utilisateur_id !== (int) $user->id && $user->role !== 'admin') {
            return $this->interdit('Vous ne pouvez pas acceder a cette facture.');
        }

        $sousTotal = $commande->lignes->sum(
            static fn ($ligne): float => (float) $ligne->prix_unitaire * (int) $ligne->quantite
        );

        $numero = sprintf(
            'FA-%s-%05d',
            optional($commande->created_at)->format('Y') ?? date('Y'),
            (int) $commande->id
        );

        $pdf = Pdf::loadView('facture', [
            'commande'  => $commande,
            'sousTotal' => $sousTotal,
            'numero'    => $numero,
        ]);

        return $pdf->download("facture-{$commande->id}.pdf");
    }
}
