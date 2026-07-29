<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\JournalActivite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Journal d'activite (OWASP — Security Logging and Monitoring Failures).
 *
 * Ecrit dans la table `journal_activite`, consultable en back-office.
 * Une panne d'ecriture du journal ne doit jamais faire echouer la requete
 * metier : l'erreur part dans les logs fichier et la requete continue.
 */
class ActivityLogger
{
    /** Cles retirees du contexte avant ecriture, quelle que soit la source. */
    private const CLES_SENSIBLES = [
        'password', 'password_hash', 'current_password', 'mot_de_passe',
        'token', 'access_token', 'refresh_token', 'authorization',
        'api_key', 'secret', 'captcha_reponse',
    ];

    public function __construct(private ?Request $request = null)
    {
        $this->request ??= request();
    }

    public function info(string $action, string $message = '', array $contexte = [], ?int $utilisateurId = null): void
    {
        $this->ecrire($action, 'info', $message, $contexte, $utilisateurId);
    }

    public function avertissement(string $action, string $message = '', array $contexte = [], ?int $utilisateurId = null): void
    {
        $this->ecrire($action, 'avertissement', $message, $contexte, $utilisateurId);
    }

    public function erreur(string $action, string $message = '', array $contexte = [], ?int $utilisateurId = null): void
    {
        $this->ecrire($action, 'erreur', $message, $contexte, $utilisateurId);
    }

    public function critique(string $action, string $message = '', array $contexte = [], ?int $utilisateurId = null): void
    {
        $this->ecrire($action, 'critique', $message, $contexte, $utilisateurId);
    }

    private function ecrire(
        string $action,
        string $niveau,
        string $message,
        array $contexte,
        ?int $utilisateurId
    ): void {
        try {
            JournalActivite::create([
                'utilisateur_id' => $utilisateurId ?? $this->request?->user()?->id,
                'action'         => $action,
                'niveau'         => $niveau,
                'message'        => $message !== '' ? $message : null,
                'ip'             => $this->request?->ip(),
                'user_agent'     => mb_substr((string) $this->request?->userAgent(), 0, 255) ?: null,
                'contexte'       => $contexte !== [] ? $this->assainir($contexte) : null,
            ]);
        } catch (\Throwable $e) {
            // La base est peut-etre indisponible : on ne perd pas
            // l'information pour autant, et la requete metier n'echoue pas
            // a cause du journal.
            Log::error('Ecriture du journal impossible', [
                'action' => $action,
                'motif'  => $e->getMessage(),
            ]);
        }
    }

    /**
     * Retire recursivement tout ce qui ressemble a un secret. Un journal
     * qui fuit des identifiants est pire que pas de journal du tout.
     */
    private function assainir(array $contexte): array
    {
        foreach ($contexte as $cle => $valeur) {
            if (in_array(mb_strtolower((string) $cle), self::CLES_SENSIBLES, true)) {
                $contexte[$cle] = '[masque]';
                continue;
            }

            if (is_array($valeur)) {
                $contexte[$cle] = $this->assainir($valeur);
            }
        }

        return $contexte;
    }
}
