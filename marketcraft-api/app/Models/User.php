<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Utilisateur — table `utilisateurs`.
 *
 * Trois roles : client, vendeur, admin.
 */
class User extends Authenticatable implements AuthenticatableContract
{
    protected $table = 'utilisateurs';

    protected $primaryKey = 'id';

    protected $fillable = [
        'nom',
        'prenom',
        'email',
        'password_hash',
        'role',
        'avatar_url',
        'telephone',
        'est_actif',
    ];

    /**
     * Le hachage ne sort jamais de l'application, quelle que soit la route
     * qui serialise le modele.
     */
    protected $hidden = [
        'password_hash',
    ];

    protected function casts(): array
    {
        return [
            // `est_actif` reste un entier : le contrat expose 0/1, pas
            // true/false. Le caster en booleen changerait le JSON.
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * La colonne de mot de passe s'appelle `password_hash`, pas `password`.
     * Sans cette surcharge, tout le socle d'authentification de Laravel
     * chercherait une colonne inexistante.
     */
    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    // ------------------------------------------------------------------
    // Relations
    // ------------------------------------------------------------------

    /** Regle de gestion : au plus une boutique par vendeur. */
    public function boutique(): HasOne
    {
        return $this->hasOne(Boutique::class, 'vendeur_id');
    }

    public function adresses(): HasMany
    {
        return $this->hasMany(Adresse::class, 'utilisateur_id');
    }

    public function commandes(): HasMany
    {
        return $this->hasMany(Commande::class, 'utilisateur_id');
    }

    public function avis(): HasMany
    {
        return $this->hasMany(Avis::class, 'utilisateur_id');
    }

    // ------------------------------------------------------------------
    // Aides
    // ------------------------------------------------------------------

    public function estAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function aLeRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    /**
     * Charge utile du JWT. `sub` y sera converti en chaine par App\Support\Jwt.
     *
     * @return array{id: int, email: string, role: string}
     */
    public function jwtClaims(): array
    {
        return [
            'id'    => (int) $this->id,
            'email' => (string) $this->email,
            'role'  => (string) $this->role,
        ];
    }
}
