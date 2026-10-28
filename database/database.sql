-- Esquema completo de coupon-gift.
-- Ejecutar con:
--   docker exec -i mariadb mysql -u<USER> -p<PASSWORD> < database/database.sql
-- (las credenciales están en .env, DB_USER y DB_PASS)

CREATE DATABASE IF NOT EXISTS coupon_gift
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE coupon_gift;

-- ── Usuarios ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    name             VARCHAR(80)   NOT NULL,
    email            VARCHAR(190)  NOT NULL,
    password_hash    VARCHAR(255)  NOT NULL,
    -- Cuenta de Telegram vinculada (from.id, que en el chat privado con el bot es también el chat id).
    -- Se rellena con /vincular CG-… (TelegramLink). NULL = solo recibe correo.
    telegram_chat_id VARCHAR(32)   DEFAULT NULL,
    created_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    UNIQUE KEY uq_users_telegram (telegram_chat_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Códigos para vincular Telegram (/vincular CG-XXXXXXXX): un solo uso, 10 min, solo el sha256; uno por usuario
CREATE TABLE IF NOT EXISTS telegram_link_codes (
    code_hash  CHAR(64)     NOT NULL,
    user_id    INT UNSIGNED NOT NULL,
    expires_at INT UNSIGNED NOT NULL,
    PRIMARY KEY (code_hash),
    UNIQUE KEY uq_tgcodes_user (user_id),
    CONSTRAINT fk_tgcodes_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Cuponeras ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS coupon_books (
    id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    -- El enlace fijo que se comparte: /c/{slug}. Aleatorio, no adivinable.
    slug          VARCHAR(32)   NOT NULL,
    title         VARCHAR(160)  NOT NULL,
    owner_id      INT UNSIGNED  NOT NULL,  -- quien la regala y cumple los cupones
    recipient_id  INT UNSIGNED  NOT NULL,  -- quien la recibe y los canjea

    -- Ajustes de diseño. Se validan contra las whitelists de config/design.php
    -- antes de guardar; la BD solo los almacena.
    style         VARCHAR(40)   NOT NULL DEFAULT 'Recortado a mano',
    palette       TINYINT UNSIGNED NOT NULL DEFAULT 0,
    typography    VARCHAR(60)   NOT NULL DEFAULT 'Caveat + Courier Prime',
    show_cover    TINYINT(1)    NOT NULL DEFAULT 1,
    show_tone_tag TINYINT(1)    NOT NULL DEFAULT 1,
    -- Con use_qr el lateral del ticket lleva QR + código en vez del sello «vale ya».
    use_qr        TINYINT(1)    NOT NULL DEFAULT 1,
    -- Tamaño impreso. Rejilla y medidas en config/design.php (Design::SIZES).
    ticket_size   ENUM('large','medium','small') NOT NULL DEFAULT 'large',

    cover_eyebrow VARCHAR(120)  NOT NULL DEFAULT '',
    cover_title   VARCHAR(120)  NOT NULL DEFAULT '',
    cover_accent  VARCHAR(120)  NOT NULL DEFAULT '',
    cover_note    TEXT          NOT NULL,

    -- Los borradores no se enseñan al destinatario.
    is_draft      TINYINT(1)    NOT NULL DEFAULT 1,
    created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_books_slug (slug),
    KEY idx_books_owner (owner_id),
    KEY idx_books_recipient (recipient_id),
    CONSTRAINT fk_books_owner     FOREIGN KEY (owner_id)     REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_books_recipient FOREIGN KEY (recipient_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Cupones ──────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS coupons (
    id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    book_id     INT UNSIGNED  NOT NULL,
    position    SMALLINT UNSIGNED NOT NULL,
    -- Código impreso y codificado en el QR: CP-XXXXXXXXXX. Largo para no ser
    -- adivinable, pero tecleable a mano si el QR no escanea.
    code        VARCHAR(16)   NOT NULL,
    title       VARCHAR(120)  NOT NULL,
    description VARCHAR(400)  NOT NULL,
    tone        VARCHAR(40)   NOT NULL DEFAULT '',
    duration    VARCHAR(40)   NOT NULL DEFAULT '',
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_coupons_code (code),
    KEY idx_coupons_book_position (book_id, position),
    CONSTRAINT fk_coupons_book FOREIGN KEY (book_id) REFERENCES coupon_books (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Historial de estados (append-only) ───────────────────────────────────────
-- El estado actual NO se guarda en coupons: se deriva de la última fila de
-- aquí. Así queda el rastro completo de quién hizo qué y por qué canal.
CREATE TABLE IF NOT EXISTS coupon_status (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    coupon_id  INT UNSIGNED NOT NULL,
    status     ENUM('pending','requested','fulfilled','declined','cancelled') NOT NULL,
    actor_id   INT UNSIGNED DEFAULT NULL,
    channel    ENUM('web','qr','telegram','email','system') NOT NULL DEFAULT 'web',
    note       VARCHAR(255) NOT NULL DEFAULT '',
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_status_coupon (coupon_id, id),
    CONSTRAINT fk_status_coupon FOREIGN KEY (coupon_id) REFERENCES coupons (id) ON DELETE CASCADE,
    CONSTRAINT fk_status_actor  FOREIGN KEY (actor_id)  REFERENCES users (id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Enlaces de un solo uso para confirmar desde el correo ────────────────────
-- Un correo no puede llevar botones que hagan POST, así que lleva dos enlaces
-- firmados: uno para cumplir y otro para rechazar.
CREATE TABLE IF NOT EXISTS action_tokens (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    coupon_id  INT UNSIGNED NOT NULL,
    token      CHAR(64)     NOT NULL,
    action     ENUM('fulfill','decline') NOT NULL,
    expires_at DATETIME     NOT NULL,
    used_at    DATETIME     DEFAULT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tokens_token (token),
    KEY idx_tokens_coupon (coupon_id),
    CONSTRAINT fk_tokens_coupon FOREIGN KEY (coupon_id) REFERENCES coupons (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Bandeja de salida de notificaciones ──────────────────────────────────────
-- El canje NO envía dentro de la petición HTTP: con mpm_prefork y 768 MB
-- compartidos, un SMTP lento bloquea un worker de Apache. Aquí se encola y
-- cli/worker.php lo envía con reintentos.
CREATE TABLE IF NOT EXISTS notifications (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    coupon_id  INT UNSIGNED NOT NULL,
    channel    ENUM('telegram','email') NOT NULL,
    to_user_id INT UNSIGNED NOT NULL,
    payload    TEXT         NOT NULL,
    status     ENUM('queued','sent','failed') NOT NULL DEFAULT 'queued',
    attempts   TINYINT UNSIGNED NOT NULL DEFAULT 0,
    last_error VARCHAR(500) NOT NULL DEFAULT '',
    sent_at    DATETIME     DEFAULT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notifications_pending (status, attempts, id),
    CONSTRAINT fk_notifications_coupon FOREIGN KEY (coupon_id) REFERENCES coupons (id) ON DELETE CASCADE,
    CONSTRAINT fk_notifications_user   FOREIGN KEY (to_user_id) REFERENCES users (id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Intentos de acceso fallidos ──────────────────────────────────────────────
-- Solo se registran los FALLIDOS; un acceso correcto limpia las filas.
CREATE TABLE IF NOT EXISTS login_attempts (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    email      VARCHAR(190) NOT NULL,
    ip         VARCHAR(45)  NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_attempts_lookup (email, ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
