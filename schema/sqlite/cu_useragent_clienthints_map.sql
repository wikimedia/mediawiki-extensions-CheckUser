-- This file is automatically generated using maintenance/generateSchemaSql.php.
-- Source: CheckUser/schema/cu_useragent_clienthints_map.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
CREATE TABLE /*_*/cu_useragent_clienthints_map (
  uachm_uach_id INTEGER UNSIGNED NOT NULL,
  uachm_reference_id INTEGER UNSIGNED NOT NULL,
  uachm_reference_type SMALLINT DEFAULT 0 NOT NULL,
  PRIMARY KEY(
    uachm_uach_id, uachm_reference_type,
    uachm_reference_id
  )
);

CREATE INDEX uachm_reference_id ON /*_*/cu_useragent_clienthints_map (uachm_reference_id);