<?php

declare(strict_types=1);

/**
 * Rutas.
 *
 * El matching es secuencial con preg_match, así que las literales van SIEMPRE
 * antes que las parametrizadas ('/cuponeras/nueva' antes de '/cuponeras/(\d+)').
 *
 * URLs web en español; los identificadores de código, en inglés.
 *
 * @var Router $router
 */

// ── Acceso ───────────────────────────────────────────────────────────────────
$router->get('/entrar',  'AuthController', 'showLogin');
$router->post('/entrar', 'AuthController', 'login');
$router->get('/salir',   'AuthController', 'logout');

// ── Inicio ───────────────────────────────────────────────────────────────────
$router->get('/', 'BookController', 'index');

// ── Ajustes de la cuenta ─────────────────────────────────────────────────────
$router->get('/ajustes',  'SettingsController', 'show');
$router->post('/ajustes', 'SettingsController', 'update');

// ── Cuponeras ────────────────────────────────────────────────────────────────
$router->get('/cuponeras',        'BookController', 'index');
$router->get('/cuponeras/nueva',  'BookController', 'create');
$router->post('/cuponeras/nueva', 'BookController', 'store');
$router->get('/cuponeras/(\d+)/editar',  'BookController', 'edit');
$router->post('/cuponeras/(\d+)/editar', 'BookController', 'update');

// Cupones dentro de una cuponera (solo el autor).
$router->post('/cuponeras/(\d+)/cupones/nuevo',        'CouponController', 'store');
$router->post('/cuponeras/(\d+)/cupones/ejemplos',     'CouponController', 'seedDefaults');
$router->post('/cuponeras/(\d+)/cupones/(\d+)/editar', 'CouponController', 'update');
$router->post('/cuponeras/(\d+)/cupones/(\d+)/borrar', 'CouponController', 'destroy');

// ── El enlace fijo de la cuponera ────────────────────────────────────────────
// '/c/{slug}/imprimir' va antes que '/c/{slug}' para que no se lo coma.
$router->get('/c/([A-Z0-9]{6,32})/imprimir', 'PrintController', 'show');
$router->get('/c/([A-Z0-9]{6,32})',          'BookController',  'show');

// ── Canje ────────────────────────────────────────────────────────────────────
// A esto apunta el QR impreso.
$router->get('/cupon/(CP-[A-Z0-9]{4,20})',  'RedeemController', 'show');
$router->post('/cupon/(CP-[A-Z0-9]{4,20})', 'RedeemController', 'transition');

// Confirmación desde el correo: enlace firmado de un solo uso.
$router->get('/confirmar/([a-f0-9]{64})', 'RedeemController', 'confirmByToken');

// ── Cron por URL ─────────────────────────────────────────────────────────────
// Protegido por CRON_SECRET (?token= o cabecera X-Cron-Token), no por sesión.
$router->get('/cron/worker', 'WorkerController', 'run');

// ── Telegram ─────────────────────────────────────────────────────────────────
// Protegido por la cabecera X-Telegram-Bot-Api-Secret-Token, no por sesión.
$router->post('/telegram/webhook', 'TelegramWebhookController', 'handle');
