CREATE TABLE IF NOT EXISTS campaign_reports (
    id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    execution_id    INT UNSIGNED  NOT NULL,
    report_date     DATE          NOT NULL,
    campaign_id     VARCHAR(191)  NOT NULL,
    account_key     VARCHAR(191)  NOT NULL,
    domain          VARCHAR(255)  NOT NULL DEFAULT '',
    campaign_name   VARCHAR(512)  NOT NULL,
    status          VARCHAR(50)   NOT NULL DEFAULT '',
    impressions     INT           NOT NULL DEFAULT 0,
    clicks          INT           NOT NULL DEFAULT 0,
    reach           INT           NOT NULL DEFAULT 0,
    spend_usd       DECIMAL(15,4) NOT NULL DEFAULT 0,
    ctr             DECIMAL(10,6) NOT NULL DEFAULT 0,
    cpc_usd         DECIMAL(15,4) NOT NULL DEFAULT 0,
    cpm_usd         DECIMAL(15,4) NOT NULL DEFAULT 0,
    roas            DECIMAL(10,4) NOT NULL DEFAULT 0,
    av_revenue_usd  DECIMAL(15,4) NOT NULL DEFAULT 0,
    av_impressions  INT           NOT NULL DEFAULT 0,
    av_sessions     INT           NOT NULL DEFAULT 0,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_campaign_date (campaign_id, account_key, report_date),
    FOREIGN KEY (execution_id) REFERENCES executions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_cr_account  ON campaign_reports (account_key);
CREATE INDEX idx_cr_date     ON campaign_reports (report_date);
CREATE INDEX idx_cr_campaign ON campaign_reports (campaign_id);
