-- This file is automatically generated using maintenance/generateSchemaChangeSql.php.
-- Source: schema/abstractSchemaChanges/patch-cu_changes-drop-defaults.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
ALTER TABLE /*_*/cu_changes
  CHANGE cuc_actor cuc_actor BIGINT UNSIGNED NOT NULL,
  CHANGE cuc_comment_id cuc_comment_id BIGINT UNSIGNED NOT NULL;
