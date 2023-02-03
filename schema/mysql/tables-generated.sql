-- This file is automatically generated using maintenance/generateSchemaSql.php.
-- Source: extensions/CheckUser/schema/tables.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
CREATE TABLE /*_*/cu_changes (
  cuc_id INT UNSIGNED AUTO_INCREMENT NOT NULL,
  cuc_namespace INT DEFAULT 0 NOT NULL,
  cuc_title VARBINARY(255) DEFAULT '' NOT NULL,
  cuc_actor BIGINT UNSIGNED DEFAULT 0 NOT NULL,
  cuc_actiontext VARBINARY(255) DEFAULT '' NOT NULL,
  cuc_comment VARBINARY(255) DEFAULT '' NOT NULL,
  cuc_comment_id BIGINT UNSIGNED DEFAULT 0 NOT NULL,
  cuc_minor TINYINT(1) DEFAULT 0 NOT NULL,
  cuc_page_id INT UNSIGNED DEFAULT 0 NOT NULL,
  cuc_this_oldid INT UNSIGNED DEFAULT 0 NOT NULL,
  cuc_last_oldid INT UNSIGNED DEFAULT 0 NOT NULL,
  cuc_type TINYINT(3) UNSIGNED DEFAULT 0 NOT NULL,
  cuc_timestamp BINARY(14) NOT NULL,
  cuc_ip VARCHAR(255) DEFAULT '',
  cuc_ip_hex VARCHAR(255) DEFAULT NULL,
  cuc_xff VARBINARY(255) DEFAULT '',
  cuc_xff_hex VARCHAR(255) DEFAULT NULL,
  cuc_agent VARBINARY(255) DEFAULT NULL,
  cuc_private MEDIUMBLOB DEFAULT NULL,
  INDEX cuc_ip_hex_time (cuc_ip_hex, cuc_timestamp),
  INDEX cuc_xff_hex_time (cuc_xff_hex, cuc_timestamp),
  INDEX cuc_timestamp (cuc_timestamp),
  INDEX cuc_actor_ip_time (cuc_actor, cuc_ip, cuc_timestamp),
  PRIMARY KEY(cuc_id)
) /*$wgDBTableOptions*/;


CREATE TABLE /*_*/cu_log_event (
  cule_id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
  cule_log_id INT UNSIGNED DEFAULT 0 NOT NULL,
  cule_actor BIGINT UNSIGNED NOT NULL,
  cule_timestamp BINARY(14) NOT NULL,
  cule_ip VARCHAR(255) DEFAULT '',
  cule_ip_hex VARCHAR(255) DEFAULT NULL,
  cule_xff VARBINARY(255) DEFAULT '',
  cule_xff_hex VARCHAR(255) DEFAULT NULL,
  cule_agent VARBINARY(255) DEFAULT NULL,
  INDEX cule_ip_hex_time (cule_ip_hex, cule_timestamp),
  INDEX cule_xff_hex_time (cule_xff_hex, cule_timestamp),
  INDEX cule_timestamp (cule_timestamp),
  INDEX cule_actor_ip_time (
    cule_actor, cule_ip, cule_timestamp
  ),
  PRIMARY KEY(cule_id)
) /*$wgDBTableOptions*/;


CREATE TABLE /*_*/cu_private_event (
  cupe_id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
  cupe_namespace INT DEFAULT 0 NOT NULL,
  cupe_title VARBINARY(255) DEFAULT '' NOT NULL,
  cupe_actor BIGINT UNSIGNED DEFAULT 0 NOT NULL,
  cupe_log_type VARBINARY(32) DEFAULT '' NOT NULL,
  cupe_log_action VARBINARY(32) DEFAULT '' NOT NULL,
  cupe_params BLOB NOT NULL,
  cupe_comment_id BIGINT UNSIGNED DEFAULT 0 NOT NULL,
  cupe_page INT UNSIGNED DEFAULT 0 NOT NULL,
  cupe_timestamp BINARY(14) NOT NULL,
  cupe_ip VARCHAR(255) DEFAULT '',
  cupe_ip_hex VARCHAR(255) DEFAULT NULL,
  cupe_xff VARBINARY(255) DEFAULT '',
  cupe_xff_hex VARCHAR(255) DEFAULT NULL,
  cupe_agent VARBINARY(255) DEFAULT NULL,
  cupe_private MEDIUMBLOB DEFAULT NULL,
  INDEX cupe_ip_hex_time (cupe_ip_hex, cupe_timestamp),
  INDEX cupe_xff_hex_time (cupe_xff_hex, cupe_timestamp),
  INDEX cupe_timestamp (cupe_timestamp),
  INDEX cupe_actor_ip_time (
    cupe_actor, cupe_ip, cupe_timestamp
  ),
  PRIMARY KEY(cupe_id)
) /*$wgDBTableOptions*/;


CREATE TABLE /*_*/cu_log (
  cul_id INT UNSIGNED AUTO_INCREMENT NOT NULL,
  cul_timestamp BINARY(14) NOT NULL,
  cul_actor BIGINT UNSIGNED NOT NULL,
  cul_reason_id BIGINT UNSIGNED NOT NULL,
  cul_reason_plaintext_id BIGINT UNSIGNED NOT NULL,
  cul_type VARBINARY(30) NOT NULL,
  cul_target_id INT UNSIGNED DEFAULT 0 NOT NULL,
  cul_target_text BLOB NOT NULL,
  cul_target_hex VARBINARY(255) DEFAULT '' NOT NULL,
  cul_range_start VARBINARY(255) DEFAULT '' NOT NULL,
  cul_range_end VARBINARY(255) DEFAULT '' NOT NULL,
  INDEX cul_actor_time (cul_actor, cul_timestamp),
  INDEX cul_type_target (
    cul_type, cul_target_id, cul_timestamp
  ),
  INDEX cul_target_hex (cul_target_hex, cul_timestamp),
  INDEX cul_range_start (cul_range_start, cul_timestamp),
  INDEX cul_timestamp (cul_timestamp),
  PRIMARY KEY(cul_id)
) /*$wgDBTableOptions*/;
