CREATE TABLE IF NOT EXISTS executions (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_key      VARCHAR(191) NOT NULL,
    start_date       DATE         NOT NULL,
    end_date         DATE         NOT NULL,
    status           VARCHAR(50)  NOT NULL DEFAULT 'running',
    campaigns_count  INT          NOT NULL DEFAULT 0,
    error_message    TEXT,
    completed_at     DATETIME,
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
