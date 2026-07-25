<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\CaptchaService;
use App\Services\LoginThrottle;
use App\Support\ApiResponse;
use App\Support\Jwt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * Authentification.
 *
 * Attention a la forme des reponses : /auth/register, /auth/login et
 * /auth/refresh renvoient `access_token`, `refresh_token` et `user`
 * AU PREMIER NIVEAU, hors de l'enveloppe `data`. C'est ce que lit
 * l'intercepteur axios du front (`data.access_token`). Les envelopper
 * proprement casserait la connexion et le rafraichissement silencieux
 * du jeton, sans la moindre erreur en console.
 */
class AuthController extends Controller
{
    public function __construct(
        private readonly LoginThrottle $throttle,
        private readonly CaptchaService $captcha,
        private readonly ActivityLogger $journal,
    ) {
    }

    // ------------------------------------------------------------------
    // POST /auth/register
    // ------------------------------------------------------------------

    public function register(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'nom'       => ['required', 'string', 'min:2', 'max:100'],
            'prenom'    => ['required', 'string', 'min:2', 'max:100'],
            'email'     => ['required', 'email', 'max:191'],
            // Exigence du cahier des charges sur la complexite. Le hachage
            // reste bcrypt cout 12, comme l'ancien back-end : les comptes
            // existants continuent de se connecter.
            'password'  => ['required', 'string', 'max:255', Password::min(8)->mixedCase()->numbers()],
            'role'      => ['sometimes', 'string'],
            'telephone' => ['sometimes', 'nullable', 'string', 'max:20'],
        ]);

        $email = mb_strtolower(trim($donnees['email']));

        if (User::query()->where('email', $email)->exists()) {
            return $this->echec('This email address is already in use.', 409);
        }

        // Le role vient du client : sans cette liste blanche, n'importe qui
        // se creerait un compte administrateur a l'inscription.
        $role = in_array($donnees['role'] ?? '', ['client', 'vendeur'], true)
            ? $donnees['role']
            : 'client';

        $user = User::create([
            'nom'           => trim($donnees['nom']),
            'prenom'        => trim($donnees['prenom']),
            'email'         => $email,
            'password_hash' => Hash::make($donnees['password']),
            'role'          => $role,
            'telephone'     => $donnees['telephone'] ?? null,
            'est_actif'     => 1,
        ]);

        $this->journal->info(
            'inscription',
            "Nouveau compte {$role}",
            ['email' => $email],
            (int) $user->id
        );

        return ApiResponse::raw([
            'success'       => true,
            'message'       => 'Registration successful.',
            'access_token'  => Jwt::issueAccessToken($user->jwtClaims()),
            'refresh_token' => Jwt::issueRefreshToken($user->jwtClaims()),
            'user'          => UserResource::make($user),
        ], 201);
    }

    // ------------------------------------------------------------------
    // POST /auth/login
    // ------------------------------------------------------------------

    public function login(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $email = mb_strtolower(trim($donnees['email']));

        // 1. Verrouillage : on refuse avant meme de toucher a la base.
        if ($this->throttle->estVerrouille($email)) {
            $secondes = $this->throttle->secondesRestantes($email);
            $minutes  = (int) ceil($secondes / 60);

            $this->journal->avertissement(
                'connexion_verrouillee',
                'Tentative sur un compte verrouille',
                ['email' => $email]
            );

            return $this->echec(
                "Compte temporairement verrouille apres trop de tentatives. Reessayez dans {$minutes} minute(s).",
                423,
                ['verrouille' => true, 'secondes_restantes' => $secondes]
            );
        }

        // 2. Captcha : exige des le 3e echec, avant le verrouillage.
        if ($this->throttle->captchaRequis($email)) {
            $valide = $this->captcha->verifier(
                $request->input('captcha_token'),
                $request->input('captcha_reponse')
            );

            if (! $valide) {
                return $this->echec(
                    'Captcha requis ou incorrect.',
                    422,
                    ['captcha_requis' => true]
                );
            }
        }

        $user = User::query()->where('email', $email)->first();

        // Message identique que l'email soit inconnu ou le mot de passe
        // faux : distinguer les deux cas revient a offrir un annuaire de
        // comptes valides.
        $identifiantsValides = $user !== null
            && (int) $user->est_actif === 1
            && Hash::check($donnees['password'], $user->getAuthPassword());

        if (! $identifiantsValides) {
            $this->throttle->enregistrerEchec($email, $request->ip(), $request->userAgent());

            $this->journal->avertissement(
                'connexion_refusee',
                'Identifiants invalides',
                [
                    'email'   => $email,
                    'echecs'  => $this->throttle->nombreEchecs($email),
                ]
            );

            $restantes = max(0, (int) config('marketcraft.auth.max_attempts') - $this->throttle->nombreEchecs($email));

            return $this->echec(
                'Invalid email or password.',
                401,
                $restantes > 0 ? ['tentatives_restantes' => $restantes] : []
            );
        }

        $this->throttle->enregistrerSucces($email, $request->ip(), $request->userAgent());

        // Reencode le mot de passe si le cout bcrypt de la configuration
        // a change depuis la creation du compte.
        if (Hash::needsRehash($user->getAuthPassword())) {
            $user->forceFill(['password_hash' => Hash::make($donnees['password'])])->save();
        }

        $this->journal->info('connexion_reussie', 'Connexion', ['email' => $email], (int) $user->id);

        return ApiResponse::raw([
            'success'       => true,
            'message'       => 'Login successful.',
            'access_token'  => Jwt::issueAccessToken($user->jwtClaims()),
            'refresh_token' => Jwt::issueRefreshToken($user->jwtClaims()),
            'user'          => UserResource::make($user),
        ]);
    }

    // ------------------------------------------------------------------
    // GET /auth/captcha
    // ------------------------------------------------------------------

    /**
     * Delivre un defi. Le front l'appelle lorsqu'une reponse de connexion
     * contient `details.captcha_requis`.
     */
    public function captcha(): JsonResponse
    {
        return $this->ok($this->captcha->genererDefi());
    }

    // ------------------------------------------------------------------
    // POST /auth/logout
    // ------------------------------------------------------------------

    public function logout(Request $request): JsonResponse
    {
        // JWT est sans etat : le serveur n'a rien a revoquer, le client
        // supprime son jeton. On trace la deconnexion, c'est tout.
        $this->journal->info('deconnexion', 'Deconnexion', [], $request->user()?->id);

        return ApiResponse::raw([
            'success' => true,
            'message' => 'Logged out successfully.',
        ]);
    }

    // ------------------------------------------------------------------
    // POST /auth/refresh
    // ------------------------------------------------------------------

    public function refresh(Request $request): JsonResponse
    {
        $token = $request->input('refresh_token');

        if (! is_string($token) || $token === '') {
            return $this->echec('refresh_token is required.', 422);
        }

        $payload = Jwt::decode($token);

        // Un jeton d'acces presente ici serait accepte sans ce controle,
        // ce qui reviendrait a lui donner une duree de vie illimitee.
        if ($payload === null || ($payload['type'] ?? null) !== 'refresh') {
            return $this->echec('Invalid or expired refresh token.', 401);
        }

        $user = User::query()->find((int) ($payload['sub'] ?? 0));

        if ($user === null || (int) $user->est_actif !== 1) {
            return $this->echec('User not found.', 404);
        }

        return ApiResponse::raw([
            'success'       => true,
            'access_token'  => Jwt::issueAccessToken($user->jwtClaims()),
            'refresh_token' => Jwt::issueRefreshToken($user->jwtClaims()),
            'user'          => UserResource::make($user),
        ]);
    }

    // ------------------------------------------------------------------
    // GET /auth/me
    // ------------------------------------------------------------------

    public function me(Request $request): JsonResponse
    {
        return $this->ok(UserResource::make($request->user()));
    }

    // ------------------------------------------------------------------
    // PUT /auth/me
    // ------------------------------------------------------------------

    public function updateMe(Request $request): JsonResponse
    {
        $user = $request->user();

        $donnees = $request->validate([
            'nom'        => ['sometimes', 'string', 'min:2', 'max:100'],
            'prenom'     => ['sometimes', 'string', 'min:2', 'max:100'],
            'telephone'  => ['sometimes', 'nullable', 'string', 'max:20'],
            'avatar_url' => ['sometimes', 'nullable', 'string', 'max:500'],
            'password'   => ['sometimes', 'string', 'max:255', Password::min(8)->mixedCase()->numbers()],
        ]);

        // Changer de mot de passe exige de connaitre l'ancien : sinon un
        // jeton vole suffit a prendre le controle definitif du compte.
        if (! empty($donnees['password'])) {
            $ancien = $request->input('current_password');

            if (! is_string($ancien) || $ancien === '') {
                return $this->echec('Current password is required to set a new one.', 422);
            }

            if (! Hash::check($ancien, $user->getAuthPassword())) {
                $this->journal->avertissement(
                    'changement_mdp_refuse',
                    'Mot de passe actuel incorrect',
                    [],
                    (int) $user->id
                );

                return $this->echec('Current password is incorrect.', 403);
            }

            $user->password_hash = Hash::make($donnees['password']);
            $this->journal->info('changement_mdp', 'Mot de passe modifie', [], (int) $user->id);
        }

        // `role` et `est_actif` sont volontairement absents : un utilisateur
        // ne se promeut pas lui-meme depuis son profil.
        foreach (['nom', 'prenom', 'telephone', 'avatar_url'] as $champ) {
            if (array_key_exists($champ, $donnees)) {
                $user->{$champ} = is_string($donnees[$champ]) ? trim($donnees[$champ]) : $donnees[$champ];
            }
        }

        $user->save();

        return $this->ok(UserResource::make($user->fresh()), 'Profile updated successfully.');
    }

    // ------------------------------------------------------------------
    // DELETE /auth/me
    // ------------------------------------------------------------------

    public function deleteMe(Request $request): JsonResponse
    {
        $user     = $request->user();
        $password = $request->input('password');

        if (! is_string($password) || $password === '') {
            return $this->echec('Password is required to delete your account.', 422);
        }

        if (! Hash::check($password, $user->getAuthPassword())) {
            return $this->echec('Password is incorrect.', 403);
        }

        // Desactivation et non suppression : les commandes passees portent
        // une contrainte RESTRICT, et l'historique comptable doit survivre
        // au depart de l'utilisateur.
        $user->est_actif = 0;
        $user->save();

        $this->journal->avertissement(
            'suppression_compte',
            'Compte desactive a la demande de son titulaire',
            [],
            (int) $user->id
        );

        return $this->ok(null, 'Account deleted successfully.');
    }
}
