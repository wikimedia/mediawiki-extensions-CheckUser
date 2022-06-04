-- Add cul_reason_id in cu_log
ALTER TABLE /*_*/cu_log
	ADD cul_reason_id bigint(20) unsigned null default null after cul_reason;
