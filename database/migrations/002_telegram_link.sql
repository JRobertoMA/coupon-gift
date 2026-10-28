-- Telegram a través del backend compartido del bot: vincular la cuenta con un código (/vincular CG-XXXXXXXX).
-- users.telegram_chat_id pasa a ser el id de la persona en Telegram (from.id), que en el chat privado con el bot
-- es también el id del chat. Una cuenta de Telegram, un usuario.
--
-- Antes de aplicarla, comprueba que no haya chat ids repetidos (debe devolver 0 filas):
--   SELECT telegram_chat_id, COUNT(*) FROM users WHERE telegram_chat_id IS NOT NULL GROUP BY 1 HAVING COUNT(*) > 1;

ALTER TABLE users
    ADD UNIQUE KEY uq_users_telegram (telegram_chat_id);

-- Códigos de un solo uso, 10 min, solo el sha256; uno por usuario
CREATE TABLE IF NOT EXISTS telegram_link_codes (
    code_hash  CHAR(64)     NOT NULL,
    user_id    INT UNSIGNED NOT NULL,
    expires_at INT UNSIGNED NOT NULL,
    PRIMARY KEY (code_hash),
    UNIQUE KEY uq_tgcodes_user (user_id),
    CONSTRAINT fk_tgcodes_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
