<?php

declare(strict_types=1);

/**
 * Vincular la cuenta de Telegram de un usuario (tabla `telegram_link_codes` + `users.telegram_chat_id`).
 *
 * - «Vincular Telegram» en /ajustes genera un código CG-XXXXXXXX de un solo uso, válido CODE_TTL segundos. Solo se
 *   guarda su sha256 y hay uno por usuario: generar otro anula el anterior.
 * - El usuario lo envía al bot (/vincular CG-…). El bot lo reenvía firmado a TelegramBotController con el from.id
 *   de quien lo escribió, y eso es lo que se guarda en telegram_chat_id: en un chat privado con el bot, el id de la
 *   persona es el id del chat, así que sirve para identificarla (botones) y para escribirle (avisos).
 * - Una cuenta de Telegram solo puede estar vinculada a un usuario (UNIQUE en telegram_chat_id).
 *
 * Mismo diseño que App\TelegramLinks de telegram-notification.
 */
class TelegramLink extends Model
{
    protected string $table = 'telegram_link_codes';

    public const CODE_PREFIX = 'CG';
    public const CODE_TTL    = 600;
    // Sin 0/O, 1/I/L: el código se teclea en el móvil
    private const ALPHABET    = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    private const CODE_LENGTH = 8;

    /** Código nuevo para el usuario (anula el que tuviera). @return array{code:string, expires_at:int} */
    public function createCode(int $userId): array
    {
        $code = self::CODE_PREFIX . '-';
        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }
        $expires = time() + self::CODE_TTL;
        $this->db->beginTransaction();
        try {
            $this->execute('DELETE FROM telegram_link_codes WHERE user_id = ? OR expires_at < ?', [$userId, time()]);
            $this->execute('INSERT INTO telegram_link_codes (code_hash, user_id, expires_at) VALUES (?, ?, ?)', [self::hash($code), $userId, $expires]);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        return ['code' => $code, 'expires_at' => $expires];
    }

    /**
     * Gasta el código y vincula esa cuenta de Telegram a su usuario (sustituye la que tuviera).
     *
     * @return array el usuario vinculado
     * @throws DomainException código no válido o caducado, o cuenta de Telegram vinculada a otro usuario
     */
    public function redeem(string $code, int $telegramUserId): array
    {
        $code = strtoupper(trim($code));
        if (!preg_match('/^' . self::CODE_PREFIX . '-[' . self::ALPHABET . ']{' . self::CODE_LENGTH . '}$/', $code)) {
            throw new DomainException('Ese código no es válido.');
        }
        $this->db->beginTransaction();
        try {
            $row = $this->queryOne('SELECT user_id, expires_at FROM telegram_link_codes WHERE code_hash = ? FOR UPDATE', [self::hash($code)]);
            if ($row === null || (int) $row['expires_at'] < time()) {
                throw new DomainException('Ese código no es válido o ya caducó.');
            }
            $user = $this->queryOne('SELECT * FROM users WHERE id = ?', [(int) $row['user_id']])
                ?? throw new DomainException('Ese código no es válido o ya caducó.');
            $other = $this->queryOne('SELECT id FROM users WHERE telegram_chat_id = ? AND id <> ?', [(string) $telegramUserId, $user['id']]);
            if ($other !== null) {
                throw new DomainException('Esta cuenta de Telegram ya está vinculada a otro usuario. Desvincúlala antes desde sus ajustes.');
            }
            $this->execute('DELETE FROM telegram_link_codes WHERE code_hash = ?', [self::hash($code)]);
            $this->execute('UPDATE users SET telegram_chat_id = ? WHERE id = ?', [(string) $telegramUserId, $user['id']]);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        $user['telegram_chat_id'] = (string) $telegramUserId;
        return $user;
    }

    /** Anula el código pendiente del usuario, si lo hay. */
    public function discardCode(int $userId): void
    {
        $this->execute('DELETE FROM telegram_link_codes WHERE user_id = ?', [$userId]);
    }

    private static function hash(string $code): string
    {
        return hash('sha256', $code);
    }
}
