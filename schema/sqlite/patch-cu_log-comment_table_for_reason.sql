-- This file is automatically generated using maintenance/generateSchemaChangeSql.php.
-- Source: extensions/CheckUser/schema/abstractSchemaChanges/patch-cu_log-comment_table_for_reason.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
ALTER TABLE  /*_*/cu_log
ADD  COLUMN cul_reason_id BIGINT UNSIGNED DEFAULT 0 NOT NULL;
ALTER TABLE  /*_*/cu_log
ADD  COLUMN cul_reason_plaintext_id BIGINT UNSIGNED DEFAULT 0 NOT NULL;