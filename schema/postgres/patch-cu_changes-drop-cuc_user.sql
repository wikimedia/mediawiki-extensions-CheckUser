-- This file is automatically generated using maintenance/generateSchemaChangeSql.php.
-- Source: extensions/CheckUser/schema/abstractSchemaChanges/patch-cu_changes-drop-cuc_user.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
DROP  INDEX cuc_user_ip_time;
ALTER TABLE  cu_changes
DROP  cuc_user;
ALTER TABLE  cu_changes
DROP  cuc_user_text;