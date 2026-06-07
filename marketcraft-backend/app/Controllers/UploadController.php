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

        for ($i = 0; $i < min($count, 5); $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
            if ($files['size'][$i]  > self::MAX_SIZE)   continue;

            $finfo    = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $files['tmp_name'][$i]);
            finfo_close($finfo);
            if (!in_array($mimeType, self::ALLOWED_MIME, true)) continue;

            $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
            if (!in_array($ext, self::ALLOWED_EXT, true)) continue;

            $filename    = uniqid('img_', true) . '.' . $ext;
            $destination = self::UPLOAD_DIR . $filename;

            if (move_uploaded_file($files['tmp_name'][$i], $destination)) {
                $urls[] = $appUrl . '/uploads/' . $filename;
            }
        }

        $this->json(['success' => true, 'urls' => $urls], 201);
    }

    private function uploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File too large.',
            UPLOAD_ERR_PARTIAL  => 'File partially uploaded.',
            UPLOAD_ERR_NO_FILE  => 'No file sent.',
            UPLOAD_ERR_NO_TMP_DIR => 'No temp directory.',
            UPLOAD_ERR_CANT_WRITE => 'Cannot write to disk.',
            default             => 'Unknown error.',
        };
    }
}
