-- This file is automatically generated using maintenance/generateSchemaChangeSql.php.
-- Source: extensions/CheckUser/schema/abstractSchemaChanges/patch-cu_log-defaults.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
ALTER TABLE  /*_*/cu_log
CHANGE  cul_user cul_user INT UNSIGNED DEFAULT 0 NOT NULL,
CHANGE  cul_user_text cul_user_text VARBINARY(255) DEFAULT '' NOT NULL;