CREATE TABLE IF NOT EXISTS users (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    email            VARCHAR(255) NOT NULL,
    session_token    VARCHAR(512),
    token_expires_at DATETIME,
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
