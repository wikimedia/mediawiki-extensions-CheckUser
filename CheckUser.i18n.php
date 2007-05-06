<?php
/**
 * Internationalisation file for CheckUser extension.
 *
 * @addtogroup Extensions
*/

$wgCheckUserMessages = array();

$wgCheckUserMessages['en'] = array(
	'checkuser-summary'      => 'This tool scans recent changes to retrieve the IPs used by a user or show the edit/user data for an IP.
	Users and edits can be retrieved with an XFF IP by appending the IP with "/xff". IPv4 (CIDR 16-32) and IPv6 (CIDR 64-128) are supported.
	No more than 5000 edits will be returned for performance reasons. Use this in accordance with policy.',
	'checkuser-logcase'      => 'The log search is case sensitive.',
	'checkuser'              => 'Check user',
	'group-checkuser'        => 'Check users',
	'group-checkuser-member' => 'Check user',
	'grouppage-checkuser'    => '{{ns:project}}:Check user',
	'checkuser-reason'       => 'Reason',
	'checkuser-showlog'      => 'Show log',
	'checkuser-log'          => 'Checkuser log',
	'checkuser-query'        => 'Query recent changes',
	'checkuser-target'       => 'User or IP',
	'checkuser-users'        => 'Get users',
	'checkuser-edits'        => 'Get edits from IP',
	'checkuser-ips'          => 'Get IPs',
	'checkuser-search'       => 'Search',
	'checkuser-empty'        => 'The log contains no items.',
	'checkuser-nomatch'      => 'No matches found.',
	'checkuser-check'        => 'Check',
	'checkuser-log-fail'     => 'Unable to add log entry',
	'checkuser-nolog'        => 'No log file found.'
);
$wgCheckUserMessages['ar'] = array(
	'checkuser'              => 'افحص مستخدم',
	'group-checkuser'        => 'مدققو مستخدم',
	'group-checkuser-member' => 'مدقق مستخدم',
	'grouppage-checkuser'    => '{{ns:project}}:تدقيق مستخدم',
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
	'checkuser-summary'      => 'Tämän työkalun avulla voidaan tutkia tuoreet muutokset ja paljastaa käyttäjien IP-osoitteet tai noutaa IP-osoitteiden muokkaukset ja käyttäjätiedot.
	Käyttäjät ja muokkaukset voidaan hakea myös uudelleenohjausosoitteen (X-Forwarded-For) takaa käyttämällä IP-osoitteen perässä <tt>/xff</tt> -merkintää. Työkalu tukee sekä IPv4 (CIDR 16–32) ja IPv6 (CIDR 64–128) -standardeja.',
	'checkuser-logcase'      => 'Haku lokista on kirjainkokoriippuvainen.',
	'checkuser'              => 'Osoitepaljastin',
	'group-checkuser'        => 'Osoitepaljastimen käyttäjät',
	'group-checkuser-member' => 'Osoitepaljastimen käyttäjä',
	'grouppage-checkuser'    => '{{ns:project}}:Osoitepaljastin',
	'checkuser-reason'       => 'Syy',
	'checkuser-showlog'      => 'Näytä loki',
	'checkuser-log'          => 'Osoitepaljastinloki',
	'checkuser-query'        => 'Hae tuoreet muutokset',
	'checkuser-target'       => 'Käyttäjä tai IP-osoite',
	'checkuser-users'        => 'Hae käyttäjät',
	'checkuser-edits'        => 'Hae IP-osoitteen muokkaukset',
	'checkuser-ips'          => 'Hae IP-osoitteet',
	'checkuser-search'       => 'Etsi',
	'checkuser-empty'        => 'Ei lokitapahtumia.',
	'checkuser-nomatch'      => 'Hakuehtoihin sopivia tuloksia ei löytynyt.',
	'checkuser-check'        => 'Tarkasta',
	'checkuser-log-fail'     => 'Lokitapahtuman lisäys epäonnistui',
	'checkuser-nolog'        => 'Lokitiedostoa ei löytynyt.',
);
$wgCheckUserMessages['es'] = array(
	'checkuser'              => 'Verificador del usuarios',
	'group-checkuser'        => 'Verificadors del usuarios',
	'group-checkuser-member' => 'Verificador del usuarios',
	'grouppage-checkuser'    => '{{ns:project}}:verificador del usuarios',
);
$wgCheckUserMessages['fr'] = array(
	'checkuser-summary'		 => 'Cet outil balaye les changements récents à la recherche de l’adresse IP employée par un utilisateur, affiche toutes les éditions d’une adresse IP (même enregistrée), ou liste les comptes utilisés par une adresse IP. Les comptes et modifications peuvent être trouvés avec une IP XFF si elle finit avec « /xff ». Il est possible d’utiliser les protocoles IPv4 (CIDR 16-32) et IPv6 (CIDR 64-128). Veuillez utiliser cet outil dans les limites de la charte d’utilisation.',
	'checkuser-logcase'		 => 'La recherche dans le Journal est sensible à la casse.',
	'checkuser'              => 'Vérificateur d’utilisateur',
	'group-checkuser'        => 'Vérificateurs d’utilisateur',
	'group-checkuser-member' => 'Vérificateur d’utilisateur',
	'grouppage-checkuser'    => '{{ns:projet}}:Vérificateur d’utilisateur',
	'checkuser-reason'		 => 'Motif',
	'checkuser-showlog'		 => 'Afficher le journal',
	'checkuser-log'			 => 'Notation de Vérificateur d’utilisateur',
	'checkuser-query'		 => 'Recherche par les changements récents',
	'checkuser-target'		 => 'Nom de l’utilisateur ou IP',
	'checkuser-users'		 => 'Obtenir les utilisateurs',
	'checkuser-edits'	  	 => 'Obtenir les modifications de l’IP',
	'checkuser-ips'	  	 	 => 'Obtenir les adresses IP',
	'checkuser-search'	  	 => 'Recherche',
	'checkuser-empty'	 	 => 'Le journal ne contient aucun article',
	'checkuser-nomatch'	  	 => 'Recherches infructueuses.',
	'checkuser-check'	  	 => 'Recherche',
	'checkuser-log-fail'	 => 'Impossible d’ajouter l’entrée du journal.',
	'checkuser-nolog'		 => 'Aucune entrée dans le Journal.'
);
$wgCheckUserMessages['he'] = array(
	'checkuser-summary'      => 'כלי זה סורק את השינויים האחרונים במטרה למצוא את כתובות ה־IP שהשתמש בהן משתמש מסוים או כדי להציג את כל המידע על המשתמשים שהשתמשו בכתובת IP ועל העריכות שבוצעו ממנה.
	ניתן לקבל עריכות ומשתמשים מכתובות IP של הכותרת X-Forwarded-For באמצעות הוספת הטקסט "/xff" לסוף הכתובת. הן כתובות IPv4 (כלומר, CIDR 16-32) והן כתובות IPv6 (כלומר, CIDR 64-128) נתמכות.
	לא יוחזרו יותר מ־5000 עריכות מסיבות של עומס על השרתים. אנא השתמשו בכלי זה בהתאם למדיניות.',
	'checkuser-logcase'      => 'החיפוש ביומנים הוא תלוי־רישיות.',
	'checkuser'              => 'בדיקת משתמש',
	'group-checkuser'        => 'בודקים',
	'group-checkuser-member' => 'בודק',
	'grouppage-checkuser'    => '{{ns:project}}:בודק',
	'checkuser-reason'       => 'סיבה',
	'checkuser-showlog'      => 'הצגת יומן',
	'checkuser-log'          => 'יומן בדיקות',
	'checkuser-query'        => 'בדוק שינויים אחרונים',
	'checkuser-target'       => 'שם משתמש או כתובת IP',
	'checkuser-users'        => 'הצגת משתמשים',
	'checkuser-edits'        => 'הצגת עריכות מכתובת IP מסוימת',
	'checkuser-ips'          => 'הצגת כתובות IP',
	'checkuser-search'       => 'חיפוש',
	'checkuser-empty'        => 'אין פריטים ביומן.',
	'checkuser-nomatch'      => 'לא נמצאו התאמות.',
	'checkuser-check'        => 'בדיקה',
	'checkuser-log-fail'     => 'לא ניתן היה להוסיף פריט ליומן',
	'checkuser-nolog'        => 'לא נמצא קובץ יומן.'
);
$wgCheckUserMessages['id'] = array(
	'checkuser-summary'		 => 'Peralatan ini memindai perubahan terbaru untuk mendapatkan IP yang digunakan oleh seorang pengguna atau menunjukkan data suntingan/pengguna untuk suatu IP.
	Pengguna dan suntingan dapat diperoleh dari suatu IP XFF dengan menambahkan "/xff" pada suatu IP. IPv4 (CIDR 16-32) dan IPv6 (CIDR 64-128) dapat digunakan.
	Karena alasan kinerja, maksimum hanya 5000 suntingan yang dapat diambil. Harap gunakan peralatan ini sesuai dengan kebijakan yang ada.',
	'checkuser-logcase'		 => 'Log ini bersifat sensitif terhadap kapitalisasi.',
	'checkuser'              => 'Pemeriksaan pengguna',
	'group-checkuser'        => 'Pemeriksa',
	'group-checkuser-member' => 'Pemeriksa',
	'grouppage-checkuser'    => '{{ns:project}}:Pemeriksa',
	'checkuser-reason'		 => 'Alasan',
	'checkuser-showlog'		 => 'Tampilkan log',
	'checkuser-log'			 => 'Log pemeriksaan pengguna',
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
	'checkuser-summary'      => 'Бұл құрал пайдаланушы қолданған IP жайлар үшін, немесе IP жай түзету/пайдаланушы деректерін көрсету үшін жуықтағы өзгерістерді қарап шығады.
	Пайдаланушыларды мен түзетулерді XFF IP арқылы IP жайға "/xff" дегенді қосып келтіруге болады. IPv4 (CIDR 16-32) және IPv6 (CIDR 64-128) арқауланады.
	Орындаушылық себептерімен 5000 түзетуден артық қайтарылмамайды. Бұны ережелерге сәйкес пайдаланыңыз.',
	'checkuser-logcase'      => 'Журналдан іздеу әріп бас-кішілігін айырады.',
	'checkuser'              => 'Пайдаланушы сынаушы',
	'group-checkuser'        => 'Пайдаланушы сынаушылар',
	'group-checkuser-member' => 'пайдаланушы сынаушы',
	'grouppage-checkuser'    => '{{ns:project}}:Пайдаланушы сынаушы',
	'checkuser-reason'       => 'Себеп',
	'checkuser-showlog'      => 'Журналды көрсету',
	'checkuser-log'          => 'Пайдаланушы сынаушы журналы',
	'checkuser-query'        => 'Жуықтағы өзгерістерді сұраныстау',
	'checkuser-target'       => 'Пайдаланушы/IP',
	'checkuser-users'        => 'Пайдаланушыларды алу',
	'checkuser-edits'        => 'IP түзетулерін алу',
	'checkuser-ips'          => 'IP жайларды алу',
	'checkuser-search'       => 'Іздеу',
	'checkuser-empty'        => 'Журналда еш жазба жоқ.',
	'checkuser-nomatch'      => 'Сәйкес табылмады.',
	'checkuser-check'        => 'Сынау',
	'checkuser-log-fail'     => 'Журналға жазба үстелінбеді',
	'checkuser-nolog'        => 'Журнал файлы табылмады.'
);
$wgCheckUserMessages['kk-tr'] = array(
	'checkuser-summary'      => 'Bul qural paýdalanwşı qoldanğan IP jaýlar üşin, nemese IP jaý tüzetw/paýdalanwşı derekterin körsetw üşin jwıqtağı özgeristerdi qarap şığadı.
	Paýdalanwşılardı men tüzetwlerdi XFF IP arqılı IP jaýğa "/xff" degendi qosıp keltirwge boladı. IPv4 (CIDR 16-32) jäne IPv6 (CIDR 64-128) arqawlanadı.
	Orındawşılıq sebepterimen 5000 tüzetwden artıq qaýtarılmamaýdı. Bunı erejelerge säýkes paýdalanıñız.',
	'checkuser-logcase'      => 'Jwrnaldan izdew ärip bas-kişiligin aýıradı.',
	'checkuser'              => 'Paýdalanwşı sınawşı',
	'group-checkuser'        => 'Paýdalanwşı sınawşılar',
	'group-checkuser-member' => 'paýdalanwşı sınawşı',
	'grouppage-checkuser'    => '{{ns:project}}:Paýdalanwşı sınawşı',
	'checkuser-reason'       => 'Sebep',
	'checkuser-showlog'      => 'Jwrnaldı körsetw',
	'checkuser-log'          => 'Paýdalanwşı sınawşı jwrnalı',
	'checkuser-query'        => 'Jwıqtağı özgeristerdi suranıstaw',
	'checkuser-target'       => 'Paýdalanwşı/IP',
	'checkuser-users'        => 'Paýdalanwşılardı alw',
	'checkuser-edits'        => 'IP tüzetwlerin alw',
	'checkuser-ips'          => 'IP jaýlardı alw',
	'checkuser-search'       => 'İzdew',
	'checkuser-empty'        => 'Jwrnalda eş jazba joq.',
	'checkuser-nomatch'      => 'Säýkes tabılmadı.',
	'checkuser-check'        => 'Sınaw',
	'checkuser-log-fail'     => 'Jwrnalğa jazba üstelinbedi',
	'checkuser-nolog'        => 'Jwrnal faýlı tabılmadı.'
);
$wgCheckUserMessages['kk-cn'] = array(
	'checkuser-summary'      => 'بۇل قۇرال پايدالانۋشى قولدانعان IP جايلار ٴۇشٴىن, نەمەسە IP جاي تٴۇزەتۋ/پايدالانۋشى دەرەكتەرٴىن كٴورسەتۋ ٴۇشٴىن جۋىقتاعى ٴوزگەرٴىستەردٴى قاراپ شىعادى.
	پايدالانۋشىلاردى مەن تٴۇزەتۋلەردٴى XFF IP ارقىلى IP جايعا "/xff" دەگەندٴى قوسىپ كەلتٴىرۋگە بولادى. IPv4 (CIDR 16-32) جٴانە IPv6 (CIDR 64-128) ارقاۋلانادى.
	ورىنداۋشىلىق سەبەپتەرٴىمەن 5000 تٴۇزەتۋدەن ارتىق قايتارىلمامايدى. بۇنى ەرەجەلەرگە سٴايكەس پايدالانىڭىز.',
	'checkuser-logcase'      => 'جۋرنالدان ٴىزدەۋ ٴارٴىپ باس-كٴىشٴىلٴىگٴىن ايىرادى.',
	'checkuser'              => 'پايدالانۋشى سىناۋشى',
	'group-checkuser'        => 'پايدالانۋشى سىناۋشىلار',
	'group-checkuser-member' => 'پايدالانۋشى سىناۋشى',
	'grouppage-checkuser'    => '{{ns:project}}:پايدالانۋشى سىناۋشى',
	'checkuser-reason'       => 'سەبەپ',
	'checkuser-showlog'      => 'جۋرنالدى كٴورسەتۋ',
	'checkuser-log'          => 'پايدالانۋشى سىناۋشى جۋرنالى',
	'checkuser-query'        => 'جۋىقتاعى ٴوزگەرٴىستەردٴى سۇرانىستاۋ',
	'checkuser-target'       => 'پايدالانۋشى/IP',
	'checkuser-users'        => 'پايدالانۋشىلاردى الۋ',
	'checkuser-edits'        => 'IP تٴۇزەتۋلەرٴىن الۋ',
	'checkuser-ips'          => 'IP جايلاردى الۋ',
	'checkuser-search'       => 'ٴىزدەۋ',
	'checkuser-empty'        => 'جۋرنالدا ەش جازبا جوق.',
	'checkuser-nomatch'      => 'سٴايكەس تابىلمادى.',
	'checkuser-check'        => 'سىناۋ',
	'checkuser-log-fail'     => 'جۋرنالعا جازبا ٴۇستەلٴىنبەدٴى',
	'checkuser-nolog'        => 'جۋرنال فايلى تابىلمادى.'
);
$wgCheckUserMessages['kk'] = $wgCheckUserMessages['kk-kz'];
$wgCheckUserMessages['nl'] = array(
	'checkuser'              => 'Rechercheer gebruiker',
	'group-checkuser'        => 'Rechercheer gebruikers',
	'group-checkuser-member' => 'Rechercheer gebruiker',
	'grouppage-checkuser'    => '{{ns:project}}:Rechercheer gebruiker',
);
$wgCheckUserMessages['oc'] = array(
	'checkuser-summary'      => 'Aqueste esplech passa en revista los cambiaments recents per recercar l\'IPS emplegada per un utilizaire, mostrar totas las edicions fachas per una IP, o per enumerar los utilizaires qu\'an emplegat las IPs. Los utilizaires e las modificacions pòdon èsser trobatss amb una IP XFF se s\'acaba amb « /xff ». IPv4 (CIDR 16-32) e IPv6(CIDR 64-128) son suportats. Emplegatz aquò segon las cadenas de caractèrs.',
	'checkuser-logcase'      => 'La recèrca dins lo Jornal es sensibla a la cassa.',
	'checkuser'              => 'Verificator d’utilizaire',
	'group-checkuser'        => 'Verificators d’utilizaire',
	'group-checkuser-member' => 'Verificator d’utilizaire',
	'grouppage-checkuser'    => '{{ns:project}}:Verificator d’utilizaire',
	'checkuser-reason'       => 'Explicacion',
	'checkuser-showlog'      => 'Mostrar la lista obtenguda',
	'checkuser-log'          => 'Notacion de Verificator d\'utilizaire',
	'checkuser-query'        => 'Recèrca pels darrièrs cambiaments',
	'checkuser-target'       => 'Nom de l\'utilizaire o IP',
	'checkuser-users'        => 'Obténer los utilizaires',
	'checkuser-edits'        => 'Obténer las modificacions de l\'IP',
	'checkuser-ips'          => 'Obténer las IPs',
	'checkuser-search'       => 'Recèrca',
	'checkuser-empty'        => 'Lo jornal conten pas cap d\'article',
	'checkuser-nomatch'      => 'Recèrcas infructuosas.',
	'checkuser-check'        => 'Recèrca',
	'checkuser-log-fail'     => 'Incapaç d\'ajustar la dintrada del jornal.',
	'checkuser-nolog'        => 'Cap de dintrada dins lo Jornal.',
);
$wgCheckUserMessages['pl'] = array(
	'checkuser'              => 'Sprawdź użytkownika',
	'group-checkuser'        => 'Check users',
	'group-checkuser-member' => 'Check user',
	'grouppage-checkuser'    => '{{ns:project}}:Check user',
);
$wgCheckUserMessages['pt'] = array(
	'checkuser-summary'      => 'Esta ferramente varre as Mudanças recentes para obter os endereços de IP de um utilizador ou para exibir os dados de edições/utilizadores para um IP.
	Utilizadores e edições e edições podem ser obtidos com um IP XFF colocando-se "/xff" no final do endereço. Endereços IPv4 (CIDR 16-32) e IPv6 (CIDR 64-128) são suportados.
	Não serão retornadas mais de 5000 edições por motivos de desempenho. O uso desta ferramenta deverá estar de acordo com as políticas.',
	'checkuser-logcase'      => 'As buscas nos registos são sensíveis a letras maiúsculas ou minúsculas.',
	'checkuser'              => 'Verificar utilizador',
	'group-checkuser'        => 'CheckUser',
	'group-checkuser-member' => 'CheckUsers',
	'grouppage-checkuser'    => '{{ns:project}}:CheckUser',
	'checkuser-reason'       => 'Motivo',
	'checkuser-showlog'      => 'Exibir registos',
	'checkuser-log'          => 'Registos de verificação de utilizadores',
	'checkuser-query'        => 'Query recent changes',
	'checkuser-target'       => 'Utilizador ou IP',
	'checkuser-users'        => 'Obter utilizadores',
	'checkuser-edits'        => 'Obter edições de IPs',
	'checkuser-ips'          => 'Obter IPs',
	'checkuser-search'       => 'Pesquisar',
	'checkuser-empty'        => 'O registo não contém itens.',
	'checkuser-nomatch'      => 'Não foram encontrados resultados.',
	'checkuser-check'        => 'Verificar',
	'checkuser-log-fail'     => 'Não foi possível adicionar entradas ao registo',
);
$wgCheckUserMessages['ru'] = array(
	'checkuser'              => 'Проверить участника',
	'group-checkuser'        => 'Проверяющие участников',
	'group-checkuser-member' => 'проверяющий участников',
	'grouppage-checkuser'    => '{{ns:project}}:Проверка участников',
);
$wgCheckUserMessages['sk'] = array(
	'checkuser-summary'      => 'Tento nástroj kontroluje Posledné úpravy, aby získal IP adresy používané používateľom alebo zobrazil úpravy/používateľské dáta IP adresy.
	Používateľov a úpravy je možné získať s XFF IP pridaním "/xff" k IP. Sú podporované IPv4 (CIDR 16-32) a IPv6 (CIDR 64-128).
	Z dôvodov výkonnosti nebude vrátených viac ako 5000 úprav. Túto funkciu využívajte len v súlade s platnou politikou.',
	'checkuser-logcase'      => 'Vyhľadávanie v zázname zohľadňuje veľkosť písmen.',
	'checkuser'              => 'Overiť používateľa',
	'group-checkuser'        => 'Revízor',
	'group-checkuser-member' => 'Revízori',
	'grouppage-checkuser'    => '{{ns:project}}:Revízia používateľa',
	'checkuser-reason'       => 'Dôvod',
	'checkuser-showlog'      => 'Zobraziť záznam',
	'checkuser-log'          => 'Záznam kontroly používateľov',
	'checkuser-query'        => 'Získať z posledných úprav',
	'checkuser-target'       => 'Používateľ alebo IP',
	'checkuser-users'        => 'Získať používateľov',
	'checkuser-edits'        => 'Získať úpravy z IP',
	'checkuser-ips'          => 'Získať IP adresy',
	'checkuser-search'       => 'Hľadať',
	'checkuser-empty'        => 'Záznam neobsahuje žiadne položky.',
	'checkuser-nomatch'      => 'Žiadny vyhovujúci záznam.',
	'checkuser-check'        => 'Skontrolovať',
	'checkuser-log-fail'     => 'Nebolo možné pridať položku záznamu',
	'checkuser-nolog'        => 'Nebol nájdený súbor záznamu.',
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
$wgCheckUserMessages['sv'] = array(
	'checkuser-summary'      => 'Det här verktyget söker igenom de senaste ändringarna för att hämta IP-adresser för en användare, eller redigeringar och användare för en IP-adress.
Användare och redigeringar kan visas med IP-adress från XFF genom att lägga till "/xff" efter IP-adressen. Verktyget stödjer IPv4 (CIDR 16-32) och IPv6 (CIDR 64-128).
På grund av prestandaskäl så visas inte mer än 5000 redigeringar. Använd verktyget i enlighet med policy.',
	'checkuser-logcase'      => 'Loggsökning är skiftlägeskänslig.',
	'checkuser'              => 'Kontroll av användare',
	'group-checkuser'        => 'Användarkontrollanter',
	'group-checkuser-member' => 'Användarkontrollant',
	'grouppage-checkuser'    => '{{ns:project}}:Användarkontrollant',
	'checkuser-reason'       => 'Anledning',
	'checkuser-showlog'      => 'Visa logg',
	'checkuser-log'          => 'Logg över användarkontroller',
	'checkuser-query'        => 'Sök de senaste ändringarna',
	'checkuser-target'       => 'Användare eller IP',
	'checkuser-users'        => 'Hämta användare',
	'checkuser-edits'        => 'Hämta redigeringar från IP-adress',
	'checkuser-ips'          => 'Hämta IP-adresser',
	'checkuser-search'       => 'Sök',
	'checkuser-empty'        => 'Loggen innehåller inga poster.',
	'checkuser-nomatch'      => 'Inga träffar hittades.',
	'checkuser-check'        => 'Kontrollera',
	'checkuser-log-fail'     => 'Loggposten kunde inte läggas i loggfilen.',
	'checkuser-nolog'        => 'Hittade ingen loggfil.'
);
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
	'group-checkuser'        => '用戶查核',
	'group-checkuser-member' => '用戶查核',
	'grouppage-checkuser'    => '{{ns:project}}:用戶查核',
	'checkuser-reason'       => '理由',
	'checkuser-showlog'      => '顯示記錄',
	'checkuser-log'          => '用戶查核記錄',
	'checkuser-query'        => '查詢最近更改',
	'checkuser-target'       => '用戶名稱或IP位扯',
	'checkuser-users'        => '查詢用戶名稱',
	'checkuser-edits'        => '從IP位址查詢編輯記錄',
	'checkuser-ips'          => '查詢IP位址',
	'checkuser-search'       => '搜尋',
	'checkuser-empty'        => '記錄裡沒有資料。',
	'checkuser-nomatch'      => '沒有符合的資訊',
	'checkuser-check'        => '查詢',
	'checkuser-log-fail'     => '無法更新記錄。',
	'checkuser-nolog'        => '找不到記錄檔'
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
