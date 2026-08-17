#IfNotTable practice_employers
CREATE TABLE IF NOT EXISTS `practice_employers`
(
    `id`            INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
    `name`          VARCHAR(255) NOT NULL,
    `phone`         VARCHAR(50)  DEFAULT NULL,
    `street`        VARCHAR(255) DEFAULT NULL,
    `street_line_2` VARCHAR(255) DEFAULT NULL,
    `city`          VARCHAR(255) DEFAULT NULL,
    `state`         VARCHAR(50)  DEFAULT NULL,
    `postal_code`   VARCHAR(20)  DEFAULT NULL,
    `country`       VARCHAR(100) DEFAULT NULL,
    `created_at`    DATETIME     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB COMMENT='Practice-level employer directory';
#EndIf