<?php

declare(strict_types=1);

/**
 * Disparo del worker por HTTP: GET /cron/worker?token=CRON_SECRET
 *
 * Es la única forma de enviar avisos en hosting compartido, donde `exec()` no
 * lanza un PHP utilizable y cli/ está bloqueado por el .htaccess. Se programa
 * en el cron del panel cada 5 minutos.
 *
 * Sin sesión: el secreto ES la autorización, comparado con hash_equals().
 */
class WorkerController extends Controller
{
    public function run(): void
    {
        $secret = (string) env('CRON_SECRET', '');
        $given  = (string) ($_GET['token'] ?? $_SERVER['HTTP_X_CRON_TOKEN'] ?? '');

        // Sin secreto configurado se rechaza todo: mejor que dejarlo abierto.
        if ($secret === '' || !hash_equals($secret, $given)) {
            $this->jsonError('No autorizado.', 403);
        }

        $this->json(OutboxWorker::run());
    }
}
