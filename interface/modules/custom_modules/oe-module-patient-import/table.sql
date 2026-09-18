#IfNotTable module_patient_import_logs
CREATE TABLE IF NOT EXISTS `module_patient_import_logs`
(
    `id`             INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
    `filename`       VARCHAR(255) NOT NULL,
    `file_type`      VARCHAR(50)  DEFAULT 'csv',
    `total_rows`     INT          DEFAULT 0,
    `imported_count` INT          DEFAULT 0,
    `updated_count`  INT          DEFAULT 0,
    `skipped_count`  INT          DEFAULT 0,
    `error_count`    INT          DEFAULT 0,
    `duplicate_mode` VARCHAR(50)  DEFAULT 'skip',
    `details_json`   LONGTEXT     DEFAULT NULL,
    `created_by`     BIGINT(20)   DEFAULT NULL,
    `created_at`     DATETIME     DEFAULT CURRENT_TIMESTAMP,
    KEY `created_at_idx` (`created_at`)
) ENGINE=InnoDB COMMENT='Patient demographics import audit log';
#EndIf
