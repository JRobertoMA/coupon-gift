<?php

declare(strict_types=1);

class Coupon extends Model
{
    protected string $table = 'coupons';

    /**
     * El estado actual no está guardado: se deriva de la última fila del
     * historial. Esta subconsulta es la única definición de «estado actual» y
     * se reutiliza en todas las lecturas.
     */
    private const CURRENT_STATUS = "COALESCE((SELECT s.status FROM coupon_status s
                                                WHERE s.coupon_id = c.id
                                                ORDER BY s.id DESC LIMIT 1), 'pending')";

    /** Código único para el QR y para teclear a mano. */
    public function newCode(): string
    {
        do {
            $code = 'CP-' . CouponBook::randomCode(10);
        } while ($this->queryOne('SELECT id FROM coupons WHERE code = ?', [$code]) !== null);
        return $code;
    }

    /** Los cupones de una cuponera, en orden, con su estado actual. */
    public function forBook(int $bookId): array
    {
        return $this->query(
            'SELECT c.*, ' . self::CURRENT_STATUS . ' AS current_status
               FROM coupons c
              WHERE c.book_id = ?
              ORDER BY c.position ASC, c.id ASC',
            [$bookId]
        );
    }

    /** Un cupón por su código impreso, con la cuponera y las dos personas. */
    public function findByCode(string $code): ?array
    {
        return $this->queryOne(
            'SELECT c.*, ' . self::CURRENT_STATUS . ' AS current_status,
                    b.slug, b.title AS book_title, b.owner_id, b.recipient_id, b.is_draft,
                    o.name AS owner_name, o.email AS owner_email, o.telegram_chat_id AS owner_chat_id,
                    r.name AS recipient_name, r.email AS recipient_email, r.telegram_chat_id AS recipient_chat_id
               FROM coupons c
               JOIN coupon_books b ON b.id = c.book_id
               JOIN users o ON o.id = b.owner_id
               JOIN users r ON r.id = b.recipient_id
              WHERE c.code = ?',
            [$code]
        );
    }

    /** Lo mismo pero por id: lo que necesitan el webhook y el worker. */
    public function findFull(int $couponId): ?array
    {
        return $this->queryOne(
            'SELECT c.*, ' . self::CURRENT_STATUS . ' AS current_status,
                    b.slug, b.title AS book_title, b.owner_id, b.recipient_id, b.is_draft,
                    o.name AS owner_name, o.email AS owner_email, o.telegram_chat_id AS owner_chat_id,
                    r.name AS recipient_name, r.email AS recipient_email, r.telegram_chat_id AS recipient_chat_id
               FROM coupons c
               JOIN coupon_books b ON b.id = c.book_id
               JOIN users o ON o.id = b.owner_id
               JOIN users r ON r.id = b.recipient_id
              WHERE c.id = ?',
            [$couponId]
        );
    }

    public function create(int $bookId, int $position, array $data): int
    {
        return $this->execute(
            'INSERT INTO coupons (book_id, position, code, title, description, tone, duration)
             VALUES (?,?,?,?,?,?,?)',
            [$bookId, $position, $this->newCode(),
             $data['title'], $data['description'], $data['tone'], $data['duration']]
        );
    }

    public function update(int $id, array $data): void
    {
        $this->execute(
            'UPDATE coupons SET title = ?, description = ?, tone = ?, duration = ? WHERE id = ?',
            [$data['title'], $data['description'], $data['tone'], $data['duration'], $id]
        );
    }

    /** Renumera las posiciones de forma contigua tras añadir o borrar. */
    public function resequence(int $bookId): void
    {
        $rows = $this->query('SELECT id FROM coupons WHERE book_id = ? ORDER BY position ASC, id ASC', [$bookId]);
        $stmt = $this->db->prepare('UPDATE coupons SET position = ? WHERE id = ?');
        foreach ($rows as $i => $row) {
            $stmt->execute([$i + 1, $row['id']]);
        }
    }

    public function countForBook(int $bookId): int
    {
        $row = $this->queryOne('SELECT COUNT(*) AS n FROM coupons WHERE book_id = ?', [$bookId]);
        return (int) ($row['n'] ?? 0);
    }
}
