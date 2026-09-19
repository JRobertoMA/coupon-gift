<?php

declare(strict_types=1);

/**
 * Tokens de diseño portados del prototipo de Claude Design
 * («Cupones de Pareja - Hechos a Mano.dc.html»).
 *
 * Es la ÚNICA fuente de verdad del tema: PHP la usa para renderizar y la
 * inyecta con json_encode() para que el panel de controles en JS reutilice las
 * mismas tablas. Si se duplican los valores en el CSS, se desincronizan.
 *
 * Todo el tema son variables CSS sobre un único <div> raíz, igual que en el
 * prototipo: cambiar paleta/estilo/tipografía es reescribir variables, nunca
 * volver a renderizar el documento.
 */
class Design
{
    /** Paletas en el orden del prototipo: [papel, tinta, acento, acento2]. */
    public const PALETTES = [
        ['#E8D8BF', '#3B3027', '#B4543A', '#4E7A6F'],
        ['#F4F1E6', '#2E3A34', '#7A8B5A', '#B08968'],
        ['#FBEFE6', '#463028', '#C97B5A', '#7D6B93'],
        ['#EFF3F6', '#28323B', '#41668C', '#C06B62'],
        ['#F7F0E1', '#332B22', '#8A6A3B', '#6B8E6B'],
    ];

    /**
     * Grosor/estilo/radio del borde del ticket, opacidad del talón y textura.
     *
     * 'paper' => 'white' fuerza fondo blanco en hoja y tarjeta (ignora el color
     * de papel de la paleta) para no gastar tinta: es la variante «para imprimir
     * en cartulina». La tinta, el acento y los bordes siguen viniendo de la
     * paleta, así que el estilo no pierde identidad.
     */
    public const STYLES = [
        'Recortado a mano'   => ['bw' => '1.5px', 'bs' => 'dashed', 'r' => '3px',  'stub' => 0.08, 'tex' => 'puntos'],
        'Papel de cuaderno'  => ['bw' => '1.2px', 'bs' => 'solid',  'r' => '2px',  'stub' => 0.05, 'tex' => 'rayas'],
        'Cartulina con cinta'=> ['bw' => '2.5px', 'bs' => 'solid',  'r' => '6px',  'stub' => 0.12, 'tex' => 'ninguna', 'paper' => 'white'],
        'Libreta cosida'     => ['bw' => '2px',   'bs' => 'dotted', 'r' => '10px', 'stub' => 0.10, 'tex' => 'cuadricula'],
        'Sobre de estraza'   => ['bw' => '1.5px', 'bs' => 'double', 'r' => '2px',  'stub' => 0.14, 'tex' => 'puntos'],
    ];

    /** [manuscrita, monoespaciada, tamaño del título del ticket]. */
    public const FONTS = [
        'Caveat + Courier Prime'              => ["'Caveat', cursive",               "'Courier Prime', monospace", '50px'],
        'Patrick Hand + Special Elite'        => ["'Patrick Hand', cursive",         "'Special Elite', monospace", '40px'],
        'Kalam + Courier Prime'               => ["'Kalam', cursive",                "'Courier Prime', monospace", '40px'],
        'Architects Daughter + Special Elite' => ["'Architects Daughter', cursive",  "'Special Elite', monospace", '36px'],
    ];

    /**
     * Tamaños de cupón impreso. Única fuente de verdad de la rejilla: cuántos
     * caben por hoja, en qué orientación y a qué tamaño va el QR.
     *
     * El grande es el diseño original del prototipo (19,3 × 7,9 cm). Mediano y
     * pequeño son maquetas propias, no un escalado: escalar el grande a 10 cm
     * deja la descripción en 6,7 px y el QR en 0,8 cm, que un móvil no lee.
     *
     * 'ec' es la corrección de errores del QR. A EC alta la URL son 37×37
     * módulos; a media, 33×33. En 44 px eso es la diferencia entre 0,31 mm por
     * módulo (límite de lectura) y 0,35 mm (cómodo). El grande se queda en alta
     * porque le sobra tamaño.
     */
    public const SIZES = [
        'large'  => [
            'label'       => 'Grande',
            'hint'        => '19,3 × 7,9 cm · 3 por hoja vertical',
            'orientation' => 'portrait',
            'per_sheet'   => 3,
            'qr'          => 58,
            'ec'          => 'high',
        ],
        'medium' => [
            'label'       => 'Mediano',
            'hint'        => '12,6 × 6 cm · 6 por hoja apaisada',
            'orientation' => 'landscape',
            'per_sheet'   => 6,
            'qr'          => 44,
            'ec'          => 'medium',
        ],
        'small'  => [
            'label'       => 'Pequeño',
            'hint'        => '9,8 × 4,7 cm · 10 por hoja vertical',
            'orientation' => 'portrait',
            'per_sheet'   => 10,
            'qr'          => 52,
            'ec'          => 'medium',
        ],
    ];

