-- This file is automatically generated using maintenance/generateSchemaChangeSql.php.
-- Source: extensions/CheckUser/schema/abstractSchemaChanges/patch-cu_changes-drop-cuc_only_for_read_old.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
ALTER TABLE /*_*/cu_changes
  DROP cuc_only_for_read_old;