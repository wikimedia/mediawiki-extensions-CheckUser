<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

$cfg['directory_list'] = array_merge(
	$cfg['directory_list'],
	[
		'../../extensions/AbuseFilter',
		'../../extensions/CentralAuth',
		'../../extensions/EventLogging',
		'../../extensions/GuidedTour',
		'../../extensions/GlobalBlocking',
		'../../extensions/TorBlock',
		'../../extensions/UserMerge'
	]
);

$cfg['exclude_analysis_directory_list'] = array_merge(
	$cfg['exclude_analysis_directory_list'],
	[
		'../../extensions/AbuseFilter',
		'../../extensions/CentralAuth',
		'../../extensions/EventLogging',
		'../../extensions/GuidedTour',
		'../../extensions/GlobalBlocking',
		'../../extensions/TorBlock',
		'../../extensions/UserMerge'
	]
);

return $cfg;
