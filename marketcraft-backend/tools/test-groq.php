<?php

declare(strict_types=1);

/**
 * Diagnostic de la recherche IA — a executer en ligne de commande :
 *
 *   cd marketcraft-backend
 *   C:\xampp\php\php.exe tools\test-groq.php
 *
 * Le script court-circuite l'application : il lit le .env, appelle
 * directement le fournisseur et affiche la reponse brute. Il permet de
 * savoir en une fois si le probleme vient de la cle, du modele, du reseau
 * ou du code applicatif.
 */

$racine  = dirname(__DIR__);
$envFile = $racine . '/.env';

echo "=== 1. Lecture du .env ===\n";

if (!file_exists($envFile)) {
    exit("ECHEC : {$envFile} introuvable.\n");
}

$env = [];
foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $ligne) {
    if (str_starts_with(trim($ligne), '#') || !str_contains($ligne, '=')) {
        continue;
    }
    [$cle, $valeur] = explode('=', $ligne, 2);
    $env[trim($cle)] = trim($valeur, " \t\n\r\"'");
}

$apiKey = $env['GROQ_API_KEY'] ?? '';
$model  = ($env['GROQ_MODEL']   ?? '') ?: 'openai/gpt-oss-120b';
$apiUrl = ($env['GROQ_API_URL'] ?? '') ?: 'https://api.groq.com/openai/v1/chat/completions';

if ($apiKey === '') {
    exit("ECHEC : GROQ_API_KEY absente du .env.\n");
}

printf("  cle    : %d caracteres, debut « %s… », prefixe gsk_ %s\n",
    strlen($apiKey), substr($apiKey, 0, 8), str_starts_with($apiKey, 'gsk_') ? 'OK' : 'MANQUANT');
printf("  modele : %s\n", $model);
printf("  url    : %s\n\n", $apiUrl);

echo "=== 2. Appel du fournisseur ===\n";

$corps = json_encode([
    'model'           => $model,
    'max_tokens'      => 200,
    'temperature'     => 0.2,
    'response_format' => ['type' => 'json_object'],
    'messages'        => [
        ['role' => 'system', 'content' => 'Reponds uniquement en JSON avec la forme {"keywords":["mot"]}.'],
        ['role' => 'user',   'content' => 'une table en bois'],
    ],
], JSON_UNESCAPED_UNICODE);

$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $corps,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_USERAGENT      => 'MarketCraft/1.0 (+PHP cURL)',
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ],
]);

$reponse   = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($reponse === false || $curlError !== '') {
    echo "  ECHEC RESEAU : {$curlError}\n";
    if (str_contains($curlError, 'certificate') || str_contains($curlError, 'SSL')) {
        echo "  -> php.ini : renseignez curl.cainfo avec un cacert.pem.\n";
    }
    exit(1);
}

printf("  code HTTP : %d\n\n", $httpCode);
echo "=== 3. Reponse brute ===\n";
echo $reponse . "\n\n";

echo "=== 4. Verdict ===\n";

$data = json_decode((string) $reponse, true);

if ($httpCode === 200 && isset($data['choices'][0]['message']['content'])) {
    echo "  SUCCES : le fournisseur repond correctement.\n";
    echo "  Contenu : " . $data['choices'][0]['message']['content'] . "\n";
    echo "  -> Si l'application echoue malgre tout, le probleme est applicatif\n";
    echo "     (serveur PHP non redemarre, ou .env different de celui-ci).\n";
} elseif ($httpCode === 401) {
    echo "  Cle refusee. Regenerez-la sur console.groq.com/keys.\n";
} elseif ($httpCode === 429) {
    echo "  Quota du palier gratuit atteint. Reessayez dans une minute.\n";
} elseif ($httpCode === 403) {
    echo "  Bloque en amont par Cloudflare.\n";
    echo "  Si ce script envoie bien un User-Agent, c'est votre IP qui est\n";
    echo "  filtree : desactivez tout VPN/proxy, ou essayez un autre reseau.\n";
} elseif ($httpCode === 400) {
    echo "  Requete refusee. Cause la plus frequente : modele retire.\n";
    echo "  Voir console.groq.com/docs/deprecations, puis renseignez\n";
    echo "  GROQ_MODEL dans le .env avec un modele encore actif.\n";
} else {
    echo "  Code inattendu. La reponse brute ci-dessus donne le motif.\n";
}
