<?php

declare(strict_types=1);

class BookController extends Controller
{
    /** Tope de cupones por cuponera. */
    private const MAX_COUPONS = 60;

    public function index(): void
    {
        Auth::require();
        $userId = (int) Auth::user();

        $this->render('cuponeras/index', [
            'title' => 'Mis cuponeras',
            'books' => (new CouponBook())->forUser($userId),
            'me'    => $userId,
        ]);
    }

    public function create(): void
    {
        Auth::require();
        $userId = (int) Auth::user();

        $this->render('cuponeras/form', [
            'title'  => 'Nueva cuponera',
            'book'   => null,
            'people' => (new User())->others($userId),
        ]);
    }

    public function store(): void
    {
        Auth::require();
        Csrf::require();

        $userId = (int) Auth::user();
        $data   = $this->validated($userId);

        if (is_string($data)) {
            $this->flash($data, 'danger');
            $this->redirect('/cuponeras/nueva');
        }

        $books = new CouponBook();

        // La cuponera nace VACÍA a propósito: los cupones los escribe el
        // usuario. Los 20 del diseño se pueden cargar después con un botón
        // (CouponController::seedDefaults) si quiere partir de ellos.
        try {
            $data['slug'] = $books->newSlug();
            $bookId = $books->create($data);
        } catch (Throwable $e) {
            error_log('[coupon-gift] No se pudo crear la cuponera: ' . $e->getMessage());
            $this->flash('No se pudo crear la cuponera.', 'danger');
            $this->redirect('/cuponeras/nueva');
        }

        $this->flash('Cuponera creada. Ahora añade los cupones.');
        $this->redirect('/cuponeras/' . $bookId . '/editar');
    }

    public function edit(string $id): void
    {
        Auth::require();
        $userId = (int) Auth::user();

        $books = new CouponBook();
        $book  = $books->find((int) $id);

        // Editar es solo del autor. Si no es suya, 404 y no 403: así no se
        // filtra que la cuponera exista.
        if ($book === null || (int) $book['owner_id'] !== $userId) {
            $this->notFound();
        }

        $this->render('cuponeras/form', [
            'title'   => 'Editar cuponera',
            'book'    => $book,
            'people'  => (new User())->others($userId),
            'coupons' => (new Coupon())->forBook((int) $book['id']),
        ]);
    }

    public function update(string $id): void
    {
        Auth::require();
        Csrf::require();

        $userId = (int) Auth::user();
        $books  = new CouponBook();
        $book   = $books->find((int) $id);

        if ($book === null || (int) $book['owner_id'] !== $userId) {
            $this->notFound();
        }

        $data = $this->validated($userId);
        if (is_string($data)) {
            $this->flash($data, 'danger');
            $this->redirect('/cuponeras/' . $id . '/editar');
        }

        $books->update((int) $book['id'], $data);
        $this->flash('Cambios guardados.');
        $this->redirect('/cuponeras/' . $id . '/editar');
    }

    /** El enlace fijo: la cuponera en web, con el estado de cada cupón. */
    public function show(string $slug): void
    {
        Auth::require();
        $userId = (int) Auth::user();

        $book = (new CouponBook())->findBySlugForUser($slug, $userId);
        if ($book === null) {
            $this->notFound();
        }

        $isOwner = (int) $book['owner_id'] === $userId;

        // El destinatario no ve los borradores.
        if ((int) $book['is_draft'] === 1 && !$isOwner) {
            $this->notFound();
        }

        $coupons = (new Coupon())->forBook((int) $book['id']);
        $role    = $isOwner ? 'owner' : 'recipient';

        $this->render('cuponeras/show', [
            'title'      => $book['title'],
            'book'       => $book,
            'coupons'    => $coupons,
            'isOwner'    => $isOwner,
            'role'       => $role,
            'themeStyle' => Design::styleAttr(
                (int) $book['palette'], $book['style'], $book['typography']
            ),
        ]);
    }

    /**
     * Valida y normaliza el formulario.
     *
     * @return array|string los datos limpios, o el mensaje de error
     */
    private function validated(int $userId): array|string
    {
        $title = trim((string) ($_POST['title'] ?? ''));
        if ($title === '') {
            return 'La cuponera necesita un título.';
        }

        $recipientId = (int) ($_POST['recipient_id'] ?? 0);
        if ($recipientId === 0 || $recipientId === $userId) {
            return 'Elige a quién va dirigida.';
        }
        if ((new User())->find($recipientId) === null) {
            return 'Esa persona no existe.';
        }

        return [
            'title'        => mb_substr($title, 0, 160),
            'owner_id'     => $userId,
            'recipient_id' => $recipientId,

            // Whitelist en servidor: lo que elige el cliente se valida contra
            // las tablas fijas de config/design.php, nunca se guarda tal cual.
            'style'        => Design::style($_POST['style'] ?? null),
            'palette'      => Design::palette($_POST['palette'] ?? null),
            'typography'   => Design::typography($_POST['typography'] ?? null),
            'ticket_size'  => Design::size($_POST['ticket_size'] ?? null),

            'show_cover'    => isset($_POST['show_cover'])    ? 1 : 0,
            'show_tone_tag' => isset($_POST['show_tone_tag']) ? 1 : 0,
            'use_qr'        => isset($_POST['use_qr'])        ? 1 : 0,
            'is_draft'      => isset($_POST['is_draft'])      ? 1 : 0,

            // Los textos de portada pueden quedar VACÍOS: en el formulario van
            // como placeholder y la portada omite lo que no se rellene.
            'cover_eyebrow' => mb_substr(trim((string) ($_POST['cover_eyebrow'] ?? '')), 0, 120),
            'cover_title'   => mb_substr(trim((string) ($_POST['cover_title'] ?? '')), 0, 120),
            'cover_accent'  => mb_substr(trim((string) ($_POST['cover_accent'] ?? '')), 0, 120),
            'cover_note'    => mb_substr(trim((string) ($_POST['cover_note'] ?? '')), 0, 600),
        ];
    }

    public static function maxCoupons(): int
    {
        return self::MAX_COUPONS;
    }
}
