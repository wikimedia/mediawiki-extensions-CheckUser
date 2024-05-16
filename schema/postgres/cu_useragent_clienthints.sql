-- This file is automatically generated using maintenance/generateSchemaSql.php.
-- Source: CheckUser/schema/cu_useragent_clienthints.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
CREATE TABLE cu_useragent_clienthints (
  uach_id SERIAL NOT NULL,
  uach_name VARCHAR(32) NOT NULL,
  uach_value VARCHAR(255) NOT NULL,
  PRIMARY KEY(uach_id)
);

CREATE UNIQUE INDEX uach_name_value ON cu_useragent_clienthints (uach_name, uach_value);