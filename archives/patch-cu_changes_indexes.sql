-- Improves indexes for better peformance with large result sets
-- vim: autoindent syn=mysql sts=2 sw=2
-- Replace /*$wgDBprefix*/ with the proper prefix
  
ALTER TABLE /*$wgDBprefix*/cu_changes 
  DROP INDEX cuc_ip_hex,
  DROP INDEX cuc_user,
  DROP INDEX cuc_xff_hex,
  ADD INDEX cuc_ip_hex_time (cuc_ip_hex,cuc_timestamp),
  ADD INDEX cuc_user_time (cuc_user,cuc_timestamp),
  ADD INDEX cuc_xff_hex_time (cuc_xff_hex,cuc_timestamp);
