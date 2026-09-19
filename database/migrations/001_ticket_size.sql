-- Tamaño del cupón impreso: grande (diseño original, 3 por hoja vertical),
-- mediano (6 por hoja apaisada) o pequeño (10 por hoja vertical).
-- Las rejillas y medidas viven en config/design.php (Design::SIZES).

ALTER TABLE coupon_books
    ADD COLUMN ticket_size ENUM('large','medium','small') NOT NULL DEFAULT 'large' AFTER use_qr;
