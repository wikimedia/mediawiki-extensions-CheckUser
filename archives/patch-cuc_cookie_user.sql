-- Adds the cookie data column needed to enable $wgCURecordCookies
-- vim: autoindent syn=mysql sts=2 sw=2
-- Replace /*$wgDBprefix*/ with the proper prefix
  
ALTER TABLE /*$wgDBprefix*/cu_changes 
  ADD cuc_cookie_user VARCHAR(255) BINARY default NULL;
