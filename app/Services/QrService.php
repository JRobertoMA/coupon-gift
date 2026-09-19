<?php

declare(strict_types=1);

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\SvgWriter;

/**
 * Genera los QR de los cupones.
 *
 * Se emiten como SVG EN LÍNEA, no como <img> ni como PNG en base64: un SVG es
 * vectorial y sale nítido en papel a cualquier tamaño, que es justo lo que hace
 * falta en un ticket de 0.85in de ancho. Un PNG de 58px se vería pixelado.
 */
class QrService
{
    /** Lado del QR dentro del ticket, en píxeles CSS. */
    public const SIZE = 58;

    /** ¿Está instalada la librería? Sin composer install no lo está. */
    public static function available(): bool
    {
        return class_exists(Builder::class);
    }

    /** URL absoluta a la que apunta el QR de un cupón. */
    public static function urlFor(string $code): string
    {
        return APP_URL . '/cupon/' . rawurlencode($code);
    }

    /**
     * Devuelve el SVG del QR listo para incrustar.
     *
     * $ec es 'high' o 'medium'. Alta aguanta más daño (estos códigos se recortan
     * a mano y acaban doblados en un bolsillo), pero mete más módulos: 37×37
     * frente a 33×33 para la URL. En los QR pequeños eso deja el módulo por
     * debajo de lo que lee un móvil, así que mediano y pequeño usan media.
     */
    public static function svg(string $code, int $size = self::SIZE, string $ec = 'high'): string
    {
        if (!self::available()) {
            return self::placeholder($size);
        }

        $level = $ec === 'medium' ? ErrorCorrectionLevel::Medium : ErrorCorrectionLevel::High;

        try {
            $result = (new Builder())
                ->writer(new SvgWriter())
                ->data(self::urlFor($code))
                ->errorCorrectionLevel($level)
                ->size($size)
                ->margin(0)
                // Sin esto endroid redondea el módulo a píxeles ENTEROS y centra
                // el sobrante como margen: pedías 44 px y salía un QR de 33 px
                // con módulo de 0,26 mm, ilegible. En SVG el módulo fraccionario
                // no cuesta nada, y así el QR ocupa el tamaño pedido de verdad.
                ->roundBlockSizeMode(RoundBlockSizeMode::None)
                ->build();

            // El writer emite un documento SVG completo con su cabecera XML;
            // se quita para poder incrustarlo dentro del HTML.
            $svg = $result->getString();
            $svg = preg_replace('/<\?xml.*?\?>\s*/s', '', $svg) ?? $svg;
            $svg = preg_replace('/<!DOCTYPE.*?>\s*/s', '', $svg) ?? $svg;

            return trim($svg);
        } catch (Throwable $e) {
            error_log('[coupon-gift] QR falló para ' . $code . ': ' . $e->getMessage());
            return self::placeholder($size);
        }
    }

    /**
     * Hueco con el mismo tamaño que el QR real.
     *
     * Así la maqueta del ticket no se descuadra si falta la librería, y el
     * cupón sigue siendo canjeable tecleando el código.
     */
    private static function placeholder(int $size): string
    {
        return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 ' . $size . ' ' . $size . '"'
            . ' role="img" aria-label="Código QR no disponible">'
            . '<rect x="0.75" y="0.75" width="' . ($size - 1.5) . '" height="' . ($size - 1.5) . '"'
            . ' fill="none" stroke="currentColor" stroke-width="1.5" stroke-dasharray="3 3"/>'
            . '</svg>';
    }
}
