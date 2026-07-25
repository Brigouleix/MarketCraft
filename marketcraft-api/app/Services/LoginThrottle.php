<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TentativeConnexion;
use Carbon\CarbonImmutable;

/**
 * Verrouillage de compte apres echecs repetes.
 *
 * Cinq echecs sur une meme adresse en quinze minutes bloquent les
 * tentatives suivantes pendant le reste de la fenetre. Le decompte repart
 * de zero des qu'une connexion aboutit.
 *
 * Le comptage porte sur l'email et non sur l'IP seule : plusieurs
 * utilisateurs derriere un meme NAT ne doivent pas se bloquer mutuellement.
 * Un compteur par IP existe en parallele, avec un seuil plus large, contre
 * le balayage d'adresses.
 */
class LoginThrottle
{
    /** Fenetre de comptage par IP, en multiples du seuil par email. */
    private const FACTEUR_IP = 4;

    public function __construct(private ?ActivityLogger $journal = null)
    {
        $this->journal ??= new ActivityLogger();
    }

    public function enregistrerEchec(string $email, ?string $ip, ?string $userAgent = null): void
    {
        TentativeConnexion::create([
            'email'      => $this->normaliser($email),
            'ip'         => $ip,
            'reussie'    => 0,
            'user_agent' => $userAgent !== null ? mb_substr($userAgent, 0, 255) : null,
        ]);
    }

    /**
     * Une connexion reussie purge l'historique d'echecs : sans cela, un
     * utilisateur qui se trompe quatre fois puis reussit resterait a une
     * tentative du verrouillage pendant un quart d'heure.
     */
    public function enregistrerSucces(string $email, ?string $ip, ?string $userAgent = null): void
    {
        $email = $this->normaliser($email);

        TentativeConnexion::create([
            'email'      => $email,
            'ip'         => $ip,
            'reussie'    => 1,
            'user_agent' => $userAgent !== null ? mb_substr($userAgent, 0, 255) : null,
        ]);

        TentativeConnexion::query()
            ->where('email', $email)
            ->where('reussie', 0)
            ->delete();
    }

    public function nombreEchecs(string $email): int
    {
        return TentativeConnexion::query()
            ->where('email', $this->normaliser($email))
            ->where('reussie', 0)
            ->where('created_at', '>=', $this->debutFenetre())
            ->count();
    }

    public function estVerrouille(string $email): bool
    {
        return $this->nombreEchecs($email) >= $this->maxTentatives();
    }

    /**
     * Nombre de secondes restantes avant deverrouillage, ou 0 si le compte
     * n'est pas verrouille. Sert a construire le message d'erreur.
     */
    public function secondesRestantes(string $email): int
    {
        $derniere = TentativeConnexion::query()
            ->where('email', $this->normaliser($email))
            ->where('reussie', 0)
            ->where('created_at', '>=', $this->debutFenetre())
            ->latest('created_at')
            ->value('created_at');

        if ($derniere === null) {
            return 0;
        }

        $deblocage = CarbonImmutable::parse($derniere)
            ->addMinutes($this->dureeMinutes());

        // Carbon 3 renvoie un flottant depuis diffInSeconds(), la ou Carbon 2
        // renvoyait un entier. Sans cette conversion, la reponse de
        // verrouillage leve une TypeError et sort en 500 : le compte reste
        // protege, mais l'utilisateur recoit une erreur serveur au lieu du
        // message lui indiquant combien de temps patienter.
        $restantes = (int) CarbonImmutable::now()->diffInSeconds($deblocage, false);

        return max(0, $restantes);
    }

    /** Le captcha se declenche avant le verrouillage, pas apres. */
    public function captchaRequis(string $email): bool
    {
        if (! config('marketcraft.auth.captcha_enabled')) {
            return false;
        }

        return $this->nombreEchecs($email) >= (int) config('marketcraft.auth.captcha_threshold');
    }

    /** Balayage d'adresses depuis une meme IP. */
    public function ipSuspecte(?string $ip): bool
    {
        if ($ip === null) {
            return false;
        }

        $echecs = TentativeConnexion::query()
            ->where('ip', $ip)
            ->where('reussie', 0)
            ->where('created_at', '>=', $this->debutFenetre())
            ->count();

        return $echecs >= $this->maxTentatives() * self::FACTEUR_IP;
    }

    private function debutFenetre(): CarbonImmutable
    {
        return CarbonImmutable::now()->subMinutes($this->dureeMinutes());
    }

    private function maxTentatives(): int
    {
        return (int) config('marketcraft.auth.max_attempts', 5);
    }

    private function dureeMinutes(): int
    {
        return (int) config('marketcraft.auth.lockout_minutes', 15);
    }

    private function normaliser(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
