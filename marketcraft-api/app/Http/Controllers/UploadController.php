<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * Depot d'images.
 *
 * Attention a la forme des reponses : comme les routes d'authentification,
 * ces deux endpoints renvoient leurs donnees AU PREMIER NIVEAU, hors de
 * l'enveloppe `data`. Le front lit `data.url`, `data.urls` et
 * `data.rejets` (DashboardPage, lignes 66 et 130). Les envelopper
 * casserait l'ajout d'images sans lever la moindre erreur visible.
 *
 * Les URL renvoyees sont ABSOLUES, construites depuis APP_URL. C'est un
 * choix contestable — deplacer le service oblige a reecrire la base, ce
 * que fait la commande db:reparer — mais le front en depend en l'etat.
 */
class UploadController extends Controller
{
    public function __construct(private readonly ActivityLogger $journal)
    {
    }

    // ------------------------------------------------------------------
    // POST /upload/image
    // ------------------------------------------------------------------

    public function image(Request $request): JsonResponse
    {
        if (! $request->hasFile('image')) {
            return $this->echec('No file uploaded. Field name must be "image".', 422);
        }

        $fichier = $request->file('image');

        if (! $fichier instanceof UploadedFile) {
            return $this->echec('No file uploaded. Field name must be "image".', 422);
        }

        if ($fichier->getError() !== UPLOAD_ERR_OK) {
            return $this->echec('Upload error: ' . $this->messageErreur($fichier->getError()), 422);
        }

        if ($fichier->getSize() > $this->tailleMax()) {
            return $this->echec('File exceeds 5 MB limit.', 422);
        }

        // Le type MIME est deduit du contenu reel du fichier, jamais de
        // l'en-tete envoye par le client : un script PHP renomme en .jpg
        // s'annonce volontiers comme image/jpeg.
        $mime = $this->mimeReel($fichier);

        if (! in_array($mime, $this->mimesAutorises(), true)) {
            return $this->echec('Invalid file type. Allowed: jpg, png, webp, gif.', 422);
        }

        if (! in_array($this->extensionClient($fichier), $this->extensionsAutorisees(), true)) {
            return $this->echec('Invalid file extension.', 422);
        }

        $nomFichier = $this->deposer($fichier, $mime);

        if ($nomFichier === null) {
            return $this->echec('Failed to save file.', 500);
        }

        $this->journal->info(
            'upload_image',
            "Image deposee : {$nomFichier}",
            ['fichier' => $nomFichier],
            $request->user()?->id
        );

        return ApiResponse::raw([
            'success'  => true,
            'url'      => $this->urlPublique($nomFichier),
            'filename' => $nomFichier,
        ], 201);
    }

    // ------------------------------------------------------------------
    // POST /upload/images
    // ------------------------------------------------------------------

    public function images(Request $request): JsonResponse
    {
        $fichiers = $request->file('images');

        if ($fichiers === null || $fichiers === []) {
            return $this->echec('No files uploaded. Field name must be "images[]".', 422);
        }

        // Le champ peut arriver seul ou en tableau selon le client.
        $fichiers = is_array($fichiers) ? $fichiers : [$fichiers];

        $urls   = [];
        $rejets = [];

        // Chaque fichier rejete est trace avec son motif : un « continue »
        // muet renverrait un 201 avec une liste vide, indiscernable d'un
        // succes cote client.
        foreach (array_slice($fichiers, 0, $this->nombreMax()) as $fichier) {
            $nom = $fichier instanceof UploadedFile
                ? $fichier->getClientOriginalName()
                : 'fichier inconnu';

            $motif = $this->motifDeRejet($fichier);

            if ($motif !== null) {
                $rejets[] = ['fichier' => $nom, 'motif' => $motif];
                continue;
            }

            $nomFichier = $this->deposer($fichier, $this->mimeReel($fichier));

            if ($nomFichier === null) {
                $rejets[] = ['fichier' => $nom, 'motif' => 'Écriture impossible dans public/uploads.'];
                continue;
            }

            $urls[] = $this->urlPublique($nomFichier);
        }

        // Aucun fichier retenu : c'est un echec, pas une creation.
        if ($urls === [] && $rejets !== []) {
            Log::warning('Tous les fichiers deposes ont ete rejetes', ['rejets' => $rejets]);

            return ApiResponse::error($rejets[0]['motif'], 422, ['rejets' => $rejets]);
        }

        $this->journal->info(
            'upload_images',
            count($urls) . ' image(s) deposee(s), ' . count($rejets) . ' rejet(s)',
            ['retenus' => count($urls), 'rejets' => $rejets],
            $request->user()?->id
        );

        $charge = ['success' => true, 'urls' => $urls];

        // Succes partiel : le client doit pouvoir le signaler a l'utilisateur.
        if ($rejets !== []) {
            $charge['rejets'] = $rejets;
        }

        return ApiResponse::raw($charge, 201);
    }

    // ------------------------------------------------------------------
    // Aides
    // ------------------------------------------------------------------

