-- This file is automatically generated using maintenance/generateSchemaChangeSql.php.
-- Source: extensions/CheckUser/schema/abstractSchemaChanges/patch-cu_private_event-modify-cupe_actor-nullable.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
ALTER TABLE /*_*/cu_private_event
  CHANGE cupe_actor cupe_actor BIGINT UNSIGNED DEFAULT 0;
