<?php

declare(strict_types=1);

class CouponBook extends Model
{
    protected string $table = 'coupon_books';

    /**
     * Alfabeto sin caracteres ambiguos (nada de 0/O ni 1/I/L): estos códigos se
     * leen de un papel impreso y se teclean a mano cuando el QR no escanea.
     */
    public const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public static function randomCode(int $length): string
    {
        $max = strlen(self::ALPHABET) - 1;
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= self::ALPHABET[random_int(0, $max)];
        }
        return $out;
    }

    /** Slug único para el enlace fijo de la cuponera. */
    public function newSlug(): string
    {
        do {
            $slug = self::randomCode(12);
        } while ($this->queryOne('SELECT id FROM coupon_books WHERE slug = ?', [$slug]) !== null);
        return $slug;
    }

    public function findBySlug(string $slug): ?array
    {
        return $this->queryOne('SELECT * FROM coupon_books WHERE slug = ?', [$slug]);
    }

    /**
     * La cuponera solo si el usuario es el autor o el destinatario.
     *
     * Devuelve null (no un 403) cuando no le pertenece: el controlador responde
     * 404 para no filtrar que la cuponera existe.
     */
    public function findForUser(int $bookId, int $userId): ?array
    {
        return $this->queryOne(
            'SELECT * FROM coupon_books WHERE id = ? AND (owner_id = ? OR recipient_id = ?)',
            [$bookId, $userId, $userId]
        );
    }

    public function findBySlugForUser(string $slug, int $userId): ?array
    {
        return $this->queryOne(
            'SELECT b.*, o.name AS owner_name, r.name AS recipient_name
               FROM coupon_books b
               JOIN users o ON o.id = b.owner_id
               JOIN users r ON r.id = b.recipient_id
              WHERE b.slug = ? AND (b.owner_id = ? OR b.recipient_id = ?)',
            [$slug, $userId, $userId]
        );
    }

    /** Las que creó y las que le crearon, con el recuento de cupones usados. */
    public function forUser(int $userId): array
    {
        return $this->query(
            "SELECT b.*,
                    o.name AS owner_name,
                    r.name AS recipient_name,
                    (SELECT COUNT(*) FROM coupons c WHERE c.book_id = b.id) AS total,
                    (SELECT COUNT(*) FROM coupons c
                       WHERE c.book_id = b.id
                         AND COALESCE((SELECT s.status FROM coupon_status s
                                        WHERE s.coupon_id = c.id
                                        ORDER BY s.id DESC LIMIT 1), 'pending') = 'fulfilled'
                    ) AS fulfilled
               FROM coupon_books b
               JOIN users o ON o.id = b.owner_id
               JOIN users r ON r.id = b.recipient_id
              WHERE b.owner_id = ? OR b.recipient_id = ?
              ORDER BY b.updated_at DESC",
            [$userId, $userId]
        );
    }

    public function create(array $data): int
    {
        return $this->execute(
            'INSERT INTO coupon_books
                (slug, title, owner_id, recipient_id, style, palette, typography,
                 show_cover, show_tone_tag, use_qr, ticket_size,
                 cover_eyebrow, cover_title, cover_accent, cover_note, is_draft)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $data['slug'], $data['title'], $data['owner_id'], $data['recipient_id'],
                $data['style'], $data['palette'], $data['typography'],
                $data['show_cover'], $data['show_tone_tag'], $data['use_qr'], $data['ticket_size'],
                $data['cover_eyebrow'], $data['cover_title'], $data['cover_accent'],
                $data['cover_note'], $data['is_draft'],
            ]
        );
    }

    public function update(int $id, array $data): void
    {
        $this->execute(
            'UPDATE coupon_books SET
                title = ?, recipient_id = ?, style = ?, palette = ?, typography = ?,
                show_cover = ?, show_tone_tag = ?, use_qr = ?, ticket_size = ?,
                cover_eyebrow = ?, cover_title = ?, cover_accent = ?, cover_note = ?,
                is_draft = ?
             WHERE id = ?',
            [
                $data['title'], $data['recipient_id'], $data['style'], $data['palette'],
                $data['typography'], $data['show_cover'], $data['show_tone_tag'], $data['use_qr'],
                $data['ticket_size'], $data['cover_eyebrow'], $data['cover_title'], $data['cover_accent'],
                $data['cover_note'], $data['is_draft'], $id,
            ]
        );
    }
}