    /**
     * Retourne le motif de rejet d'un fichier, ou null s'il est acceptable.
     */
    private function motifDeRejet(mixed $fichier): ?string
    {
        if (! $fichier instanceof UploadedFile) {
            return 'Fichier illisible.';
        }

        if ($fichier->getError() !== UPLOAD_ERR_OK) {
            return $this->messageErreur($fichier->getError());
        }

        if ($fichier->getSize() > $this->tailleMax()) {
            return 'Fichier supérieur à 5 Mo.';
        }

        $mime = $this->mimeReel($fichier);

        if (! in_array($mime, $this->mimesAutorises(), true)) {
            $libelle = $mime ?? 'type indéterminé';

            return "Type de fichier non autorisé ({$libelle}). Formats acceptés : jpg, png, webp, gif.";
        }

        $extension = $this->extensionClient($fichier);

        if (! in_array($extension, $this->extensionsAutorisees(), true)) {
            return "Extension « .{$extension} » non autorisée.";
        }

        return null;
    }

    /**
     * Ecrit le fichier dans public/uploads sous un nom aleatoire.
     *
     * L'extension est deduite du type MIME detecte, pas du nom fourni par
     * le client : ce dernier peut contenir une double extension
     * (« photo.php.jpg ») ou des caracteres de traversee de repertoire.
     *
     * @return string|null  Nom du fichier ecrit, ou null en cas d'echec.
     */
    private function deposer(UploadedFile $fichier, ?string $mime): ?string
    {
        $extension = $this->extensionDepuisMime($mime) ?? 'jpg';
        $nom       = uniqid('img_', true) . '.' . $extension;

        try {
            $fichier->move($this->repertoire(), $nom);
        } catch (\Throwable $e) {
            Log::error('Ecriture du fichier impossible', [
                'motif'     => $e->getMessage(),
                'repertoire'=> $this->repertoire(),
            ]);

            return null;
        }

        return $nom;
    }

    /**
     * Type MIME reel du fichier, deduit de son contenu par finfo.
     *
     * L'appel est protege : finfo echoue si le fichier est illisible —
     * antivirus qui verrouille le temporaire le temps de l'analyser,
     * quota disque, permissions. Sans cette garde, un fichier qu'on ne
     * peut pas inspecter provoque une erreur 500 la ou un refus poli
     * suffit. Un contenu non identifiable n'est de toute facon jamais
     * acceptable : retourner null revient a le rejeter.
     */
    private function mimeReel(UploadedFile $fichier): ?string
    {
        try {
            return $fichier->getMimeType();
        } catch (\Throwable $e) {
            Log::warning('Type MIME indeterminable', [
                'fichier' => $fichier->getClientOriginalName(),
                'motif'   => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function extensionDepuisMime(?string $mime): ?string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
            default      => null,
        };
    }

    private function extensionClient(UploadedFile $fichier): string
    {
        return mb_strtolower($fichier->getClientOriginalExtension());
    }

    private function urlPublique(string $nomFichier): string
    {
        // Le segment d'URL suit le repertoire configure : les deux ne
        // doivent pas pouvoir diverger.
        $dossier = trim((string) config('marketcraft.upload.directory', 'uploads'), '/');

        return rtrim((string) config('app.url'), '/') . '/' . $dossier . '/' . $nomFichier;
    }

    private function repertoire(): string
    {
        return public_path((string) config('marketcraft.upload.directory', 'uploads'));
    }

    private function tailleMax(): int
    {
        return (int) config('marketcraft.upload.max_size', 5 * 1024 * 1024);
    }

    private function nombreMax(): int
    {
        return (int) config('marketcraft.upload.max_files', 5);
    }

    /** @return string[] */
    private function mimesAutorises(): array
    {
        return (array) config('marketcraft.upload.mimes', []);
    }

    /** @return string[] */
    private function extensionsAutorisees(): array
    {
        return (array) config('marketcraft.upload.extensions', []);
    }

    private function messageErreur(int $code): string
    {
        // UPLOAD_ERR_INI_SIZE vient de php.ini, pas de l'application : PHP
        // plafonne upload_max_filesize a 2 Mo par defaut, en deca des 5 Mo
        // annonces par l'interface. Le message doit le dire, sans quoi
        // l'utilisateur croit a un bug.
        $limitePhp = ini_get('upload_max_filesize') ?: '?';

        return match ($code) {
            UPLOAD_ERR_INI_SIZE   => "Fichier refusé par PHP : il dépasse upload_max_filesize ({$limitePhp}) dans php.ini.",
            UPLOAD_ERR_FORM_SIZE  => 'Fichier trop volumineux.',
            UPLOAD_ERR_PARTIAL    => 'Fichier transféré partiellement.',
            UPLOAD_ERR_NO_FILE    => 'Aucun fichier envoyé.',
            UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire introuvable (php.ini).',
            UPLOAD_ERR_CANT_WRITE => 'Écriture sur le disque impossible.',
            UPLOAD_ERR_EXTENSION  => 'Transfert interrompu par une extension PHP.',
            default               => 'Erreur inconnue.',
        };
    }
}