    public const DEFAULT_STYLE      = 'Recortado a mano';
    public const DEFAULT_TYPOGRAPHY = 'Caveat + Courier Prime';
    public const DEFAULT_PALETTE    = 0;
    public const DEFAULT_SIZE       = 'large';

    /** El <link> de Google Fonts exacto del prototipo. */
    public const FONTS_HREF = 'https://fonts.googleapis.com/css2'
        . '?family=Caveat:wght@400..700'
        . '&family=Patrick+Hand'
        . '&family=Kalam:wght@400;700'
        . '&family=Architects+Daughter'
        . '&family=Courier+Prime:ital,wght@0,400;0,700;1,400'
        . '&family=Special+Elite'
        . '&display=swap';

    /** Canales RGB de un color #rrggbb. */
    private static function channels(string $hex): array
    {
        return [
            hexdec(substr($hex, 1, 2)),
            hexdec(substr($hex, 3, 2)),
            hexdec(substr($hex, 5, 2)),
        ];
    }

    /** Interpolación lineal por canal entre dos colores (t de 0 a 1). */
    public static function mix(string $hex, string $towards, float $t): string
    {
        $a = self::channels($hex);
        $b = self::channels($towards);
        $out = '#';
        foreach ($a as $i => $v) {
            $out .= str_pad(dechex((int) round($v + ($b[$i] - $v) * $t)), 2, '0', STR_PAD_LEFT);
        }
        return $out;
    }

    /** Un color hex con alfa, en notación rgba(). */
    public static function rgba(string $hex, float $alpha): string
    {
        [$r, $g, $b] = self::channels($hex);
        return "rgba($r,$g,$b,$alpha)";
    }

    /** Las cuatro texturas de fondo: [background-image, background-size]. */
    private static function textures(string $ink, string $acc2): array
    {
        return [
            'puntos'     => ['radial-gradient(' . self::rgba($ink, 0.08) . ' 1px, transparent 1px)', '5px 5px'],
            'rayas'      => ['repeating-linear-gradient(to bottom, transparent 0 25px, ' . self::rgba($acc2, 0.3) . ' 25px 26px)', 'auto'],
            'cuadricula' => [
                'linear-gradient(to right, ' . self::rgba($ink, 0.07) . ' 1px, transparent 1px), '
                . 'linear-gradient(to bottom, ' . self::rgba($ink, 0.07) . ' 1px, transparent 1px)',
                '18px 18px',
            ],
            'ninguna'    => ['none', 'auto'],
        ];
    }

    /** Normaliza un índice de paleta contra la whitelist. */
    public static function palette(int|string|null $index): int
    {
        $i = (int) $index;
        return isset(self::PALETTES[$i]) ? $i : self::DEFAULT_PALETTE;
    }

    /** Normaliza un nombre de estilo contra la whitelist. */
    public static function style(?string $name): string
    {
        return isset(self::STYLES[$name]) ? $name : self::DEFAULT_STYLE;
    }

    /** Normaliza un nombre de tipografía contra la whitelist. */
    public static function typography(?string $name): string
    {
        return isset(self::FONTS[$name]) ? $name : self::DEFAULT_TYPOGRAPHY;
    }

    /** Normaliza un tamaño de cupón contra la whitelist. */
    public static function size(?string $name): string
    {
        return isset(self::SIZES[$name]) ? $name : self::DEFAULT_SIZE;
    }

    /**
     * Calcula las variables CSS del tema. Puerto directo de renderVals() del
     * prototipo: los colores derivados usan exactamente las mismas mezclas.
     *
     * @return array<string,string> mapa de variable CSS a valor
     */
    public static function vars(int $palette, string $style, string $typography): array
    {
        [$pg, $ink, $acc, $acc2] = self::PALETTES[self::palette($palette)];
        $est = self::STYLES[self::style($style)];
        [$hand, $mono, $titleSize] = self::FONTS[self::typography($typography)];

        $tex = self::textures($ink, $acc2)[$est['tex']] ?? self::textures($ink, $acc2)['puntos'];

        // Fondo blanco para ahorrar tinta: la hoja y la tarjeta van en blanco
        // puro y --line se deriva del blanco, no del papel de la paleta.
        $white = ($est['paper'] ?? '') === 'white';
        $paper = $white ? '#FFFFFF' : $pg;

        return [
            '--pg'        => $paper,
            '--ink'       => $ink,
            '--acc'       => $acc,
            '--acc2'      => $acc2,
            '--card'      => $white ? '#FFFFFF' : self::mix($pg, '#FFFDF7', 0.55),
            '--line'      => self::mix($paper, $ink, 0.35),
            '--stub'      => self::rgba($acc, (float) $est['stub']),
            '--tape'      => self::rgba($acc, 0.28),
            '--tex'       => $tex[0],
            '--tex-size'  => $tex[1],
            '--hand'      => $hand,
            '--mono'      => $mono,
            '--tk-bw'     => $est['bw'],
            '--tk-bs'     => $est['bs'],
            '--tk-r'      => $est['r'],
            '--tk-title'  => $titleSize,
        ];
    }

