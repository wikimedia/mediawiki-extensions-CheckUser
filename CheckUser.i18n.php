<?php
/**
 * Internationalisation file for CheckUser extension.
 *
 * @addtogroup Extensions
*/

$wgCheckUserMessages = array();

$wgCheckUserMessages['en'] = array(
	'checkuser-summary'		 => 'This tool scans recent changes to retrieve the IPs used by a user or show the edit/user data for an IP.
	Users and edits can be retrieved with an XFF IP by appending the IP with "/xff". IPv4 (CIDR 16-32) and IPv6 (CIDR 64-128) are supported.
	No more than 5000 edits will be returned for performance reasons. Use this in accordance with policy.',
	'checkuser-logcase'		 => 'The log search is case sensitive.',
	'checkuser'              => 'Check user',
	'group-checkuser'        => 'Check users',
	'group-checkuser-member' => 'Check user',
	'grouppage-checkuser'    => '{{ns:project}}:Check user',
	'checkuser-reason'		 => 'Reason',
	'checkuser-showlog'		 => 'Show log',
	'checkuser-log'			 => 'Checkuser log',
	'checkuser-query'		 => 'Query recent changes',
	'checkuser-target'		 => 'User or IP',
	'checkuser-users'		 => 'Get users',
	'checkuser-edits'	  	 => 'Get edits from IP',
	'checkuser-ips'	  	 	 => 'Get IPs',
	'checkuser-search'	  	 => 'Search',
	'checkuser-empty'	 	 => 'The log contains no items.',
	'checkuser-nomatch'	  	 => 'No matches found.',
	'checkuser-check'	  	 => 'Check',
	'checkuser-log-fail'	 => 'Unable to add log entry',
	'checkuser-nolog'		 => 'No log file found.'
);
$wgCheckUserMessages['br'] = array(
	'checkuser'              => 'Gwiriañ an implijer',
	'group-checkuser'        => 'Gwiriañ an implijerien',
	'group-checkuser-member' => 'Gwiriañ an implijer',
	'grouppage-checkuser'    => '{{ns:project}}:Gwiriañ an implijer',
);
$wgCheckUserMessages['ca'] = array(
	'checkuser'              => 'Comprova l\'usuari',
	'group-checkuser'        => 'Comprova els usuaris',
	'group-checkuser-member' => 'Comprova l\'usuari',
	'grouppage-checkuser'    => '{{ns:project}}:Comprova l\'usuari',
);
$wgCheckUserMessages['cs'] = array(
	'checkuser'              => 'Revize uživatele',
	'group-checkuser'        => 'Revizoři',
	'group-checkuser-member' => 'Revizor',
	'grouppage-checkuser'    => '{{ns:project}}:Revize uživatele',
);
$wgCheckUserMessages['de'] = array(
	'checkuser-summary'	 => 'Dieses Werkzeug durchsucht die letzten Änderungen, um die IP-Adressen eines Benutzers
	bzw. die Bearbeitungen/Benutzernamen für eine IP-Adresse zu ermitteln. Benutzer und Bearbeitungen können auch nach XFF-IP-Adressen
	durchsucht werden, indem der IP-Adresse ein „/xff“ angehängt wird. IPv4 (CIDR 16-32 und IPv6 (CIDR 64-128) werden unterstützt.
	Aus Performance-Gründen werden maximal 5000 Bearbeitungen ausgegeben. Benutze diese in Übereinstimmung mit den Richtlinien.',
	'checkuser-logcase'	 => 'Die Suche im Logbuch unterscheidet zwischen Groß- und Kleinschreibung.',
	'checkuser'              => 'Checkuser',
	'group-checkuser'        => 'Checkusers',
	'group-checkuser-member' => 'Checkuser-Berechtigter',
	'grouppage-checkuser'    => '{{ns:project}}:CheckUser',
	'checkuser-reason'	 => 'Grund',
	'checkuser-showlog'	 => 'Logbuch anzeigen',
	'checkuser-log'		 => 'Checkuser-Logbuch',
	'checkuser-query'	 => 'Letzte Änderungen abfragen',
	'checkuser-target'	 => 'Benutzer oder IP-Adresse',
	'checkuser-users'	 => 'Hole Benutzer',
	'checkuser-edits'	 => 'Hole Bearbeitungen von IP-Adresse',
	'checkuser-ips'	  	 => 'Hole IP-Adressen',
	'checkuser-search'	 => 'Suche',
	'checkuser-empty'	 => 'Das Logbuch enthält keine Einträge.',
	'checkuser-nomatch'	 => 'Keine Übereinstimmungen gefunden.',
	'checkuser-check'	 => 'Ausführen',
	'checkuser-log-fail'	 => 'Logbuch-Eintrag kann nicht hinzugefügt werden.',
	'checkuser-nolog'	 => 'Kein Logbuch vorhanden.'
);
$wgCheckUserMessages['fi'] = array(
	'checkuser'              => 'Osoitepaljastin',
	'group-checkuser'        => 'Osoitepaljastimen käyttäjät',
	'group-checkuser-member' => 'Osoitepaljastimen käyttäjä',
	'grouppage-checkuser'    => '{{ns:project}}:Osoitepaljastin',
);
$wgCheckUserMessages['es'] = array(
	'checkuser'              => 'Verificador del usuarios',
	'group-checkuser'        => 'Verificadors del usuarios',
	'group-checkuser-member' => 'Verificador del usuarios',
	'grouppage-checkuser'    => '{{ns:project}}:verificador del usuarios',
);
$wgCheckUserMessages['fr'] = array(
	'checkuser-summary'		 => 'Cet outil balaye les changements récents pour rechercher l\'IPS employé par un utilisateur,
	montrer tous édite par un IP, ou énumère les utilisateurs qui ont employé les IPs. Les utilisateur et modifications peut
	être trouvé avec une IP XFF si il finit avec « /xff ». IPv4 (CIDR 16-32) et IPv6(CIDR 64-128) sont soutenus.
	Employer ceci selon les chaînes de policy.',
	'checkuser-logcase'		 => 'La recherche de notation est cas sensible.',
	'checkuser'              => 'Vérificateur d\'utilisateur',
	'group-checkuser'        => 'Vérificateurs d\'utilisateur',
	'group-checkuser-member' => 'Vérificateur d\'utilisateur',
	'grouppage-checkuser'    => '{{ns:projet}}:Vérificateur d\'utilisateur',
	'checkuser-reason'		 => 'Expanation ',
	'checkuser-showlog'		 => 'Montrer la notation',
	'checkuser-log'			 => 'Notation de Vérificateur d\'utilisateur',
	'checkuser-query'		 => 'Recherche par les changements récents',
	'checkuser-target'		 => 'Username ou IP',
	'checkuser-users'		 => 'Obtenir les users',
	'checkuser-edits'	  	 => 'Obtenir les modifications de l\'IP',
	'checkuser-ips'	  	 	 => 'Obtenir les IPs',
	'checkuser-search'	  	 => 'Recherche',
	'checkuser-empty'	 	 => 'La notation ne contient aucun article',
	'checkuser-nomatch'	  	 => 'Rien n\'a trouvé.',
	'checkuser-check'	  	 => 'Recherche',
	'checkuser-log-fail'	 => 'Incapable d\'ajouter l\'entrée de notation.',
	'checkuser-nolog'		 => 'Aucun dossier de notation trouvé.'
);
$wgCheckUserMessages['he'] = array(
	'checkuser'              => 'בדיקת משתמש',
	'group-checkuser'        => 'בודקים',
	'group-checkuser-member' => 'בודק',
	'grouppage-checkuser'    => '{{ns:project}}:בודק',
);
$wgCheckUserMessages['id'] = array(
	'checkuser-summary'		 => 'Peralatan ini memindai perubahan terbaru untuk mendapatkan IP yang digunakna oleh seorang pengguna atau menunjukkan data suntingan/pengguna untuk suatu IP.
	Pengguna dan suntingan dapat diperoleh dari suatu IP XFF dengan menambahkan "/xff" pada suatu IP. IPv4 (CIDR 16-32) dan IPv6 (CIDR 64-128) dapat digunakan.
	Karena alasan kinerja, maksimum hanya 5000 suntingan yang dapat diambil. Harap gunakan peralatan ini sesuai dengan kebijakan yang ada.',
	'checkuser-logcase'		 => 'Log ini bersifat sensitif terhadap kapitalisasi.',
	'checkuser'              => 'Periksa pengguna',
	'group-checkuser'        => 'Pemeriksa',
	'group-checkuser-member' => 'Pemeriksa',
	'grouppage-checkuser'    => '{{ns:project}}:Pemeriksa',
	'checkuser-reason'		 => 'Alasan',
	'checkuser-showlog'		 => 'Tampilkan log',
	'checkuser-log'			 => 'Log periksa pengguna',
	'checkuser-query'		 => 'Kueri perubahan terbaru',
	'checkuser-target'		 => 'Pengguna atau IP',
	'checkuser-users'		 => 'Cari pengguna',
	'checkuser-edits'	  	 => 'Cari suntingan dari IP',
	'checkuser-ips'	  	 	 => 'Cari IP',
	'checkuser-search'	  	 => 'Cari',
	'checkuser-empty'	 	 => 'Log kosong.',
	'checkuser-nomatch'	  	 => 'Data yang sesuai tidak ditemukan.',
	'checkuser-check'	  	 => 'Periksa',
	'checkuser-log-fail'	 => 'Entri log tidak dapat ditambahkan',
	'checkuser-nolog'		 => 'Berkas log tidak ditemukan.',

);
$wgCheckUserMessages['it'] = array(
	'checkuser'              => 'Controllo utenze',
	'group-checkuser'        => 'Controllori',
	'group-checkuser-member' => 'Controllore',
	'grouppage-checkuser'    => '{{ns:project}}:Controllo utenze',
);
$wgCheckUserMessages['ja'] = array(
	'checkuser'              => 'チェックユーザー',
	'group-checkuser'        => 'チェックユーザー',
	'group-checkuser-member' => 'チェックユーザー',
	'grouppage-checkuser'    => '{{ns:project}}:チェックユーザー',
);
$wgCheckUserMessages['kk-kz'] = array(
	'checkuser'              => 'Пайдаланушыны тексеру',
	'group-checkuser'        => 'Пайдаланушы тексерушілер',
	'group-checkuser-member' => 'пайдаланушы тексеруші',
	'grouppage-checkuser'    => '{{ns:project}}:Пайдаланушы тексерушілер',
);
$wgCheckUserMessages['kk-tr'] = array(
	'checkuser'              => 'Paýdalanwşını tekserw',
	'group-checkuser'        => 'Paýdalanwşı tekserwşiler',
	'group-checkuser-member' => 'paýdalanwşı tekserwşi',
	'grouppage-checkuser'    => '{{ns:project}}:Paýdalanwşı tekserwşiler',
);
$wgCheckUserMessages['kk-cn'] = array(
	'checkuser'              => 'پايدالانۋشىنى تەكسەرۋ',
	'group-checkuser'        => 'پايدالانۋشى تەكسەرۋشٴىلەر',
	'group-checkuser-member' => 'پايدالانۋشى تەكسەرۋشٴى',
	'grouppage-checkuser'    => '{{ns:project}}:پايدالانۋشى تەكسەرۋشٴىلەر',
);
$wgCheckUserMessages['kk'] = $wgCheckUserMessages['kk-kz'];
$wgCheckUserMessages['nl'] = array(
	'checkuser'              => 'Rechercheer gebruiker',
	'group-checkuser'        => 'Rechercheer gebruikers',
	'group-checkuser-member' => 'Rechercheer gebruiker',
	'grouppage-checkuser'    => '{{ns:project}}:Rechercheer gebruiker',
);
$wgCheckUserMessages['oc'] = array(
	'checkuser'              => 'Verificator d’utilizaire',
	'group-checkuser'        => 'Verificators d’utilizaire',
	'group-checkuser-member' => 'Verificator d’utilizaire',
	'grouppage-checkuser'    => '{{ns:project}}:Verificator d’utilizaire',
);
$wgCheckUserMessages['pl'] = array(
	'checkuser'              => 'Sprawdź użytkownika',
	'group-checkuser'        => 'Check users',
	'group-checkuser-member' => 'Check user',
	'grouppage-checkuser'    => '{{ns:project}}:Check user',
);
$wgCheckUserMessages['pt'] = array(
	'checkuser'              => 'Verificar utilizador',
	'group-checkuser'        => 'Verificar utilizadores',
	'group-checkuser-member' => 'Verificar utilizador',
	'grouppage-checkuser'    => '{{ns:project}}:Verificar utilizador',
);
$wgCheckUserMessages['ru'] = array(
	'checkuser'              => 'Проверить участника',
	'group-checkuser'        => 'Проверяющие участников',
	'group-checkuser-member' => 'проверяющий участников',
	'grouppage-checkuser'    => '{{ns:project}}:Проверка участников',
);
$wgCheckUserMessages['sk'] = array(
	'checkuser'              => 'Overiť používateľa',
	'group-checkuser'        => 'Revízor',
	'group-checkuser-member' => 'Revízori',
	'grouppage-checkuser'    => '{{ns:project}}:Revízia používateľa',
);
$wgCheckUserMessages['sr-ec'] = array(
	'checkuser'              => 'Чекјузер',
	'group-checkuser'        => 'Чекјузери',
	'group-checkuser-member' => 'Чекјузер',
	'grouppage-checkuser'    => '{{ns:project}}:Чекјузер',
);
$wgCheckUserMessages['sr-el'] = array(
	'checkuser'              => 'Čekjuzer',
	'group-checkuser'        => 'Čekjuzeri',
	'group-checkuser-member' => 'Čekjuzer',
	'grouppage-checkuser'    => '{{ns:project}}:Čekjuzer',
);
$wgCheckUserMessages['sr'] = $wgCheckUserMessages['sr-ec'];
$wgCheckUserMessages['wa'] = array(
	'checkuser' => 'Verifyî l\' uzeu',
);
$wgCheckUserMessages['zh-cn'] = array(
	'checkuser'              => '核对用户',
	'group-checkuser'        => '账户核查',
	'group-checkuser-member' => '账户核查',
	'grouppage-checkuser'    => '{{ns:project}}:账户核查',
);
$wgCheckUserMessages['zh-tw'] = array(
	'checkuser'              => '核對用戶',
	'group-checkuser'        => '帳戶查核',
	'group-checkuser-member' => '帳戶查核',
	'grouppage-checkuser'    => '{{ns:project}}:帳戶查核',
);
$wgCheckUserMessages['zh-yue'] = array(
	'checkuser'              => '核對用戶',
	'group-checkuser'        => '稽查員',
	'group-checkuser-member' => '稽查員',
	'grouppage-checkuser'    => '{{ns:project}}:稽查員',
);
$wgCheckUserMessages['zh-hk'] = $wgCheckUserMessages['zh-tw'];
$wgCheckUserMessages['zh-sg'] = $wgCheckUserMessages['zh-cn'];
?>
