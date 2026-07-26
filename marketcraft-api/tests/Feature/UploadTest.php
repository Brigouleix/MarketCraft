<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\CreeDesDonnees;
use Tests\TestCase;

/**
 * Depot d'images.
 *
 * Ces tests n'utilisent PAS UploadedFile::fake()->image(), pour deux
 * raisons :
 *
 *   1. cette fabrique exige l'extension GD, absente de beaucoup
 *      d'installations PHP — la suite doit tourner partout ;
 *
 *   2. surtout, le fichier qu'elle produit deduit son type MIME de son
 *      NOM, pas de son contenu. Un test ecrit avec elle ne peut donc pas
 *      eprouver la detection de type reelle : il verifierait seulement
 *      que Laravel sait lire une extension.
 *
 * On ecrit donc de vrais fichiers temporaires, avec de vrais octets, et
 * l'on construit un UploadedFile pointant dessus. `getMimeType()` passe
 * alors par finfo, exactement comme en production.
 */
class UploadTest extends TestCase
{
    use CreeDesDonnees;
    use RefreshDatabase;

    /** Une image PNG valide de 1x1 pixel, transparente. */
    private const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAA'
        . 'DUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    /** Une image GIF valide de 1x1 pixel. */
    private const GIF_1X1 = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    /**
     * Repertoire d'ecriture propre aux tests.
     *
     * Surtout PAS `public/uploads` : le controleur y depose de vrais
     * fichiers, et un nettoyage par glob('img_*') y effacerait les images
     * du catalogue — celles des produits deja en ligne portent le meme
     * prefixe. Les tests ecrivent donc a cote, et n'effacent que chez eux.
     */
    private const DOSSIER_TEST = 'uploads-test';

