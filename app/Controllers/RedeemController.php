<?php

declare(strict_types=1);

/**
 * Canje de cupones: la página a la que lleva el QR y la confirmación por correo.
 */
class RedeemController extends Controller
{
    /** Página del cupón: a esto apunta el QR impreso. */
    public function show(string $code): void
    {
        Auth::require();
        $userId = (int) Auth::user();

        $coupon = (new Coupon())->findByCode($code);
        if ($coupon === null) {
            $this->notFound();
        }

        $role = StatusMachine::roleOf($coupon, $userId);
        if ($role === null) {
            // El cupón existe pero no es de esta persona: 404, no 403.
            $this->notFound();
        }

        $this->render('canje/show', [
            'title'      => $coupon['title'],
            'coupon'     => $coupon,
            'role'       => $role,
            'available'  => StatusMachine::availableFor($coupon['current_status'], $role),
            'history'    => (new CouponStatus())->history((int) $coupon['id']),
        ]);
    }

    /** Aplica una transición pedida desde la web o desde la página del QR. */
    public function transition(string $code): void
    {
        Auth::require();
        Csrf::require();

        $userId = (int) Auth::user();
        $coupon = (new Coupon())->findByCode($code);

        if ($coupon === null) {
            $this->notFound();
        }

        $role = StatusMachine::roleOf($coupon, $userId);
        if ($role === null) {
            $this->notFound();
        }

        $to      = (string) ($_POST['to'] ?? '');
        $channel = ($_POST['channel'] ?? '') === 'qr' ? 'qr' : 'web';

        try {
            StatusMachine::apply($coupon, $to, $userId, $role, $channel);
        } catch (DomainException $e) {
            // Transición fuera de la whitelist. Se responde con redirección y
            // mensaje flash (patrón POST-Redirect-Get), no con un 422: esto
            // viene de un formulario, y un 422 dejaría al navegador mostrando
            // el POST y repitiéndolo al recargar. La petición NO cambia nada:
            // StatusMachine lanza antes de escribir en el historial.
            $this->flash($e->getMessage(), 'danger');
            $this->redirect('/cupon/' . $coupon['code']);
        }

        $this->notifyAfter($coupon, $to);

        $this->flash(match ($to) {
            StatusMachine::REQUESTED => 'Cupón pedido. Ya le ha llegado el aviso.',
            StatusMachine::FULFILLED => '¡Cupón cumplido!',
            StatusMachine::DECLINED  => 'Cupón rechazado.',
            StatusMachine::CANCELLED => 'Petición cancelada.',
            default                  => 'Cupón actualizado.',
        });
        $this->redirect('/cupon/' . $coupon['code']);
    }

    /**
     * Confirmación desde el correo.
     *
     * Sin sesión: el enlace firmado ES la autorización. Por eso es de un solo
     * uso, caduca, y se invalida en cuanto el canje se resuelve por otra vía.
     */
    public function confirmByToken(string $token): void
    {
        $tokens = new ActionToken();
        $row    = $tokens->findUsable($token);

        if ($row === null) {
            $this->render('canje/token_invalido', [
                'title' => 'Enlace no válido',
            ], 'auth');
            return;
        }

        $coupon = (new Coupon())->findFull((int) $row['coupon_id']);
        if ($coupon === null) {
            $this->notFound();
        }

        $to = $row['action'] === 'fulfill' ? StatusMachine::FULFILLED : StatusMachine::DECLINED;

        try {
            // El enlace se emitió para quien cumple el cupón, así que se actúa
            // con ese papel aunque no haya sesión abierta.
            StatusMachine::apply($coupon, $to, (int) $coupon['owner_id'], 'owner', 'email');
        } catch (DomainException $e) {
            $tokens->markUsed((int) $row['id']);
            $this->render('canje/token_invalido', [
                'title'  => 'Ya no aplica',
                'reason' => $e->getMessage(),
            ], 'auth');
            return;
        }

        $tokens->markUsed((int) $row['id']);
        $this->notifyAfter($coupon, $to);

        $this->render('canje/token_ok', [
            'title'  => 'Listo',
            'coupon' => $coupon,
            'status' => $to,
        ], 'auth');
    }

    /**
     * Encola los avisos que toquen tras un cambio de estado y despierta al
     * worker. El envío nunca ocurre dentro de esta petición.
     */
    private function notifyAfter(array $coupon, string $to): void
    {
        if ($to === StatusMachine::REQUESTED) {
            Notifier::couponRequested($coupon);
        } elseif (in_array($to, [StatusMachine::FULFILLED, StatusMachine::DECLINED], true)) {
            Notifier::couponResolved($coupon, $to);
        } else {
            return;
        }

        Notifier::kickWorker();
    }
}
