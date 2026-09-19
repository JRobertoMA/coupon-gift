<?php

declare(strict_types=1);

/**
 * Alta, edición y borrado de cupones dentro de una cuponera.
 * Solo el autor de la cuponera puede tocarlos.
 */
class CouponController extends Controller
{
    /** Devuelve la cuponera si es del usuario; si no, corta con 404. */
    private function ownedBook(string $bookId): array
    {
        Auth::require();
        $book = (new CouponBook())->find((int) $bookId);

        if ($book === null || (int) $book['owner_id'] !== (int) Auth::user()) {
            $this->notFound();
        }
        return $book;
    }

    public function store(string $bookId): void
    {
        $book = $this->ownedBook($bookId);
        Csrf::require();

        $coupons = new Coupon();
        $count   = $coupons->countForBook((int) $book['id']);

        if ($count >= BookController::maxCoupons()) {
            $this->flash('Esta cuponera ya tiene el máximo de ' . BookController::maxCoupons() . ' cupones.', 'danger');
            $this->redirect('/cuponeras/' . $book['id'] . '/editar');
        }

        $data = $this->validated();
        if (is_string($data)) {
            $this->flash($data, 'danger');
            $this->redirect('/cuponeras/' . $book['id'] . '/editar');
        }

        $coupons->create((int) $book['id'], $count + 1, $data);

        $this->flash('Cupón añadido.');
        $this->redirect('/cuponeras/' . $book['id'] . '/editar');
    }

    /**
     * Carga los 20 cupones del diseño a continuación de los que ya haya.
     * Es opcional y a petición: la cuponera nace vacía.
     */
    public function seedDefaults(string $bookId): void
    {
        $book = $this->ownedBook($bookId);
        Csrf::require();

        $coupons = new Coupon();
        $count   = $coupons->countForBook((int) $book['id']);
        $room    = BookController::maxCoupons() - $count;

        if ($room <= 0) {
            $this->flash('Esta cuponera ya tiene el máximo de ' . BookController::maxCoupons() . ' cupones.', 'danger');
            $this->redirect('/cuponeras/' . $book['id'] . '/editar');
        }

        $db = $coupons->pdo();
        $db->beginTransaction();
        try {
            $added = 0;
            foreach (array_slice(Design::DEFAULT_COUPONS, 0, $room) as [$title, $description, $tone, $duration]) {
                $coupons->create((int) $book['id'], $count + $added + 1, [
                    'title'       => $title,
                    'description' => $description,
                    'tone'        => $tone,
                    'duration'    => $duration,
                ]);
                $added++;
            }
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            error_log('[coupon-gift] No se pudieron cargar los ejemplos: ' . $e->getMessage());
            $this->flash('No se pudieron cargar los cupones de ejemplo.', 'danger');
            $this->redirect('/cuponeras/' . $book['id'] . '/editar');
        }

        $this->flash($added === count(Design::DEFAULT_COUPONS)
            ? 'Cargados los 20 cupones de ejemplo. Edítalos a tu gusto.'
            : "Cargados $added cupones de ejemplo (tope de " . BookController::maxCoupons() . ').');
        $this->redirect('/cuponeras/' . $book['id'] . '/editar');
    }

    public function update(string $bookId, string $couponId): void
    {
        $book = $this->ownedBook($bookId);
        Csrf::require();

        $coupons = new Coupon();
        $coupon  = $coupons->find((int) $couponId);

        if ($coupon === null || (int) $coupon['book_id'] !== (int) $book['id']) {
            $this->notFound();
        }

        $data = $this->validated();
        if (is_string($data)) {
            $this->flash($data, 'danger');
            $this->redirect('/cuponeras/' . $book['id'] . '/editar');
        }

        $coupons->update((int) $coupon['id'], $data);

        $this->flash('Cupón actualizado.');
        $this->redirect('/cuponeras/' . $book['id'] . '/editar');
    }

    public function destroy(string $bookId, string $couponId): void
    {
        $book = $this->ownedBook($bookId);
        Csrf::require();

        $coupons = new Coupon();
        $coupon  = $coupons->find((int) $couponId);

        if ($coupon === null || (int) $coupon['book_id'] !== (int) $book['id']) {
            $this->notFound();
        }

        // El historial de estados se va con el cupón por la FK en cascada:
        // un cupón borrado no deja un rastro huérfano que nadie puede leer.
        $coupons->delete((int) $coupon['id']);
        $coupons->resequence((int) $book['id']);

        $this->flash('Cupón borrado.');
        $this->redirect('/cuponeras/' . $book['id'] . '/editar');
    }

    /** @return array|string los datos limpios, o el mensaje de error */
    private function validated(): array|string
    {
        $title = trim((string) ($_POST['title'] ?? ''));
        if ($title === '') {
            return 'El cupón necesita un título.';
        }

        return [
            'title'       => mb_substr($title, 0, 120),
            'description' => mb_substr(trim((string) ($_POST['description'] ?? '')), 0, 400),
            'tone'        => mb_substr(trim((string) ($_POST['tone'] ?? '')), 0, 40),
            'duration'    => mb_substr(trim((string) ($_POST['duration'] ?? '')), 0, 40),
        ];
    }
}
