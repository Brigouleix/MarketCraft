@php
    $statuts = [
        'en_attente' => 'En attente', 'confirmee' => 'Confirmée', 'en_preparation' => 'En préparation',
        'expediee' => 'Expédiée', 'livree' => 'Livrée', 'annulee' => 'Annulée',
    ];
    $u = $commande->utilisateur;
    $a = $commande->adresse;
    $frais = (float) ($commande->frais_livraison ?? 0);
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<style>
    * { font-family: "DejaVu Sans", sans-serif; }
    body { color: #262320; font-size: 12px; margin: 0; padding: 34px 38px; }
    .row { width: 100%; }
    .brand { color: #8B4513; font-size: 26px; font-weight: bold; }
    .brand small { display: block; color: #6E695E; font-size: 10px; font-weight: normal; margin-top: 2px; }
    .facture-title { text-align: right; }
    .facture-title h1 { color: #8B4513; font-size: 22px; margin: 0; letter-spacing: 1px; }
    .facture-title .meta { color: #6E695E; font-size: 11px; margin-top: 4px; }
    hr { border: none; border-top: 2px solid #8B4513; margin: 16px 0; }
    .blocks td { vertical-align: top; width: 50%; }
    .label { color: #8B4513; font-weight: bold; font-size: 11px; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 4px; }
    .box { color: #3F3A31; line-height: 1.5; }
    table.items { width: 100%; border-collapse: collapse; margin-top: 22px; }
    table.items th { background: #8B4513; color: #fff; text-align: left; padding: 8px 10px; font-size: 11px; }
    table.items th.r, table.items td.r { text-align: right; }
    table.items td { padding: 8px 10px; border-bottom: 1px solid #EDE7DD; }
    table.items tr:nth-child(even) td { background: #FBFAF7; }
    .totals { width: 42%; margin-left: 58%; margin-top: 16px; }
    .totals td { padding: 5px 10px; }
    .totals .r { text-align: right; }
    .totals .grand td { border-top: 2px solid #8B4513; font-weight: bold; font-size: 14px; color: #8B4513; }
    .statut { display: inline-block; margin-top: 18px; padding: 4px 12px; border-radius: 12px; background: #F0E9DB; color: #8B4513; font-size: 11px; font-weight: bold; }
    .foot { margin-top: 34px; padding-top: 12px; border-top: 1px solid #EDE7DD; color: #9A9488; font-size: 10px; line-height: 1.6; }
</style>
</head>
<body>
    <table class="row">
        <tr>
            <td>
                <div class="brand">MarketCraft
                    <small>Marketplace artisanale · contact@marketcraft.fr</small>
                </div>
            </td>
            <td class="facture-title">
                <h1>FACTURE</h1>
                <div class="meta">
                    N° {{ $numero }}<br>
                    Date : {{ optional($commande->created_at)->format('d/m/Y') }}
                </div>
            </td>
        </tr>
    </table>

    <hr>

    <table class="blocks">
        <tr>
            <td>
                <div class="label">Éditeur</div>
                <div class="box">
                    MarketCraft<br>
                    12 rue des Artisans<br>
                    75011 Paris, France<br>
                    contact@marketcraft.fr
                </div>
            </td>
            <td>
                <div class="label">Client — livraison</div>
                <div class="box">
                    <strong>{{ $a->nom_complet ?? trim(($u->prenom ?? '') . ' ' . ($u->nom ?? '')) }}</strong><br>
                    @if($u && $u->email){{ $u->email }}<br>@endif
                    @if($a)
                        {{ $a->ligne1 }}<br>
                        @if($a->ligne2){{ $a->ligne2 }}<br>@endif
                        {{ $a->code_postal }} {{ $a->ville }}<br>
                        {{ $a->pays }}
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th>Produit</th>
                <th class="r">Qté</th>
                <th class="r">Prix unitaire</th>
                <th class="r">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($commande->lignes as $ligne)
                @php $lt = (float) $ligne->prix_unitaire * (int) $ligne->quantite; @endphp
                <tr>
                    <td>{{ $ligne->nom_produit }}</td>
                    <td class="r">{{ (int) $ligne->quantite }}</td>
                    <td class="r">{{ number_format((float) $ligne->prix_unitaire, 2, ',', ' ') }} €</td>
                    <td class="r">{{ number_format($lt, 2, ',', ' ') }} €</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td>Sous-total</td>
            <td class="r">{{ number_format((float) $sousTotal, 2, ',', ' ') }} €</td>
        </tr>
        <tr>
            <td>Livraison</td>
            <td class="r">{{ $frais > 0 ? number_format($frais, 2, ',', ' ') . ' €' : 'Gratuite' }}</td>
        </tr>
        <tr class="grand">
            <td>Total TTC</td>
            <td class="r">{{ number_format((float) $commande->montant_total, 2, ',', ' ') }} €</td>
        </tr>
    </table>

    <div class="statut">Statut : {{ $statuts[$commande->statut] ?? $commande->statut }}</div>

    <div class="foot">
        Facture émise par MarketCraft pour la commande n° {{ $commande->id }}.
        Document généré automatiquement — projet de démonstration. TVA non applicable, art. 293 B du CGI.
    </div>
</body>
</html>
