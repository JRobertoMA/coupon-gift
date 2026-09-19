<?php

declare(strict_types=1);

/**
 * La vista de impresión: el diseño del prototipo, tal cual.
 *
 * Acepta parámetros por URL (?estilo=&paleta=&tipografia=&portada=&tonos=) que
 * sobrescriben SOLO esta vista y no se guardan, para poder compartir o probar
 * una combinación sin pisar lo que tiene la cuponera.
 */
class PrintController extends Controller
{
    public function show(string $slug): void
    {
        Auth::require();
        $userId = (int) Auth::user();

        $book = (new CouponBook())->findBySlugForUser($slug, $userId);
        if ($book === null) {
            $this->notFound();
        }

        $isOwner = (int) $book['owner_id'] === $userId;
        if ((int) $book['is_draft'] === 1 && !$isOwner) {
            $this->notFound();
        }

        // Los parámetros de URL pasan por la misma whitelist que el formulario.
        // Un valor que no esté en la lista NO cae al default global sino al de
        // la cuponera: si la cuponera es mediana y llega ?tamano=gigante, sigue
        // siendo mediana.
        $q = fn (string $key): ?string => isset($_GET[$key]) ? (string) $_GET[$key] : null;

        $palette    = ($v = $q('paleta')) !== null && isset(Design::PALETTES[(int) $v]) && ctype_digit($v)
                    ? (int) $v : Design::palette($book['palette']);
        $style      = ($v = $q('estilo')) !== null && isset(Design::STYLES[$v])
                    ? $v : Design::style($book['style']);
        $typography = ($v = $q('tipografia')) !== null && isset(Design::FONTS[$v])
                    ? $v : Design::typography($book['typography']);
        $size       = ($v = $q('tamano')) !== null && isset(Design::SIZES[$v])
                    ? $v : Design::size($book['ticket_size'] ?? null);

        $showCover = isset($_GET['portada']) ? $_GET['portada'] === '1' : (bool) $book['show_cover'];
        $showTone  = isset($_GET['tonos'])   ? $_GET['tonos']   === '1' : (bool) $book['show_tone_tag'];

        $coupons  = (new Coupon())->forBook((int) $book['id']);
        $sizeSpec = Design::SIZES[$size];

        // La paginación vive solo aquí. Cuántos caben por hoja lo dice el tamaño
        // (Design::SIZES): 3 en grande, 6 en mediano apaisado, 10 en pequeño.
        $sheets = array_chunk($coupons, $sizeSpec['per_sheet']);

        $this->render('cuponeras/imprimir', [
            'title'      => $book['title'],
            'book'       => $book,
            'sheets'     => $sheets,
            'total'      => count($coupons),
            'showCover'  => $showCover,
            'showTone'   => $showTone,
            'useQr'      => (bool) $book['use_qr'],
            'size'       => $size,
            'sizeSpec'   => $sizeSpec,
            'themeStyle' => Design::styleAttr($palette, $style, $typography),
            'settings'   => [
                'palette'    => $palette,
                'style'      => $style,
                'typography' => $typography,
                'size'       => $size,
            ],
        ], 'print');
    }
}
