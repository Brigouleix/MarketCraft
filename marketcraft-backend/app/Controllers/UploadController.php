<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;

class UploadController extends Controller
{
    private const UPLOAD_DIR  = __DIR__ . '/../../public/uploads/';
    private const MAX_SIZE    = 5 * 1024 * 1024; // 5 Mo
    private const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    private const ALLOWED_EXT  = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    // ------------------------------------------------------------------
    // POST /upload/image  (JWT required)
    // ------------------------------------------------------------------

    public function image(array $params = []): void
    {
        $auth = Auth::getCurrentUser();
        if ($auth === null) {
            $this->error('Unauthorized.', 401);
            return;
        }

        if (empty($_FILES['image'])) {
            $this->error('No file uploaded. Field name must be "image".', 422);
            return;
        }

        $file = $_FILES['image'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->error('Upload error: ' . $this->uploadErrorMessage($file['error']), 422);
            return;
        }

        if ($file['size'] > self::MAX_SIZE) {
            $this->error('File exceeds 5 MB limit.', 422);
            return;
        }

        // Vérification du type MIME réel (pas celui envoyé par le client)
        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, self::ALLOWED_MIME, true)) {
            $this->error('Invalid file type. Allowed: jpg, png, webp, gif.', 422);
            return;
        }

        $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXT, true)) {
            $this->error('Invalid file extension.', 422);
            return;
        }

        // Créer le répertoire si besoin
        if (!is_dir(self::UPLOAD_DIR)) {
            mkdir(self::UPLOAD_DIR, 0755, true);
        }

        $filename    = uniqid('img_', true) . '.' . $ext;
        $destination = self::UPLOAD_DIR . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            $this->error('Failed to save file.', 500);
            return;
        }

        $appUrl = rtrim($_ENV['APP_URL'] ?? getenv('APP_URL') ?: 'http://localhost:8000', '/');
        $url    = $appUrl . '/uploads/' . $filename;

        $this->json([
            'success'  => true,
            'url'      => $url,
            'filename' => $filename,
        ], 201);
    }

    // ------------------------------------------------------------------
    // POST /upload/images  – Upload multiple (max 5)
    // ------------------------------------------------------------------

    public function images(array $params = []): void
    {
        $auth = Auth::getCurrentUser();
        if ($auth === null) {
            $this->error('Unauthorized.', 401);
            return;
        }

        if (empty($_FILES['images'])) {
            $this->error('No files uploaded. Field name must be "images[]".', 422);
            return;
        }

        $files  = $_FILES['images'];
        $count  = count($files['name']);
        $appUrl = rtrim($_ENV['APP_URL'] ?? getenv('APP_URL') ?: 'http://localhost:8000', '/');
        $urls   = [];

        if (!is_dir(self::UPLOAD_DIR)) {
            mkdir(self::UPLOAD_DIR, 0755, true);
        }

        // Chaque fichier rejeté est tracé avec son motif : un « continue »
        // silencieux renvoyait un 201 avec une liste vide, indiscernable
        // d'un succès côté client.
        $rejets = [];

        for ($i = 0; $i < min($count, 5); $i++) {
            $nom = (string) ($files['name'][$i] ?? "fichier {$i}");

            if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                $rejets[] = ['fichier' => $nom, 'motif' => $this->uploadErrorMessage($files['error'][$i])];
                continue;
            }

            if ($files['size'][$i] > self::MAX_SIZE) {
                $rejets[] = ['fichier' => $nom, 'motif' => 'Fichier supérieur à 5 Mo.'];
                continue;
            }

            $finfo    = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $files['tmp_name'][$i]);
            finfo_close($finfo);

            if (!in_array($mimeType, self::ALLOWED_MIME, true)) {
                $rejets[] = ['fichier' => $nom, 'motif' => "Type de fichier non autorisé ({$mimeType}). Formats acceptés : jpg, png, webp, gif."];
                continue;
            }

            $ext = strtolower(pathinfo($nom, PATHINFO_EXTENSION));

            if (!in_array($ext, self::ALLOWED_EXT, true)) {
                $rejets[] = ['fichier' => $nom, 'motif' => "Extension « .{$ext} » non autorisée."];
                continue;
            }

            $filename    = uniqid('img_', true) . '.' . $ext;
            $destination = self::UPLOAD_DIR . $filename;

            if (!move_uploaded_file($files['tmp_name'][$i], $destination)) {
                $rejets[] = ['fichier' => $nom, 'motif' => 'Écriture impossible dans public/uploads.'];
                continue;
            }

            $urls[] = $appUrl . '/uploads/' . $filename;
        }

        // Aucun fichier retenu : c'est un échec, pas une création.
        if ($urls === [] && $rejets !== []) {
            error_log('[UploadController] Tous les fichiers ont été rejetés : ' . json_encode($rejets, JSON_UNESCAPED_UNICODE));
            $this->error($rejets[0]['motif'], 422, ['rejets' => $rejets]);
            return;
        }

        $payload = ['success' => true, 'urls' => $urls];

        // Succès partiel : le client doit pouvoir le signaler.
        if ($rejets !== []) {
            $payload['rejets'] = $rejets;
        }

        $this->json($payload, 201);
    }

    private function uploadErrorMessage(int $code): string
    {
        // UPLOAD_ERR_INI_SIZE vient de php.ini, pas de l'application : PHP
        // plafonne par defaut upload_max_filesize a 2 Mo, en deca de la limite
        // de 5 Mo annoncee par l'interface. Le message doit le dire.
        $limitePhp = ini_get('upload_max_filesize') ?: '?';

        return match ($code) {
            UPLOAD_ERR_INI_SIZE   => "Fichier refuse par PHP : il depasse upload_max_filesize ({$limitePhp}) dans php.ini.",
            UPLOAD_ERR_FORM_SIZE  => 'Fichier trop volumineux.',
            UPLOAD_ERR_PARTIAL    => 'Fichier transfere partiellement.',
            UPLOAD_ERR_NO_FILE    => 'Aucun fichier envoye.',
            UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire introuvable (php.ini).',
            UPLOAD_ERR_CANT_WRITE => 'Ecriture sur le disque impossible.',
            UPLOAD_ERR_EXTENSION  => 'Transfert interrompu par une extension PHP.',
            default               => 'Erreur inconnue.',
        };
    }
}
