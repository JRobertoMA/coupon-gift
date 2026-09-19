<?php

declare(strict_types=1);

class User extends Model
{
    protected string $table = 'users';

    public function findByEmail(string $email): ?array
    {
        return $this->queryOne('SELECT * FROM users WHERE email = ?', [mb_strtolower(trim($email))]);
    }

    /** Todos menos el indicado: para elegir destinatario al crear una cuponera. */
    public function others(int $exceptId): array
    {
        return $this->query('SELECT id, name, email FROM users WHERE id <> ? ORDER BY name', [$exceptId]);
    }

    public function create(string $name, string $email, string $plainPassword): int
    {
        return $this->execute(
            'INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)',
            [trim($name), mb_strtolower(trim($email)), password_hash($plainPassword, PASSWORD_DEFAULT)]
        );
    }

    public function setTelegramChatId(int $userId, ?string $chatId): void
    {
        $this->execute('UPDATE users SET telegram_chat_id = ? WHERE id = ?', [$chatId ?: null, $userId]);
    }

    public function findByTelegramChatId(string $chatId): ?array
    {
        return $this->queryOne('SELECT * FROM users WHERE telegram_chat_id = ?', [$chatId]);
    }
}
