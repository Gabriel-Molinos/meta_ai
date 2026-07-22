CREATE TABLE IF NOT EXISTS wordpress_sites (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    label          VARCHAR(255) NOT NULL,
    url            VARCHAR(500) NOT NULL,
    wp_username    VARCHAR(255) NOT NULL,
    wp_app_password TEXT        NOT NULL,
    account_id     INT UNSIGNED NULL,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