    /** @var string[] Chemins temporaires a effacer apres chaque test. */
    private array $temporaires = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['marketcraft.upload.directory' => self::DOSSIER_TEST]);
    }

    protected function tearDown(): void
    {
        // Le controleur ecrit de vrais fichiers avec move(), comme
        // l'ancien back-end : pas de disque simule, donc on range.
        // Le nettoyage est borne au repertoire de test.
        $dossier = public_path(self::DOSSIER_TEST);

        foreach (glob($dossier . '/*') ?: [] as $chemin) {
            @unlink($chemin);
        }

        @rmdir($dossier);

        foreach ($this->temporaires as $chemin) {
            @unlink($chemin);
        }

        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // Acces
    // ------------------------------------------------------------------

    public function test_le_depot_exige_une_authentification(): void
    {
        $this->postJson('/api/upload/image')->assertStatus(401);
        $this->postJson('/api/upload/images')->assertStatus(401);
    }

    // ------------------------------------------------------------------
    // Depot simple
    // ------------------------------------------------------------------

    public function test_une_image_valide_renvoie_url_et_filename_hors_enveloppe(): void
    {
        $user = $this->creerUtilisateur('vendeur');

        $reponse = $this->postJson('/api/upload/image', [
            'image' => $this->imagePng('photo.png'),
        ], $this->entetes($user));

        // Forme historique : `url` et `filename` au premier niveau, hors
        // de `data`. Le front lit `data.url` directement (DashboardPage).
        $reponse->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'url', 'filename'])
            ->assertJsonMissingPath('data');

        $this->assertStringStartsWith(config('app.url') . '/' . self::DOSSIER_TEST . '/', $reponse->json('url'));
        $this->assertStringStartsWith('img_', $reponse->json('filename'));
        $this->assertFileExists(public_path(self::DOSSIER_TEST . '/' . $reponse->json('filename')));
    }

    public function test_le_champ_doit_s_appeler_image(): void
    {
        $user = $this->creerUtilisateur('vendeur');

        $this->postJson('/api/upload/image', [
            'fichier' => $this->imagePng('photo.png'),
        ], $this->entetes($user))
            ->assertStatus(422)
            ->assertJsonPath('error', 'No file uploaded. Field name must be "image".');
    }

    public function test_un_executable_deguise_en_image_est_refuse(): void
    {
        $user = $this->creerUtilisateur('vendeur');

        // Nom et extension plausibles, contenu qui ne l'est pas. C'est le
        // contenu reel qui doit trancher : un .jpg qui contient du PHP est
        // un script, pas une image.
        //
        // Le contenu est volontairement anodin. Une charge utile realiste
        // — un appel a system() sur un parametre de l'URL — correspond a
        // une signature de webshell : l'antivirus du poste verrouille
        // alors le fichier temporaire, et le test echoue pour une raison
        // qui n'a rien a voir avec ce qu'il verifie.
        $piege = $this->fichierReel('innocent.jpg', "<?php echo 'bonjour'; ?>");

        $this->postJson('/api/upload/image', ['image' => $piege], $this->entetes($user))
            ->assertStatus(422)
            ->assertJsonPath('error', 'Invalid file type. Allowed: jpg, png, webp, gif.');

        // Et rien n'a ete ecrit sur le disque.
        $this->assertCount(0, glob(public_path(self::DOSSIER_TEST . '/img_*')) ?: []);
    }

    public function test_le_nom_ecrit_neutralise_la_double_extension(): void
    {
        $user = $this->creerUtilisateur('vendeur');

        // « photo.php.png » : certaines configurations Apache executent un
        // tel fichier malgre l'extension finale. Le nom retenu derive du
        // type MIME detecte, jamais du nom fourni par le client.
        $reponse = $this->postJson('/api/upload/image', [
            'image' => $this->imagePng('photo.php.png'),
        ], $this->entetes($user));

        $reponse->assertStatus(201);

        $nom = $reponse->json('filename');

        $this->assertStringEndsWith('.png', $nom);
        $this->assertStringNotContainsString('php', $nom);
    }

    public function test_un_fichier_trop_volumineux_est_refuse(): void
    {
        $user = $this->creerUtilisateur('vendeur');

        // create() ne demande pas GD : seuls le nom et la taille comptent
        // ici, la taille etant verifiee avant le type.
        $lourd = UploadedFile::fake()->create('enorme.png', 6 * 1024);

        $this->postJson('/api/upload/image', ['image' => $lourd], $this->entetes($user))
            ->assertStatus(422)
            ->assertJsonPath('error', 'File exceeds 5 MB limit.');
    }

    // ------------------------------------------------------------------
    // Depot multiple
    // ------------------------------------------------------------------

    public function test_le_depot_multiple_renvoie_un_tableau_d_urls(): void
    {
        $user = $this->creerUtilisateur('vendeur');

        $reponse = $this->postJson('/api/upload/images', [
            'images' => [
                $this->imagePng('a.png'),
                $this->imageGif('b.gif'),
            ],
        ], $this->entetes($user));

        $reponse->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonMissingPath('data')
            // Aucun rejet : la cle est absente, pas presente et vide.
            ->assertJsonMissingPath('rejets');

        $this->assertCount(2, $reponse->json('urls'));
    }

    public function test_un_succes_partiel_liste_les_rejets(): void
    {
        $user = $this->creerUtilisateur('vendeur');

        $reponse = $this->postJson('/api/upload/images', [
            'images' => [
                $this->imagePng('bonne.png'),
                $this->fichierReel('mauvaise.txt', 'du texte, pas une image'),
            ],
        ], $this->entetes($user));

        $reponse->assertStatus(201);

        $this->assertCount(1, $reponse->json('urls'));
        $this->assertCount(1, $reponse->json('rejets'));
        $this->assertSame('mauvaise.txt', $reponse->json('rejets.0.fichier'));
        $this->assertNotEmpty($reponse->json('rejets.0.motif'));
    }

    public function test_tout_rejeter_est_un_echec_et_non_une_creation(): void
    {
        $user = $this->creerUtilisateur('vendeur');

        // Un 201 avec une liste vide serait indiscernable d'un succes cote
        // client : le tableau de bord ajouterait zero image sans rien dire.
        $this->postJson('/api/upload/images', [
            'images' => [$this->fichierReel('note.txt', 'du texte')],
        ], $this->entetes($user))
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonCount(1, 'details.rejets');
    }

    public function test_au_dela_de_cinq_fichiers_le_surplus_est_ignore(): void
    {
        $user = $this->creerUtilisateur('vendeur');

        $reponse = $this->postJson('/api/upload/images', [
            'images' => array_map(
                fn (int $i) => $this->imagePng("img{$i}.png"),
                range(1, 8)
            ),
        ], $this->entetes($user));

        $reponse->assertStatus(201);

        $this->assertCount(5, $reponse->json('urls'));
    }

    // ------------------------------------------------------------------
    // Fabriques
    // ------------------------------------------------------------------

    private function imagePng(string $nom): UploadedFile
    {
        return $this->fichierReel($nom, (string) base64_decode(self::PNG_1X1, true));
    }

    private function imageGif(string $nom): UploadedFile
    {
        return $this->fichierReel($nom, (string) base64_decode(self::GIF_1X1, true));
    }

    /**
     * Ecrit un vrai fichier temporaire et retourne un UploadedFile pointant
     * dessus.
     *
     * Le dernier argument `true` place l'objet en mode test : sans lui,
     * move() exigerait que le fichier provienne d'un vrai transfert HTTP.
     * Le type MIME n'est volontairement pas renseigne — il sera devine
     * depuis le contenu, ce qui est precisement ce que l'on veut eprouver.
     */
    private function fichierReel(string $nom, string $contenu): UploadedFile
    {
        $chemin = tempnam(sys_get_temp_dir(), 'mc_test_');
        file_put_contents($chemin, $contenu);

        $this->temporaires[] = $chemin;

        return new UploadedFile($chemin, $nom, null, null, true);
    }
}
