#IfNotTable module_group_canvas_config
CREATE TABLE IF NOT EXISTS `module_group_canvas_config` (
    `id` INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
    `form_id` VARCHAR(31) NOT NULL,
    `group_id` VARCHAR(31) NOT NULL,
    `is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
    `button_label` VARCHAR(63) NOT NULL DEFAULT 'Annotate Diagram',
    `background_image` VARCHAR(255) DEFAULT '',
    `canvas_width` INT NOT NULL DEFAULT 800,
    `canvas_height` INT NOT NULL DEFAULT 600,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `form_group_unique` (`form_id`, `group_id`)
) ENGINE=InnoDB COMMENT='Configuration for Group Header Canvas annotations';
#EndIf

#IfNotTable module_group_canvas_data
CREATE TABLE IF NOT EXISTS `module_group_canvas_data` (
    `id` BIGINT NOT NULL PRIMARY KEY AUTO_INCREMENT,
    `pid` BIGINT NOT NULL,
    `encounter` BIGINT NOT NULL DEFAULT 0,
    `form_id` VARCHAR(31) NOT NULL,
    `form_instance_id` BIGINT NOT NULL DEFAULT 0,
    `group_id` VARCHAR(31) NOT NULL,
    `drawing_data` LONGTEXT DEFAULT NULL COMMENT 'JSON vector drawing strokes',
    `drawing_png` LONGTEXT DEFAULT NULL COMMENT 'Base64 data URL for display and print',
    `created_by` BIGINT DEFAULT NULL,
    `updated_by` BIGINT DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_pid_enc_form_grp` (`pid`, `encounter`, `form_id`, `group_id`)
) ENGINE=InnoDB COMMENT='Patient drawing annotations per encounter and group header';
#EndIf
