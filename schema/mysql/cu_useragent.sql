-- This file is automatically generated using maintenance/generateSchemaSql.php.
-- Source: extensions/CheckUser/schema/cu_useragent.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
CREATE TABLE /*_*/cu_useragent (
  cuua_id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
  cuua_text VARBINARY(255) NOT NULL,
  INDEX cuua_text (cuua_text),
  PRIMARY KEY(cuua_id)
) /*$wgDBTableOptions*/;