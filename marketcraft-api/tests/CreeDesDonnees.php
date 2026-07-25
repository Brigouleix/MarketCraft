<?php

declare(strict_types=1);

namespace Tests;

use App\Models\Boutique;
use App\Models\Categorie;
use App\Models\Produit;
use App\Models\User;
use App\Support\Jwt;
use Illuminate\Support\Facades\Hash;

/**
 * Fabriques minimales partagees par les tests.
 *
 * Ecrites a la main plutot qu'avec des factories : le schema est herite
 * d'une base existante, avec des colonnes en francais et des contraintes
 * (une boutique par vendeur, un avis par couple) qu'une factory generique
 * violerait sans le dire.
 */
trait CreeDesDonnees
{
    protected function creerUtilisateur(string $role = 'client', array $attributs = []): User
    {
        static $compteur = 0;
        $compteur++;

        return User::create(array_merge([
            'nom'           => 'Test',
            'prenom'        => 'Utilisateur',
            'email'         => "utilisateur{$compteur}@example.com",
            'password_hash' => Hash::make('Password123'),
            'role'          => $role,
            'est_actif'     => 1,
        ], $attributs));
    }

    protected function creerBoutique(User $vendeur, array $attributs = []): Boutique
    {
        static $compteur = 0;
        $compteur++;

        return Boutique::create(array_merge([
            'vendeur_id' => $vendeur->id,
            'nom'        => "Boutique {$compteur}",
            'slug'       => "boutique-{$compteur}",
            'est_active' => 1,
        ], $attributs));
    }

    protected function creerCategorie(string $nom, string $slug, ?int $parentId = null): Categorie
    {
        return Categorie::create([
            'parent_id' => $parentId,
            'nom'       => $nom,
            'slug'      => $slug,
            'ordre'     => 0,
        ]);
    }

    protected function creerProduit(Boutique $boutique, array $attributs = []): Produit
    {
        static $compteur = 0;
        $compteur++;

        return Produit::create(array_merge([
            'boutique_id'   => $boutique->id,
            'nom'           => "Produit {$compteur}",
            'slug'          => "produit-{$compteur}",
            'prix'          => 45.00,
            'stock'         => 10,
            'est_actif'     => 1,
            'est_fait_main' => 1,
        ], $attributs));
    }

    /**
     * @return array<string, string>  En-tetes prets a passer a $this->getJson()
     */
    protected function entetes(User $user): array
    {
        return ['Authorization' => 'Bearer ' . Jwt::issueAccessToken($user->jwtClaims())];
    }
}
