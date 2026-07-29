<?php

declare(strict_types=1);

namespace App\Services;

/**
 * CAPTCHA maison, arithmetique, sans etat serveur.
 *
 * Pourquoi pas reCAPTCHA : la soutenance comporte huit minutes de
 * demonstration live. Un appel sortant vers un service tiers y est un point
 * de defaillance qui ne se rattrape pas. Ce defi-ci n'a besoin ni de cle,
 * ni de domaine declare, ni de reseau.
 *
 * Sans etat : le defi est un jeton signe en HMAC-SHA256 qui porte sa propre
 * reponse et sa date d'expiration. Aucune session, aucune table. Le jeton
 * ne peut etre ni forge ni relu apres expiration, et la reponse n'y est
 * jamais lisible en clair.
 *
 * Limite assumee : ce captcha arrete un script naif, pas un adversaire
 * determine qui resoudrait « 7 + 3 ». Sa fonction reelle est de ralentir
 * le remplissage automatique de formulaire ; le verrouillage de compte
 * apres cinq echecs reste la mesure qui bloque reellement le brute-force.
 */
class CaptchaService
{
    private const OPERATIONS = ['+', '-', 'x'];

    /**
     * Genere un defi. Le jeton est a renvoyer tel quel avec la reponse.
     *
     * @return array{question: string, token: string, expire_dans: int}
     */
    public function genererDefi(): array
    {
        [$question, $reponse] = $this->tirerOperation();

        $expiration = time() + (int) config('marketcraft.auth.captcha_ttl', 300);
        $sel        = bin2hex(random_bytes(8));

        return [
            'question'    => $question,
            'token'       => $this->signer($reponse, $expiration, $sel),
            'expire_dans' => $expiration - time(),
        ];
    }

    /**
     * Verifie une reponse contre son jeton.
     */
    public function verifier(?string $token, mixed $reponse): bool
    {
        if ($token === null || $token === '' || $reponse === null || $reponse === '') {
            return false;
        }

        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return false;
        }

        [$expiration, $sel, $signature] = $parts;

        if (! ctype_digit($expiration) || (int) $expiration < time()) {
            return false;
        }

        $attendu = $this->calculerSignature((string) (int) $reponse, (int) $expiration, $sel);

        // Comparaison a temps constant : un `===` sur des chaines permet de
        // deviner la signature octet par octet en mesurant le temps de reponse.
        return hash_equals($attendu, $signature);
    }

    /**
     * @return array{0: string, 1: int}  [question, reponse]
     */
    private function tirerOperation(): array
    {
        $operation = self::OPERATIONS[random_int(0, count(self::OPERATIONS) - 1)];

        return match ($operation) {
            '+' => (function () {
                $a = random_int(2, 19);
                $b = random_int(2, 19);

                return ["Combien font {$a} + {$b} ?", $a + $b];
            })(),
            // On tire toujours a >= b pour ne jamais poser de soustraction
            // a resultat negatif : un champ « -4 » invite a la faute de saisie.
            '-' => (function () {
                $a = random_int(10, 30);
                $b = random_int(2, 9);

                return ["Combien font {$a} - {$b} ?", $a - $b];
            })(),
            default => (function () {
                $a = random_int(2, 9);
                $b = random_int(2, 9);

                return ["Combien font {$a} x {$b} ?", $a * $b];
            })(),
        };
    }

    private function signer(int $reponse, int $expiration, string $sel): string
    {
        return $expiration . '.' . $sel . '.' . $this->calculerSignature((string) $reponse, $expiration, $sel);
    }

    private function calculerSignature(string $reponse, int $expiration, string $sel): string
    {
        return hash_hmac(
            'sha256',
            $reponse . '|' . $expiration . '|' . $sel,
            (string) config('app.key')
        );
    }
}
