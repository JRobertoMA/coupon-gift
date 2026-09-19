<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

/**
 * Envío por correo con PHPMailer sobre SMTP.
 *
 * La imagen php:8.5-apache no trae MTA, así que mail() no funciona: hace falta
 * un SMTP real, configurado en .env.
 *
 * Un correo no puede llevar botones que hagan POST, así que el aviso de canje
 * lleva dos enlaces firmados de un solo uso a /confirmar/{token}.
 */
class MailChannel
{
    public static function configured(): bool
    {
        return (string) env('MAIL_HOST', '') !== '' && (string) env('MAIL_FROM', '') !== '';
    }

    public static function send(array $payload): void
    {
        if (!class_exists(PHPMailer::class)) {
            throw new RuntimeException('Falta PHPMailer: ejecuta composer install.');
        }
        if (!self::configured()) {
            throw new RuntimeException('Falta la configuración MAIL_* en .env.');
        }

        $to = (string) ($payload['to_email'] ?? '');
        if ($to === '') {
            throw new RuntimeException('El destinatario no tiene correo.');
        }

        [$subject, $html] = ($payload['kind'] ?? '') === 'requested'
            ? self::requestedBody($payload)
            : self::resolvedBody($payload);

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = (string) env('MAIL_HOST');
        $mail->Port       = (int) env('MAIL_PORT', '587');
        $mail->SMTPAuth   = true;
        $mail->Username   = (string) env('MAIL_USER', '');
        $mail->Password   = (string) env('MAIL_PASS', '');
        $mail->CharSet    = 'UTF-8';
        $mail->Timeout    = 20;

        $encryption = strtolower((string) env('MAIL_ENCRYPTION', 'tls'));
        if ($encryption === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($encryption === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPSecure  = '';
            $mail->SMTPAutoTLS = false;
        }

        $mail->setFrom((string) env('MAIL_FROM'), (string) env('MAIL_FROM_NAME', 'Cuponeras'));
        $mail->addAddress($to, (string) ($payload['to_name'] ?? ''));
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html;
        $mail->AltBody = trim(strip_tags(str_replace(['<br>', '</p>'], "\n", $html)));

        $mail->send();
    }

    /** @return array{0:string,1:string} asunto y cuerpo HTML */
    private static function requestedBody(array $payload): array
    {
        $fulfillUrl = APP_URL . '/confirmar/' . $payload['fulfill_token'];
        $declineUrl = APP_URL . '/confirmar/' . $payload['decline_token'];

        $subject = sprintf('%s ha pedido un cupón: %s',
            (string) $payload['from_name'], (string) $payload['coupon_title']);

        $html = self::wrap(
            '<p><strong>' . self::esc($payload['from_name']) . '</strong> acaba de canjear un cupón '
            . 'de «' . self::esc($payload['book_title']) . '».</p>'
            . '<div style="border:1.5px dashed #A9917A;border-radius:3px;padding:16px 20px;'
            . 'background:#F6EBD8;margin:20px 0">'
            . '<div style="font-size:22px;font-weight:700;color:#3B3027">'
            . self::esc($payload['coupon_title']) . '</div>'
            . '<div style="font-family:monospace;font-size:13px;color:#8a7a68;margin-top:6px">'
            . self::esc($payload['coupon_code']) . '</div>'
            . '</div>'
            . '<p>¿Lo cumples?</p>'
            . '<p>'
            . self::button($fulfillUrl, '✅ Sí, lo cumplo', '#4E7A6F')
            . ' &nbsp; '
            . self::button($declineUrl, '✖️ Ahora no', '#B4543A')
            . '</p>'
            . '<p style="font-size:12px;color:#8a7a68">Cada enlace sirve una sola vez y caduca en 30 días. '
            . 'También puedes responder desde Telegram o entrar en la cuponera.</p>'
        );

        return [$subject, $html];
    }

    /** @return array{0:string,1:string} asunto y cuerpo HTML */
    private static function resolvedBody(array $payload): array
    {
        $subject = sprintf('Tu cupón «%s»: %s',
            (string) $payload['coupon_title'], (string) $payload['status_label']);

        $html = self::wrap(
            '<p><strong>' . self::esc($payload['from_name']) . '</strong> marcó tu cupón como '
            . '<strong>' . self::esc($payload['status_label']) . '</strong>.</p>'
            . '<div style="border:1.5px dashed #A9917A;border-radius:3px;padding:16px 20px;'
            . 'background:#F6EBD8;margin:20px 0">'
            . '<div style="font-size:22px;font-weight:700;color:#3B3027">'
            . self::esc($payload['coupon_title']) . '</div></div>'
            . '<p>' . self::button(APP_URL . '/c/' . $payload['book_slug'], 'Ver la cuponera', '#B4543A') . '</p>'
        );

        return [$subject, $html];
    }

    private static function button(string $url, string $label, string $color): string
    {
        return '<a href="' . self::esc($url) . '" style="display:inline-block;padding:11px 20px;'
            . 'background:' . $color . ';color:#fff;text-decoration:none;border-radius:4px;'
            . 'font-weight:600;font-size:15px">' . $label . '</a>';
    }

    private static function wrap(string $inner): string
    {
        return '<div style="font-family:-apple-system,Segoe UI,Roboto,sans-serif;font-size:15px;'
            . 'line-height:1.6;color:#3B3027;max-width:560px;margin:0 auto;padding:24px">'
            . $inner . '</div>';
    }

    private static function esc(mixed $text): string
    {
        return htmlspecialchars((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
