<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\CreeDesDonnees;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use CreeDesDonnees;
    use RefreshDatabase;

    // ------------------------------------------------------------------
    // Inscription
    // ------------------------------------------------------------------

    public function test_inscription_renvoie_les_jetons_hors_enveloppe(): void
    {
        $reponse = $this->postJson('/api/auth/register', [
            'nom'      => 'Martin',
            'prenom'   => 'Paul',
            'email'    => 'paul@example.com',
            'password' => 'Password123',
        ]);

        // Forme historique : les jetons sont au premier niveau, PAS sous
        // `data`. L'intercepteur axios du front lit `data.access_token`.
        $reponse->assertStatus(201)
            ->assertJsonStructure(['success', 'message', 'access_token', 'refresh_token', 'user'])
            ->assertJsonMissingPath('data');

        $this->assertDatabaseHas('utilisateurs', ['email' => 'paul@example.com', 'role' => 'client']);
    }

    public function test_le_mot_de_passe_est_hache_et_jamais_renvoye(): void
    {
        $reponse = $this->postJson('/api/auth/register', [
            'nom' => 'Martin', 'prenom' => 'Paul',
            'email' => 'paul@example.com', 'password' => 'Password123',
        ]);

        $reponse->assertJsonMissingPath('user.password_hash');

        $hash = User::query()->where('email', 'paul@example.com')->value('password_hash');

        $this->assertStringStartsWith('$2y$', $hash);
        $this->assertNotSame('Password123', $hash);
        $this->assertTrue(Hash::check('Password123', $hash));
    }

    public function test_un_mot_de_passe_sans_majuscule_est_refuse(): void
    {
        $this->postJson('/api/auth/register', [
            'nom' => 'Martin', 'prenom' => 'Paul',
            'email' => 'paul@example.com', 'password' => 'password123',
        ])->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_on_ne_peut_pas_s_inscrire_administrateur(): void
    {
        $this->postJson('/api/auth/register', [
            'nom' => 'Pirate', 'prenom' => 'Jean',
            'email' => 'pirate@example.com', 'password' => 'Password123',
            'role' => 'admin',
        ])->assertStatus(201);

        // Le role demande est ignore : seule la liste blanche compte.
        $this->assertSame('client', User::query()->where('email', 'pirate@example.com')->value('role'));
    }

    public function test_email_deja_utilise_renvoie_409(): void
    {
        $this->creerUtilisateur('client', ['email' => 'occupe@example.com']);

        $this->postJson('/api/auth/register', [
            'nom' => 'Martin', 'prenom' => 'Paul',
            'email' => 'occupe@example.com', 'password' => 'Password123',
        ])->assertStatus(409)->assertJsonPath('error', 'This email address is already in use.');
    }

    // ------------------------------------------------------------------
    // Connexion
    // ------------------------------------------------------------------

    public function test_connexion_valide(): void
    {
        $this->creerUtilisateur('client', ['email' => 'jules@example.com']);

        $this->postJson('/api/auth/login', [
            'email' => 'jules@example.com', 'password' => 'Password123',
        ])->assertStatus(200)->assertJsonStructure(['access_token', 'refresh_token', 'user']);
    }

    public function test_le_message_d_erreur_ne_distingue_pas_email_inconnu_et_mot_de_passe_faux(): void
    {
        $this->creerUtilisateur('client', ['email' => 'jules@example.com']);

        $inconnu = $this->postJson('/api/auth/login', [
            'email' => 'personne@example.com', 'password' => 'Password123',
        ]);

        $mauvais = $this->postJson('/api/auth/login', [
            'email' => 'jules@example.com', 'password' => 'MauvaisMdp123',
        ]);

        // Deux messages differents offriraient un annuaire des comptes valides.
        $inconnu->assertStatus(401)->assertJsonPath('error', 'Invalid email or password.');
        $mauvais->assertStatus(401)->assertJsonPath('error', 'Invalid email or password.');
    }

    public function test_le_compte_se_verrouille_apres_cinq_echecs(): void
    {
        $this->creerUtilisateur('client', ['email' => 'cible@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', [
                'email' => 'cible@example.com', 'password' => 'Faux' . $i,
            ])->assertStatus(401);
        }

        // 6e tentative : refusee avant meme d etre evaluee, et le bon mot
        // de passe ne debloque pas.
        $this->postJson('/api/auth/login', [
            'email' => 'cible@example.com', 'password' => 'Password123',
        ])->assertStatus(423)->assertJsonPath('details.verrouille', true);
    }

    public function test_une_connexion_reussie_remet_le_compteur_a_zero(): void
    {
        $this->creerUtilisateur('client', ['email' => 'jules@example.com']);

        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/auth/login', ['email' => 'jules@example.com', 'password' => 'Faux']);
        }

        $this->postJson('/api/auth/login', [
            'email' => 'jules@example.com', 'password' => 'Password123',
        ])->assertStatus(200);

        $this->assertDatabaseMissing('tentatives_connexion', [
            'email' => 'jules@example.com', 'reussie' => 0,
        ]);
    }

    public function test_un_compte_desactive_ne_peut_pas_se_connecter(): void
    {
        $this->creerUtilisateur('client', ['email' => 'parti@example.com', 'est_actif' => 0]);

        $this->postJson('/api/auth/login', [
            'email' => 'parti@example.com', 'password' => 'Password123',
        ])->assertStatus(401);
    }

    // ------------------------------------------------------------------
    // Routes protegees
    // ------------------------------------------------------------------

    public function test_me_exige_un_jeton(): void
    {
        $this->getJson('/api/auth/me')->assertStatus(401)->assertJsonPath('success', false);
        $this->getJson('/api/auth/me', ['Authorization' => 'Bearer nimportequoi'])->assertStatus(401);
    }

    public function test_me_renvoie_le_profil_dans_l_enveloppe(): void
    {
        $user = $this->creerUtilisateur('vendeur');

        $this->getJson('/api/auth/me', $this->entetes($user))
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', (int) $user->id)
            ->assertJsonPath('data.role', 'vendeur')
            ->assertJsonMissingPath('data.password_hash');
    }

    public function test_changer_de_mot_de_passe_exige_l_ancien(): void
    {
        $user = $this->creerUtilisateur();

        $this->putJson('/api/auth/me', ['password' => 'NouveauMdp123'], $this->entetes($user))
            ->assertStatus(422);

        $this->putJson('/api/auth/me', [
            'password' => 'NouveauMdp123', 'current_password' => 'Faux',
        ], $this->entetes($user))->assertStatus(403);

        $this->putJson('/api/auth/me', [
            'password' => 'NouveauMdp123', 'current_password' => 'Password123',
        ], $this->entetes($user))->assertStatus(200);
    }

    public function test_le_jeton_de_rafraichissement_n_ouvre_pas_les_ressources(): void
    {
        $user = $this->creerUtilisateur();

        $refresh = json_decode($this->postJson('/api/auth/login', [
            'email' => $user->email, 'password' => 'Password123',
        ])->getContent(), true)['refresh_token'];

        // Accepte comme jeton d acces, il aurait une duree de vie de 7 jours.
        $this->getJson('/api/auth/me', ['Authorization' => "Bearer {$refresh}"])
            ->assertStatus(401);

        $this->postJson('/api/auth/refresh', ['refresh_token' => $refresh])
            ->assertStatus(200)
            ->assertJsonStructure(['access_token', 'refresh_token', 'user']);
    }

    public function test_les_entetes_de_securite_sont_presents(): void
    {
        $this->getJson('/api/health')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }
}
