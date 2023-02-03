-- This file is automatically generated using maintenance/generateSchemaSql.php.
-- Source: extensions/CheckUser/schema/tables.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
CREATE TABLE cu_changes (
  cuc_id SERIAL NOT NULL,
  cuc_namespace INT DEFAULT 0 NOT NULL,
  cuc_title TEXT DEFAULT '' NOT NULL,
  cuc_actor BIGINT DEFAULT 0 NOT NULL,
  cuc_actiontext TEXT DEFAULT '' NOT NULL,
  cuc_comment TEXT DEFAULT '' NOT NULL,
  cuc_comment_id BIGINT DEFAULT 0 NOT NULL,
  cuc_minor SMALLINT DEFAULT 0 NOT NULL,
  cuc_page_id INT DEFAULT 0 NOT NULL,
  cuc_this_oldid INT DEFAULT 0 NOT NULL,
  cuc_last_oldid INT DEFAULT 0 NOT NULL,
  cuc_type SMALLINT DEFAULT 0 NOT NULL,
  cuc_timestamp TIMESTAMPTZ NOT NULL,
  cuc_ip VARCHAR(255) DEFAULT '',
  cuc_ip_hex VARCHAR(255) DEFAULT NULL,
  cuc_xff TEXT DEFAULT '',
  cuc_xff_hex VARCHAR(255) DEFAULT NULL,
  cuc_agent TEXT DEFAULT NULL,
  cuc_private TEXT DEFAULT NULL,
  PRIMARY KEY(cuc_id)
);

CREATE INDEX cuc_ip_hex_time ON cu_changes (cuc_ip_hex, cuc_timestamp);

CREATE INDEX cuc_xff_hex_time ON cu_changes (cuc_xff_hex, cuc_timestamp);

CREATE INDEX cuc_timestamp ON cu_changes (cuc_timestamp);

CREATE INDEX cuc_actor_ip_time ON cu_changes (cuc_actor, cuc_ip, cuc_timestamp);


CREATE TABLE cu_log_event (
  cule_id BIGSERIAL NOT NULL,
  cule_log_id INT DEFAULT 0 NOT NULL,
  cule_actor BIGINT NOT NULL,
  cule_timestamp TIMESTAMPTZ NOT NULL,
  cule_ip VARCHAR(255) DEFAULT '',
  cule_ip_hex VARCHAR(255) DEFAULT NULL,
  cule_xff TEXT DEFAULT '',
  cule_xff_hex VARCHAR(255) DEFAULT NULL,
  cule_agent TEXT DEFAULT NULL,
  PRIMARY KEY(cule_id)
);

CREATE INDEX cule_ip_hex_time ON cu_log_event (cule_ip_hex, cule_timestamp);

CREATE INDEX cule_xff_hex_time ON cu_log_event (cule_xff_hex, cule_timestamp);

CREATE INDEX cule_timestamp ON cu_log_event (cule_timestamp);

CREATE INDEX cule_actor_ip_time ON cu_log_event (
  cule_actor, cule_ip, cule_timestamp
);


CREATE TABLE cu_private_event (
  cupe_id BIGSERIAL NOT NULL,
  cupe_namespace INT DEFAULT 0 NOT NULL,
  cupe_title TEXT DEFAULT '' NOT NULL,
  cupe_actor BIGINT DEFAULT 0 NOT NULL,
  cupe_log_type TEXT DEFAULT '' NOT NULL,
  cupe_log_action TEXT DEFAULT '' NOT NULL,
  cupe_params TEXT NOT NULL,
  cupe_comment_id BIGINT DEFAULT 0 NOT NULL,
  cupe_page INT DEFAULT 0 NOT NULL,
  cupe_timestamp TIMESTAMPTZ NOT NULL,
  cupe_ip VARCHAR(255) DEFAULT '',
  cupe_ip_hex VARCHAR(255) DEFAULT NULL,
  cupe_xff TEXT DEFAULT '',
  cupe_xff_hex VARCHAR(255) DEFAULT NULL,
  cupe_agent TEXT DEFAULT NULL,
  cupe_private TEXT DEFAULT NULL,
  PRIMARY KEY(cupe_id)
);

CREATE INDEX cupe_ip_hex_time ON cu_private_event (cupe_ip_hex, cupe_timestamp);

CREATE INDEX cupe_xff_hex_time ON cu_private_event (cupe_xff_hex, cupe_timestamp);

CREATE INDEX cupe_timestamp ON cu_private_event (cupe_timestamp);

CREATE INDEX cupe_actor_ip_time ON cu_private_event (
  cupe_actor, cupe_ip, cupe_timestamp
);


CREATE TABLE cu_log (
  cul_id SERIAL NOT NULL,
  cul_timestamp TIMESTAMPTZ NOT NULL,
  cul_actor BIGINT NOT NULL,
  cul_reason_id BIGINT NOT NULL,
  cul_reason_plaintext_id BIGINT NOT NULL,
  cul_type TEXT NOT NULL,
  cul_target_id INT DEFAULT 0 NOT NULL,
  cul_target_text TEXT NOT NULL,
  cul_target_hex TEXT DEFAULT '' NOT NULL,
  cul_range_start TEXT DEFAULT '' NOT NULL,
  cul_range_end TEXT DEFAULT '' NOT NULL,
  PRIMARY KEY(cul_id)
);

CREATE INDEX cul_actor_time ON cu_log (cul_actor, cul_timestamp);

CREATE INDEX cul_type_target ON cu_log (
  cul_type, cul_target_id, cul_timestamp
);

CREATE INDEX cul_target_hex ON cu_log (cul_target_hex, cul_timestamp);

CREATE INDEX cul_range_start ON cu_log (cul_range_start, cul_timestamp);

CREATE INDEX cul_timestamp ON cu_log (cul_timestamp);
