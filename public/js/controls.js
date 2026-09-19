/* ============================================================================
   Panel de personalización de la vista de impresión.

   No vuelve a renderizar nada: todo el tema son variables CSS sobre
   #theme-root, así que cambiar paleta, estilo o tipografía es reescribir esas
   variables. Es el mismo mecanismo que usaba el prototipo de Claude Design.

   Las tablas vienen de config/design.php (window.DESIGN_TABLES), así que PHP y
   JS no pueden desincronizarse.
   ========================================================================= */
(function () {
    'use strict';

    var tables = window.DESIGN_TABLES;
    var root   = document.getElementById('theme-root');
    if (!tables || !root) return;

    // ── Puerto de mezcla() y rgba() del prototipo ────────────────────────────

    function channels(hex) {
        return [
            parseInt(hex.slice(1, 3), 16),
            parseInt(hex.slice(3, 5), 16),
            parseInt(hex.slice(5, 7), 16)
        ];
    }

    /** Interpolación lineal por canal, idéntica a Design::mix() en PHP. */
    function mix(hex, towards, t) {
        var a = channels(hex), b = channels(towards);
        return '#' + a.map(function (v, i) {
            return Math.round(v + (b[i] - v) * t).toString(16).padStart(2, '0');
        }).join('');
    }

    function rgba(hex, alpha) {
        var c = channels(hex);
        return 'rgba(' + c[0] + ',' + c[1] + ',' + c[2] + ',' + alpha + ')';
    }

    function textures(ink, acc2) {
        return {
            puntos: ['radial-gradient(' + rgba(ink, 0.08) + ' 1px, transparent 1px)', '5px 5px'],
            rayas: ['repeating-linear-gradient(to bottom, transparent 0 25px, ' + rgba(acc2, 0.3) + ' 25px 26px)', 'auto'],
            cuadricula: [
                'linear-gradient(to right, ' + rgba(ink, 0.07) + ' 1px, transparent 1px), ' +
                'linear-gradient(to bottom, ' + rgba(ink, 0.07) + ' 1px, transparent 1px)',
                '18px 18px'
            ],
            ninguna: ['none', 'auto']
        };
    }

    // ── Estado efectivo ──────────────────────────────────────────────────────

    var state = {
        palette:    tables.defaults.palette,
        style:      tables.defaults.style,
        typography: tables.defaults.typography
    };

    var styleSelect = document.getElementById('pt-style');
    var typeSelect  = document.getElementById('pt-typography');
    var activeChip  = document.querySelector('.pt-swatch.is-active');

    if (styleSelect) state.style      = styleSelect.value;
    if (typeSelect)  state.typography = typeSelect.value;
    if (activeChip)  state.palette    = parseInt(activeChip.dataset.palette, 10);

    /** Reescribe las variables CSS. Mismo cálculo que Design::vars() en PHP. */
    function applyTheme() {
        var palette = tables.palettes[state.palette] || tables.palettes[0];
        var pg = palette[0], ink = palette[1], acc = palette[2], acc2 = palette[3];

        var est  = tables.styles[state.style] || tables.styles[tables.defaults.style];
        var font = tables.fonts[state.typography] || tables.fonts[tables.defaults.typography];
        var tex  = textures(ink, acc2)[est.tex] || textures(ink, acc2).puntos;

        // Fondo blanco para ahorrar tinta (estilo «Cartulina con cinta»):
        // mismo cálculo que Design::vars() en PHP.
        var white = est.paper === 'white';
        var paper = white ? '#FFFFFF' : pg;

        var vars = {
            '--pg': paper,
            '--ink': ink,
            '--acc': acc,
            '--acc2': acc2,
            '--card': white ? '#FFFFFF' : mix(pg, '#FFFDF7', 0.55),
            '--line': mix(paper, ink, 0.35),
            '--stub': rgba(acc, est.stub),
            '--tape': rgba(acc, 0.28),
            '--tex': tex[0],
            '--tex-size': tex[1],
            '--hand': font[0],
            '--mono': font[1],
            '--tk-bw': est.bw,
            '--tk-bs': est.bs,
            '--tk-r': est.r,
            '--tk-title': font[2]
        };

        Object.keys(vars).forEach(function (key) {
            root.style.setProperty(key, vars[key]);
        });
    }

    /**
     * Refleja la selección en la URL sin recargar.
     *
     * Así un enlace comparte una combinación concreta. NO se guarda en la
     * cuponera: para eso está el botón «Guardar combinación».
     */
    function syncUrl() {
        var params = new URLSearchParams(window.location.search);
        params.set('estilo', state.style);
        params.set('paleta', String(state.palette));
        params.set('tipografia', state.typography);
        var sizeSelect = document.getElementById('pt-size');
        if (sizeSelect) params.set('tamano', sizeSelect.value);
        params.set('portada', document.getElementById('pt-cover').checked ? '1' : '0');
        params.set('tonos', document.getElementById('pt-tone').checked ? '1' : '0');

        history.replaceState(null, '', window.location.pathname + '?' + params.toString());

        // El botón de guardar arrastra la combinación al formulario de edición.
        var save = document.getElementById('pt-save');
        if (save) {
            var base = save.href.split('?')[0];
            save.href = base + '?' + params.toString();
        }
    }

    // ── Enlaces de la interfaz ───────────────────────────────────────────────

    if (styleSelect) {
        styleSelect.addEventListener('change', function () {
            state.style = this.value;
            applyTheme();
            syncUrl();
        });
    }

    if (typeSelect) {
        typeSelect.addEventListener('change', function () {
            state.typography = this.value;
            applyTheme();
            syncUrl();
        });
    }

    document.querySelectorAll('.pt-swatch').forEach(function (chip) {
        chip.addEventListener('click', function () {
            document.querySelectorAll('.pt-swatch').forEach(function (c) {
                c.classList.remove('is-active');
            });
            this.classList.add('is-active');
            state.palette = parseInt(this.dataset.palette, 10);
            applyTheme();
            syncUrl();
        });
    });

    // El tamaño no es una variable CSS: cambia la paginación y la orientación,
    // que decide PHP. Se sincroniza la URL (para conservar paleta, estilo, etc.)
    // y se recarga.
    var sizeSelect = document.getElementById('pt-size');
    if (sizeSelect) {
        sizeSelect.addEventListener('change', function () {
            syncUrl();
            window.location.reload();
        });
    }

    var coverToggle = document.getElementById('pt-cover');
    if (coverToggle) {
        coverToggle.addEventListener('change', function () {
            var cover = document.querySelector('.sheet--cover');
            if (cover) cover.classList.toggle('is-hidden', !this.checked);
            syncUrl();
        });
    }

    var toneToggle = document.getElementById('pt-tone');
    if (toneToggle) {
        toneToggle.addEventListener('change', function () {
            var checked = this.checked;
            document.querySelectorAll('.ticket-meta').forEach(function (meta) {
                meta.classList.toggle('is-hidden', !checked);
            });
            syncUrl();
        });
    }

    var printButton = document.getElementById('pt-print');
    if (printButton) {
        printButton.addEventListener('click', function () {
            window.print();
        });
    }

    /**
     * Escala la hoja en pantallas estrechas.
     *
     * Solo afecta a la vista: al imprimir, @media print anula el transform y la
     * hoja vuelve a su tamaño físico real.
     */
    function fitToScreen() {
        var sheets = document.querySelector('.sheets');
        if (!sheets) return;

        // 8.5in a 96dpi = 816px (1056px en apaisado), más un respiro a los lados.
        var sheetWidth = document.body.classList.contains('sheet--landscape') ? 1056 : 816;
        var available = document.documentElement.clientWidth - 32;
        var scale = Math.min(1, available / sheetWidth);
        sheets.style.setProperty('--fit', String(scale));
    }

    window.addEventListener('resize', fitToScreen);

    applyTheme();
    fitToScreen();
})();