    /** Las variables del tema serializadas para un atributo style="". */
    public static function styleAttr(int $palette, string $style, string $typography): string
    {
        $out = [];
        foreach (self::vars($palette, $style, $typography) as $key => $value) {
            $out[] = $key . ':' . $value;
        }
        return implode('; ', $out);
    }

    /**
     * Las tablas que necesita el panel de controles en JS para recalcular el
     * tema en el navegador sin recargar. Solo tokens de diseño: no se expone
     * nada de la base de datos.
     */
    public static function clientTables(): array
    {
        return [
            'palettes'   => self::PALETTES,
            'styles'     => self::STYLES,
            'fonts'      => self::FONTS,
            'sizes'      => self::SIZES,
            'defaults'   => [
                'palette'    => self::DEFAULT_PALETTE,
                'style'      => self::DEFAULT_STYLE,
                'typography' => self::DEFAULT_TYPOGRAPHY,
                'size'       => self::DEFAULT_SIZE,
            ],
        ];
    }

    /** Textos de portada por defecto, los del prototipo. */
    public const DEFAULT_COVER = [
        'eyebrow' => 'Hecho en casa · con mis manos',
        'title'   => 'Veinte cupones',
        'accent'  => 'para gastar conmigo',
        'note'    => 'Los corté, los escribí y los pegué yo. No hay dos iguales y alguno saldrá torcido, '
                   . 'pero todos se cumplen. Arranca el que quieras y déjalo en mi mesa, en mi bolsillo o en mi taza.',
    ];

    /** Los 20 cupones del prototipo: [título, descripción, tono, duración]. */
    public const DEFAULT_COUPONS = [
        ['Cena hecha por mí',      'Cocino, sirvo y lavo los platos. Tú pones la música y me dices que está bueno.', 'Romántico', '1 noche'],
        ['Masaje sin reloj',       'Veinte minutos mínimo. Si te duermes, lo doy por bien hecho.',                   'Romántico', '20 min'],
        ['Peli a tu antojo',       'La eliges tú y no me quejo ni una vez. Ni una.',                                 'Divertido', '2 horas'],
        ['Desayuno en la cama',    'A la hora que digas, aunque sea la una de la tarde.',                            'Romántico', '1 mañana'],
        ['Cita sorpresa',          'Lo organizo todo. Tú solo apareces y te dejas llevar.',                          'Romántico', '1 tarde'],
        ['Día sin pantallas',      'Seis horas en modo avión los dos, sin mirar de reojo.',                          'Cómplice',  '6 horas'],
        ['Un sí sin preguntas',    'Pides algo y no lo discuto. Válido una sola vez, úsalo bien.',                   'Cómplice',  '1 uso'],
        ['Paseo sin rumbo',        'Café en la mano y una calle que no conocemos todavía.',                          'Romántico', '1 tarde'],
        ['Baile en la cocina',     'Tres canciones seguidas, sin público y sin vergüenza.',                          'Divertido', '3 temas'],
        ['Siesta de las buenas',   'Cortinas cerradas, teléfonos lejos y nadie que nos despierte.',                  'Romántico', '90 min'],
        ['Chofer por un día',      'Te llevo y te traigo a donde quieras, sin poner cara.',                          'Cómplice',  '1 día'],
        ['La casa es mi problema', 'Esta semana me encargo yo de todo. Tú supervisa desde el sofá.',                 'Cómplice',  '1 semana'],
        ['Sesión de fotos',        'Diez minutos, veinte fotos y tú eliges la que se publica.',                      'Divertido', '10 min'],
        ['Cena fuera, yo invito',  'Eliges el sitio sin mirar los precios primero.',                                 'Romántico', '1 noche'],
        ['Plan con tus amigos',    'Voy, sonrío y me lo paso bien de verdad.',                                       'Divertido', '1 plan'],
        ['Noche de juegos',        'Cartas, mesa o consola. Quien pierda hace el postre.',                           'Divertido', '1 noche'],
        ['Beso largo, ahora',      'Canjeable en cualquier momento, incluso en mitad de la calle.',                  'Picante',   '1 uso'],
        ['Pelea cancelada',        'Perdono una discusión pendiente. Sin rencor y sin un te lo dije.',               'Cómplice',  '1 uso'],
        ['Ducha sin prisa',        'Agua caliente, la puerta cerrada y cero prisa por salir.',                       'Picante',   '1 uso'],
        ['Comodín, lo escribes tú','Escribe aquí con tu letra lo que quieras. Lo cumplo sin rechistar.',             'Picante',   '1 deseo'],
    ];
}
