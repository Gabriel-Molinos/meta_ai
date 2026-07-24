CREATE TABLE IF NOT EXISTS user_accounts (
    user_id    INT UNSIGNED NOT NULL,
    account_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (user_id, account_id),
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
