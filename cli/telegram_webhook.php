<?php

declare(strict_types=1);

/**
 * Registra, consulta o borra el webhook del bot en Telegram.
 *
 *   php cli/telegram_webhook.php set
 *   php cli/telegram_webhook.php info
 *   php cli/telegram_webhook.php delete
 *
 * Telegram solo acepta webhooks HTTPS con certificado válido y en los puertos
 * 443, 80, 88 u 8443: APP_URL tiene que ser el dominio público, no localhost.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo por línea de órdenes.');
}

define('BASE_PATH', dirname(__DIR__));

require_once BASE_PATH . '/config/app.php';

$token  = (string) env('TELEGRAM_BOT_TOKEN', '');
$secret = (string) env('TELEGRAM_WEBHOOK_SECRET', '');

if ($token === '') {
    fwrite(STDERR, "Falta TELEGRAM_BOT_TOKEN en .env.\n");
    exit(1);
}

/** Llamada simple a la API. */
function api(string $token, string $method, array $params = []): array
{
    $ch = curl_init('https://api.telegram.org/bot' . $token . '/' . $method);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($params),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $raw = curl_exec($ch);
    curl_close($ch);

    $decoded = json_decode((string) $raw, true);
    return is_array($decoded) ? $decoded : ['ok' => false, 'raw' => $raw];
}

switch ($argv[1] ?? 'info') {
    case 'set':
        if ($secret === '') {
            fwrite(STDERR, "Falta TELEGRAM_WEBHOOK_SECRET en .env.\n");
            fwrite(STDERR, "Genéralo con: php -r \"echo bin2hex(random_bytes(16));\"\n");
            exit(1);
        }

        $url = APP_URL . '/telegram/webhook';
        if (!str_starts_with($url, 'https://')) {
            fwrite(STDERR, "APP_URL tiene que ser HTTPS público. Ahora es: $url\n");
            exit(1);
        }

        $result = api($token, 'setWebhook', [
            'url'             => $url,
            'secret_token'    => $secret,
            // Solo interesan los botones pulsados; lo demás no se procesa.
            'allowed_updates' => json_encode(['callback_query']),
        ]);

        echo ($result['ok'] ?? false)
            ? "Webhook registrado en $url\n"
            : "Error: " . json_encode($result, JSON_UNESCAPED_UNICODE) . "\n";
        break;

    case 'delete':
        $result = api($token, 'deleteWebhook');
        echo ($result['ok'] ?? false) ? "Webhook borrado.\n" : "Error.\n";
        break;

    default:
        $result = api($token, 'getWebhookInfo');
        echo json_encode($result['result'] ?? $result,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        break;
}
