<?php
/**
 * Internationalisation file for CheckUser extension.
 *
 * @addtogroup Extensions
*/

$messages = array();

$messages['en'] = array(
	'checkuser-summary'      => 'This tool scans recent changes to retrieve the IPs used by a user or show the edit/user data for an IP.
	Users and edits by a client IP can be retrieved via XFF headers by appending the IP with "/xff". IPv4 (CIDR 16-32) and IPv6 (CIDR 64-128) are supported.
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
	'checkuser-nolog'        => 'No log file found.',
	'checkuser-blocked'      => 'Blocked',
	'checkuser-too-many'     => 'Too many results, please narrow down the CIDR. Here are the IPs used (5000 max, sorted by address):',
);

$messages['af'] = array(
	'checkuser-search'       => 'Soek',
);

$messages['ang'] = array(
	'checkuser-reason'       => 'Racu',
);

/* Arabic (Meno25) */
$messages['ar'] = array(
	'checkuser-summary'      => 'هذه الأداة تفحص أحدث التغييرات لاسترجاع الأيبيهات المستخدمة بواسطة مستخدم أو عرض بيانات التعديل/المستخدم لأيبي.
	المستخمون والتعديلات بواسطة أيبي عميل يمكن استرجاعها من خلال عناوين XFF عبر طرق الأيبي IP ب"/xff". IPv4 (CIDR 16-32) و IPv6 (CIDR 64-128) مدعومان.
	لا أكثر من 5000 تعديل سيتم عرضها لأسباب تتعلق بالأداء. استخدم هذا بالتوافق مع السياسة.',
	'checkuser-logcase'      => 'بحث السجل حساس لحالة الحروف.',
	'checkuser'              => 'تدقيق مستخدم',
	'group-checkuser'        => 'مدققو مستخدم',
	'group-checkuser-member' => 'مدقق مستخدم',
	'grouppage-checkuser'    => '{{ns:project}}:تدقيق مستخدم',
	'checkuser-reason'       => 'السبب',
	'checkuser-showlog'      => 'عرض السجل',
	'checkuser-log'          => 'سجل تدقيق المستخدم',
	'checkuser-query'        => 'فحص أحدث التغييرات',
	'checkuser-target'       => 'مستخدم أو عنوان أيبي',
	'checkuser-users'        => 'عرض المستخدمين',
	'checkuser-edits'        => 'عرض التعديلات من الأيبي',
	'checkuser-ips'          => 'عرض الأيبيهات',
	'checkuser-search'       => 'بحث',
	'checkuser-empty'        => 'لا توجد مدخلات في السجل.',
	'checkuser-nomatch'      => 'لم يتم العثور على مدخلات مطابقة.',
	'checkuser-check'        => 'فحص',
	'checkuser-log-fail'     => 'غير قادر على إضافة مدخلة للسجل',
	'checkuser-nolog'        => 'لم يتم العثور على ملف سجل.',
	'checkuser-blocked'      => 'ممنوع',
	'checkuser-too-many'     => 'نتائج كثيرة جدا، من فضلك قم بتضييق عنوان الأيبي:',
);

/** Asturian (Asturianu)
 * @author SPQRobin
 */
$messages['ast'] = array(
	'checkuser-summary'  => "Esta ferramienta escanea los cambeos recientes pa obtener les IP usaes por un usuariu o p'amosar les ediciones o usuarios d'una IP.
	Los usuarios y ediciones correspondientes a una IP puen obtenese per aciu de les cabeceres XFF añadiendo depués de la IP \\\"/xff\\\". Puen usase los protocolos IPv4 (CIDR 16-32) y IPv6 (CIDR 64-128).
	Por razones de rendimientu nun s'amosarán más de 5.000 ediciones. Emplega esta ferramienta  acordies cola política d'usu.",
	'checkuser-logcase'  => 'La busca nel rexistru distingue ente mayúscules y minúscules.',
	'checkuser'          => "Comprobador d'usuarios",
	'checkuser-reason'   => 'Razón',
	'checkuser-showlog'  => 'Amosar el rexistru',
	'checkuser-log'      => "Rexistru de comprobadores d'usuarios",
	'checkuser-query'    => 'Buscar nos cambeos recientes',
	'checkuser-target'   => 'Usuariu o IP',
	'checkuser-users'    => 'Obtener usuarios',
	'checkuser-edits'    => 'Obtener les ediciones de la IP',
	'checkuser-ips'      => 'Obtener les IP',
	'checkuser-search'   => 'Buscar',
	'checkuser-empty'    => 'El rexistru nun tien nengún artículu.',
	'checkuser-nomatch'  => "Nun s'atoparon coincidencies.",
	'checkuser-check'    => 'Comprobar',
	'checkuser-log-fail' => 'Nun se pue añader la entrada nel rexistru',
	'checkuser-nolog'    => 'Nun hai entraes nel rexistru.',
	'checkuser-blocked'  => 'Bloquiáu',
);

$messages['bcl'] = array(
	'checkuser-reason'       => 'Rasón',
	'checkuser-showlog'      => 'Ipahiling an mga historial',
	'checkuser-target'       => 'Parágamit o IP',
	'checkuser-users'        => 'Kûanón',
	'checkuser-ips'          => 'Kûanón an mga IP',
	'checkuser-search'       => 'Hanápon',
	'checkuser-blocked'      => 'Pigbágat',
);

$messages['bg'] = array(
	'checkuser-summary'      => 'Този инструмент сканира последните промени и извлича IP адресите, използвани от потребител или показва информацията за редакциите/потребителя за посоченото IP.
	Потребители и редакции по клиентско IP могат да бъдат извлечени чрез XFF headers като се добави IP с "/xff". Поддържат се IPv4 (CIDR 16-32) и IPv6 (CIDR 64-128).
	От съображения, свързани с производителността на уикито, ще бъдат показани не повече от 5000 редакции. Използвайте инструмента съобразно установената политика.',
	'checkuser-logcase'      => 'Търсенето в дневника различава главни от малки букви.',
	'checkuser'              => 'Проверяване на потребител',
	'group-checkuser'        => 'Проверяващи',
	'group-checkuser-member' => 'Проверяващ',
	'grouppage-checkuser'    => '{{ns:project}}:Проверяващи',
	'checkuser-reason'       => 'Причина',
	'checkuser-showlog'      => 'Показване на дневника',
	'checkuser-log'          => 'Дневник на проверяващите',
	'checkuser-query'        => 'Заявка към последните промени',
	'checkuser-target'       => 'Потребител или IP',
	'checkuser-users'        => 'Извличане на потребители',
	'checkuser-edits'        => 'Извличане на редакции от IP',
	'checkuser-ips'          => 'Извличане на IP адреси',
	'checkuser-search'       => 'Търсене',
	'checkuser-empty'        => 'Дневникът не съдържа записи.',
	'checkuser-nomatch'      => 'Няма открити съвпадения.',
	'checkuser-check'        => 'Проверка',
	'checkuser-log-fail'     => 'Беше невъзможно да се добави запис в дневника',
	'checkuser-nolog'        => 'Не беше открит дневник.',
	'checkuser-blocked'      => 'Блокиран',
	'checkuser-too-many'     => 'Твърде много резултати. Показани са използваните IP адреси (най-много 5000, сортирани по адрес):',
);

/** Breton (Brezhoneg)
 * @author Fulup
 */
$messages['br'] = array(
	'checkuser'              => 'Gwiriañ an implijer',
	'group-checkuser'        => 'Gwiriañ an implijerien',
	'group-checkuser-member' => 'Gwiriañ an implijer',
	'grouppage-checkuser'    => '{{ns:project}}:Gwiriañ an implijer',
	'checkuser-reason'       => 'Abeg',
	'checkuser-showlog'      => 'Diskouez ar marilh',
	'checkuser-search'       => 'Klask',
	'checkuser-check'        => 'Gwiriañ',
	'checkuser-blocked'      => 'Stanket',
);

$messages['ca'] = array(
	'checkuser'              => 'Comprova l\'usuari',
	'group-checkuser'        => 'Comprova els usuaris',
	'group-checkuser-member' => 'Comprova l\'usuari',
	'grouppage-checkuser'    => '{{ns:project}}:Comprova l\'usuari',
);

$messages['cdo'] = array(
	'checkuser-search'       => 'Sìng-tō̤',
);

$messages['co'] = array(
	'group-checkuser'        => 'Controllori',
	'group-checkuser-member' => 'Controllore',
	'grouppage-checkuser'    => '{{ns:project}}:Controllori',
);

$messages['cs'] = array(
	'checkuser'              => 'Kontrola uživatele',
	'group-checkuser'        => 'Revizoři',
	'group-checkuser-member' => 'Revizor',
	'grouppage-checkuser'    => '{{ns:project}}:Revize uživatele',
);

$messages['de'] = array(
	'checkuser-summary'	 => 'Dieses Werkzeug durchsucht die letzten Änderungen, um die IP-Adressen eines Benutzers
	bzw. die Bearbeitungen/Benutzernamen für eine IP-Adresse zu ermitteln. Benutzer und Bearbeitungen einer IP-Adresse können auch nach Informationen aus den XFF-Headern
	abgefragt werden, indem der IP-Adresse ein „/xff“ angehängt wird. IPv4 (CIDR 16-32) und IPv6 (CIDR 64-128) werden unterstützt.
	Aus Performance-Gründen werden maximal 5000 Bearbeitungen ausgegeben. Benutze CheckUser ausschließlich in Übereinstimmung mit den Datenschutzrichtlinien.',
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
	'checkuser-search'	 => 'Suchen',
	'checkuser-empty'	 => 'Das Logbuch enthält keine Einträge.',
	'checkuser-nomatch'	 => 'Keine Übereinstimmungen gefunden.',
	'checkuser-check'	 => 'Ausführen',
	'checkuser-log-fail'	 => 'Logbuch-Eintrag kann nicht hinzugefügt werden.',
	'checkuser-nolog'	 => 'Kein Logbuch vorhanden.',
	'checkuser-blocked'      => 'gesperrt',
	'checkuser-too-many'     => 'Die Ergebnisliste ist zu lang, bitte grenze den IP-Bereich weiter ein. Hier sind die benutzten IP-Adressen (maximal 5000, sortiert nach Adresse):',
);

/** Greek (Ελληνικά)
 * @author Consta
 */
$messages['el'] = array(
	'checkuser-reason' => 'Λόγος',
	'checkuser-target' => 'Χρήστης ή IP',
	'checkuser-search' => 'Αναζήτηση',
);

/** Spanish (Español)
 * @author Dmcdevit
 * @author Spacebirdy
 */
$messages['es'] = array(
	'checkuser-summary'      => 'Esta herramienta explora los cambios recientes para obtener las IPs usadas por un usuario o la información de ediciones/usuarios hechos/usados por una IP.
También se puede obtener usuarios y ediciones de un cliente IP vía XFF por añadir "/xff". IPv4 (CIDR 16-32) y IPv6 (CIDR 64-128) funcionan.
No se muestra más que 5000 ediciones por motivos de rendimiento. Usa esta herramienta en acuerdo con la ley orgánica de protección de datos.',
	'checkuser-logcase'      => 'El buscador del registro sabe distinguir entre mayúsculas y minúsculas.',
	'checkuser'              => 'Verificador de usuarios',
	'group-checkuser'        => 'Verificadores de usuarios',
	'group-checkuser-member' => 'Verificador de usuarios',
	'grouppage-checkuser'    => '{{ns:project}}:verificador de usuarios',
	'checkuser-reason'       => 'Motivo',
	'checkuser-showlog'      => 'Ver registro',
	'checkuser-log'          => 'Registro de CheckUser',
	'checkuser-query'        => 'Buscar en cambios recientes',
	'checkuser-target'       => 'Usuario o IP',
	'checkuser-users'        => 'Obtener usuarios',
	'checkuser-edits'        => 'Obtener ediciones de IP',
	'checkuser-ips'          => 'Obtener IPs',
	'checkuser-search'       => 'Buscar',
	'checkuser-empty'        => 'No hay elementos en el registro.',
	'checkuser-nomatch'      => 'No hay elementos en el registro con esas condiciones.',
	'checkuser-check'        => 'Examinar',
	'checkuser-log-fail'     => 'No se puede añadir este elemento al registro.',
	'checkuser-nolog'        => 'No se encuentra un archivo del registro.',
	'checkuser-blocked'      => 'bloqueado',
	'checkuser-too-many'     => 'Hay demasiados resultados. Por favor limita el CIDR. Aquí ves las IPs usadas (5000, ordenar por dirección):',
);

$messages['eu'] = array(
	'checkuser'              => 'Erabiltzailea egiaztatu',
	'checkuser-reason'       => 'Arrazoia',
	'checkuser-search'       => 'Bilatu',
	'checkuser-nomatch'      => 'Ez da bat datorren emaitzarik aurkitu.',
);

$messages['ext'] = array(
	'checkuser-reason'       => 'Razón',
	'checkuser-search'       => 'Landeal',
);

$messages['fa'] = array(
	'checkuser-summary'      => 'این ابزار تغییرات اخیر را برای به دست آوردن نشانی‌های اینترنتی (IP) استفاده شده توسط یک کاربر و یا تعیین ویرایش‌های انجام شده از طریق یک نشانی اینترنتی جستجو می‌کند.
کاربرها و ویرایش‌های مرتبط با یک نشانی اینترنتی را می‌توان با توجه به اطلاعات سرآیند XFF (با افزودن «‏‎/xff» به انتهای نشانی IP) پیدا کرد.
هر دو پروتکل IPv4 (معادل CIDR 16-32) و IPv6 (معادل CIDR 64-128) توسط این ابزار پشتیبانی می‌شوند.',
	'checkuser-logcase'      => 'جستجوی سیاهه به کوچک یا بزرگ بودن حروف حساس است.',
	'checkuser'              => 'بازرس کاربر',
	'group-checkuser'        => 'بازرسان کاربر',
	'group-checkuser-member' => 'بازرس کاربر',
	'grouppage-checkuser'    => '{{ns:project}}:بازرسی کاربر',
	'checkuser-reason'       => 'دلیل',
	'checkuser-showlog'      => 'نمایش سیاهه',
	'checkuser-log'          => 'سیاهه بازرسی کاربر',
	'checkuser-query'        => 'جستجوی تغییرات اخیر',
	'checkuser-target'       => 'کاربر یا نشانی اینترنتی',
	'checkuser-users'        => 'فهرست کردن کاربرها',
	'checkuser-edits'        => 'نمایش ویرایش‌های مربوط به این نشانی اینترنتی',
	'checkuser-ips'          => 'فهرست کردن نشانی‌های اینترنتی',
	'checkuser-search'       => 'جستجو',
	'checkuser-empty'        => 'سیاهه خالی است.',
	'checkuser-nomatch'      => 'موردی که مطابقت داشته باشد پیدا نشد.',
	'checkuser-check'        => 'بررسی',
	'checkuser-log-fail'     => 'امکان افزودن اطلاعات به سیاهه وجود ندارد',
	'checkuser-nolog'        => 'پرونده سیاهه پیدا نشد.',
	'checkuser-blocked'      => 'دسترسی قطع شد',
);

$messages['fi'] = array(
	'checkuser-summary'      => 'Tämän työkalun avulla voidaan tutkia tuoreet muutokset ja paljastaa käyttäjien IP-osoitteet tai noutaa IP-osoitteiden muokkaukset ja käyttäjätiedot.
	Käyttäjät ja muokkaukset voidaan hakea myös uudelleenohjausosoitteen (X-Forwarded-For) takaa käyttämällä IP-osoitteen perässä <tt>/xff</tt> -merkintää. Työkalu tukee sekä IPv4 (CIDR 16–32) ja IPv6 (CIDR 64–128) -standardeja.',
	'checkuser-logcase'      => 'Haku lokista on kirjainkokoriippuvainen.',
	'checkuser'              => 'Osoitepaljastin',
	'group-checkuser'        => 'osoitepaljastimen käyttäjät',
	'group-checkuser-member' => 'osoitepaljastimen käyttäjä',
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
	'checkuser-blocked'      => 'Estetty',
	'checkuser-too-many'     => 'Liian monta tulosta, rajoita IP-osoitetta:',
);

$messages['fo'] = array(
	'checkuser'              => 'Rannsakanar brúkari',
	'group-checkuser'        => 'Rannsakanar brúkari',
	'group-checkuser-member' => 'Rannsakanar brúkarir',
	'grouppage-checkuser'    => '{{ns:project}}:Rannsakanar brúkari',
	'checkuser-search'       => 'Leita',
);

$messages['fr'] = array(
	'checkuser-summary'      => 'Cet outil parcourt la liste des changements récents à la recherche de l’adresse IP employée par un utilisateur, affiche toutes les éditions d’une adresse IP (même enregistrée), ou liste les comptes utilisés par une adresse IP. Les comptes et les modifications peuvent être trouvés avec une IP XFF si elle finit avec « /xff ». Il est possible d’utiliser les protocoles IPv4 (CIDR 16-32) et IPv6 (CIDR 64-128). Le nombre d’éditions affichables est limité à {{formatnum:5000}} pour des questions de performance du serveur. Veuillez utiliser cet outil dans les limites de la charte d’utilisation.',
	'checkuser-logcase'      => 'La recherche dans le journal est sensible à la casse.',
	'checkuser'              => 'Vérificateur d’utilisateur',
	'group-checkuser'        => 'Vérificateurs d’utilisateur',
	'group-checkuser-member' => 'Vérificateur d’utilisateur',
	'grouppage-checkuser'    => '{{ns:projet}}:Vérificateur d’utilisateur',
	'checkuser-reason'       => 'Motif',
	'checkuser-showlog'      => 'Afficher le journal',
	'checkuser-log'          => 'Journal de vérificateur d’utilisateur',
	'checkuser-query'        => 'Recherche par les changements récents',
	'checkuser-target'       => 'Nom d\'utilisateur ou adresse IP',
	'checkuser-users'        => 'Obtenir les utilisateurs',
	'checkuser-edits'        => 'Obtenir les modifications de l’adresse IP',
	'checkuser-ips'          => 'Obtenir les adresses IP',
	'checkuser-search'       => 'Recherche',
	'checkuser-empty'        => 'Le journal ne contient aucun article',
	'checkuser-nomatch'      => 'Recherches infructueuses.',
	'checkuser-check'        => 'Recherche',
	'checkuser-log-fail'     => 'Impossible d’ajouter l’entrée du journal.',
	'checkuser-nolog'        => 'Aucune entrée dans le journal',
	'checkuser-blocked'      => 'Bloqué',
	'checkuser-too-many'     => 'Trop de résultats. Veuillez limiter la recherche sur les adresses IP :',
);

$messages['frc'] = array(
	'checkuser-summary'      => 'Cet outil observe les derniers changements pour retirer le IP de l\'useur ou pour montrer l\'information de l\'editeur/de l\'useur pour cet IP. Les userus et les changements par le IP d\'un client pouvont être reçus par les en-têtes XFF par additionner le IP avec "/xff". Ipv4 (CIDR 16-32) and IPv6 (CIDR 64-128) sont supportés. Cet outil retourne pas plus que 5000 changements par rapport à la qualité d\'ouvrage.  Usez ça ici en accord avec les régluations.',
	'checkuser-logcase'      => 'La charche des notes est sensible aux lettres basses ou hautes.',
	'checkuser'              => '\'Gardez-voir à l\'useur encore',
	'group-checkuser'        => '\'Gardez-voir aux useurs encore',
	'group-checkuser-member' => '\'Gardez-voir à l\'useur encore',
	'grouppage-checkuser'    => '{{ns:project}}:\'Gardez-voir à l\'useur encore',
	'checkuser-reason'       => 'Raison',
	'checkuser-showlog'      => 'Montrer les notes',
	'checkuser-log'          => 'Notes de la Garde d\'useur',
	'checkuser-query'        => 'Charchez les nouveaux changements',
	'checkuser-target'       => 'Nom de l\'useur ou IP',
	'checkuser-users'        => 'Obtenir les useurs',
	'checkuser-edits'        => 'Obtenir les modifications du IP',
	'checkuser-ips'          => 'Obtenir les adresses IP',
	'checkuser-search'       => 'Charche',
	'checkuser-empty'        => 'Les notes sont vides.',
	'checkuser-nomatch'      => 'Rien pareil trouvé.',
	'checkuser-check'        => 'Charche',
	'checkuser-log-fail'     => 'Pas capable d\'additionner la note',
	'checkuser-nolog'        => 'Rien trouvé dans les notes.',
);

/** Franco-Provençal (Arpetan)
 * @author ChrisPtDe
 */
$messages['frp'] = array(
	'checkuser-summary'      => 'Ceti outil parcôrt la lista des dèrriérs changements a la rechèrche de l’adrèce IP empleyê per un utilisator, afiche totes les èdicions d’una adrèce IP (méma enregistrâ), ou ben liste los comptos utilisâs per una adrèce IP.
	Los comptos et les modificacions pôvont étre trovâs avouéc una IP XFF se sè chavone avouéc « /xff ». O est possiblo d’utilisar los protocolos IPv4 (CIDR 16-32) et IPv6 (CIDR 64-128).
	Lo nombro d’èdicions afichâbles est limitâ a {{formatnum:5000}} por des quèstions de pèrformence du sèrvior. Volyéd utilisar ceti outil dens les limites de la chârta d’usâjo.',
	'checkuser-logcase'      => 'La rechèrche dens lo jornal est sensibla a la câssa.',
	'checkuser'              => 'Controlor d’utilisator',
	'group-checkuser'        => 'Controlors d’utilisator',
	'group-checkuser-member' => 'Controlor d’utilisator',
	'grouppage-checkuser'    => '{{ns:project}}:Controlors d’utilisator',
	'checkuser-reason'       => 'Rêson',
	'checkuser-showlog'      => 'Afichiér lo jornal',
	'checkuser-log'          => 'Jornal de controlor d’utilisator',
	'checkuser-query'        => 'Rechèrche per los dèrriérs changements',
	'checkuser-target'       => 'Nom d’utilisator ou adrèce IP',
	'checkuser-users'        => 'Obtegnir los utilisators',
	'checkuser-edits'        => 'Obtegnir les modificacions de l’adrèce IP',
	'checkuser-ips'          => 'Obtegnir les adrèces IP',
	'checkuser-search'       => 'Rechèrche',
	'checkuser-empty'        => 'Lo jornal contint gins d’articllo.',
	'checkuser-nomatch'      => 'Rechèrches que balyont ren.',
	'checkuser-check'        => 'Rechèrche',
	'checkuser-log-fail'     => 'Empossiblo d’apondre l’entrâ du jornal.',
	'checkuser-nolog'        => 'Niona entrâ dens lo jornal.',
	'checkuser-blocked'      => 'Blocâ',
	'checkuser-too-many'     => 'Trop de rèsultats. Volyéd limitar la rechèrche sur les adrèces IP :',
);

/** Irish (Gaeilge)
 * @author Alison
 */
$messages['ga'] = array(
	'checkuser-logcase'  => 'Tá na logaí seo cásíogair.',
	'checkuser-reason'   => 'Fáth',
	'checkuser-showlog'  => 'Taispeáin logaí',
	'checkuser-log'      => 'Logaí checkuser',
	'checkuser-query'    => 'Iarratais ar athrú úrnua',
	'checkuser-target'   => 'Úsáideoir ná seoladh IP',
	'checkuser-users'    => 'Faigh úsáideoira',
	'checkuser-edits'    => 'Faigh athraigh don seoladh IP seo',
	'checkuser-ips'      => 'Faigh Seolaidh IP',
	'checkuser-search'   => 'Cuardaigh',
	'checkuser-empty'    => 'Níl aon míreanna sa log.',
	'checkuser-nomatch'  => 'Ní faigheann aon comhoiriúnaigh.',
	'checkuser-check'    => 'Iarratais',
	'checkuser-log-fail' => 'Ní féidir iontráil a cur sa log',
	'checkuser-nolog'    => 'Ní bhfaigheann comhad loga.',
	'checkuser-blocked'  => 'Cosanta',
	'checkuser-too-many' => "Tá le mórán torthaí, caolaigh an CIDR le d'thoil. Seo iad na seolaidh IP (5000 uasta, sórtáilte le seoladh):",
);

$messages['gl'] = array(
	'checkuser-summary'      => 'Esta ferramenta analiza os cambios recentes para recuperar os enderezos IPs utilizados por un usuario ou amosar as edicións / datos do usuario dun enderezo de IP.
Os usuarios e as edicións por un cliente IP poden ser recuperados a través das cabeceiras XFF engadindo o enderezo IP con "/ xff". IPv4 (CIDR 16-32) e o IPv6 (CIDR 64-128) están soportadas.',
	'checkuser-logcase'      => 'O rexistro de búsqueda é sensíbel a maiúsculas e minúsculas.',
	'checkuser'              => 'Verificador de usuarios',
	'group-checkuser'        => 'Verificadores de usuarios',
	'group-checkuser-member' => 'Verificador usuarios',
	'grouppage-checkuser'    => '{{ns:project}}:Verificador de usuarios',
	'checkuser-reason'       => 'Razón',
	'checkuser-showlog'      => 'Amosar rexistro',
	'checkuser-log'          => 'Rexistro de verificador de usuarios',
	'checkuser-query'        => 'Consulta de cambios recentes',
	'checkuser-target'       => 'Usuario ou enderezo IP',
	'checkuser-users'        => 'Obter usuarios',
	'checkuser-edits'        => 'Obter edicións de enderezos IP',
	'checkuser-ips'          => 'Conseguir enderezos IPs',
	'checkuser-search'       => 'Buscar',
	'checkuser-empty'        => 'O rexistro non contén artigos.',
	'checkuser-nomatch'      => 'Non se atoparon coincidencias.',
	'checkuser-check'        => 'Comprobar',
	'checkuser-log-fail'     => 'Non é posíbel engadir unha entrada no rexistro',
	'checkuser-nolog'        => 'Ningún arquivo de rexistro.',
	'checkuser-blocked'      => 'Bloqueado',
	'checkuser-too-many'     => 'Hai demasiados resultados, restrinxa o enderezo IP:',
);

$messages['grc'] = array(
	'checkuser-search'       => 'Ζητεῖν',
);

/** Gujarati (ગુજરાતી) */
$messages['gu'] = array(
	'checkuser-reason' => 'કારણ',
	'checkuser-search' => 'શોધો',
);

$messages['he'] = array(
	'checkuser-summary'      => 'כלי זה סורק את השינויים האחרונים במטרה למצוא את כתובות ה־IP שהשתמש בהן משתמש מסוים או כדי להציג את כל המידע על המשתמשים שהשתמשו בכתובת IP ועל העריכות שבוצעו ממנה.
	ניתן לקבל עריכות ומשתמשים מכתובות IP של הכותרת X-Forwarded-For באמצעות הוספת הטקסט "/xff" לסוף הכתובת. הן כתובות IPv4 (כלומר, CIDR 16-32) והן כתובות IPv6 (כלומר, CIDR 64-128) נתמכות.
	לא יוחזרו יותר מ־5000 עריכות מסיבות של עומס על השרתים. אנא השתמשו בכלי זה בהתאם למדיניות.',
	'checkuser-logcase'      => 'החיפוש ביומנים הוא תלוי־רישיות.',
	'checkuser'              => 'בדיקת משתמש',
	'group-checkuser'        => 'בודקים',
	'group-checkuser-member' => 'בודק',
	'grouppage-checkuser'    => 'Project:בודק',
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
	'checkuser-nolog'        => 'לא נמצא קובץ יומן.',
	'checkuser-blocked'      => 'חסום',
	'checkuser-too-many'     => 'נמצאו תוצאות רבות מדי, אנא צמצו את טווח כתובות ה־IP. אלה כתובת ה־IP שנעשה בהן שימוש (מוצגות 5,000 לכל היותר):',
);

$messages['hr'] = array(
	'checkuser-summary'      => 'Ovaj alat pretražuje nedavne promjene i pronalazi IP adrese suradnika ili prikazuje uređivanja/ime suradnika ako je zadana IP adresa. Suradnici i uređivanja mogu biti dobiveni po XFF zaglavljima dodavanjem "/xff" na kraj IP adrese. Podržane su IPv4 (CIDR 16-32) i IPv6 (CIDR 64-128) adrese. Rezultat ima maksimalno 5.000 zapisa iz tehničkih razloga. Rabite ovaj alat u skladu s pravilima.',
	'checkuser-logcase'      => 'Provjera evidencije razlikuje velika i mala slova',
	'checkuser'              => 'Provjeri suradnika',
	'group-checkuser'        => 'Check users',#identical but defined
	'group-checkuser-member' => 'Check user',#identical but defined
	'grouppage-checkuser'    => '{{ns:project}}:Checkuser',
	'checkuser-reason'       => 'Razlog',
	'checkuser-showlog'      => 'Pokaži evidenciju',
	'checkuser-log'          => 'Checkuser evidencija',
	'checkuser-query'        => 'Provjeri nedavne promjene',
	'checkuser-target'       => 'Suradnik ili IP',
	'checkuser-users'        => 'suradničko ime',
	'checkuser-edits'        => 'uređivanja tog IP-a',
	'checkuser-ips'          => 'Nađi IP adrese',
	'checkuser-search'       => 'Traži',
	'checkuser-empty'        => 'Evidencija je prazna.',
	'checkuser-nomatch'      => 'Nema suradnika s tom IP adresom.',
	'checkuser-check'        => 'Provjeri',
	'checkuser-log-fail'     => 'Ne mogu dodati zapis',
	'checkuser-nolog'        => 'Evidencijska datoteka nije nađena',
	'checkuser-blocked'      => 'Blokiran',
	'checkuser-too-many'     => 'Previše rezultata, molimo suzite opseg (CIDR). Slijede rabljene IP adrese (najviše njih 5000, poredano abecedno):',
);
$messages['hsb'] = array(
	'checkuser-summary'      => 'Tutón nastroj přepytuje aktualne změny, zo by IP-adresy wužiwarja zwěsćił abo změny abo wužiwarske daty za IP pokazał.
Wužiwarjo a změny IP-adresy dadźa so přez XFF-hłowy wotwołać, připowěšo "/xff" na IP-adresu. IPv4 (CIDR 16-32) a IPv6 (CIDR 64-128) so podpěrujetej.',
	'checkuser-logcase'      => 'Pytanje w protokolu rozeznawa mjez wulko- a małopisanjom.',
	'checkuser'              => 'Wužiwarja kontrolować',
	'group-checkuser'        => 'Kontrolerojo',
	'group-checkuser-member' => 'Kontroler',
	'grouppage-checkuser'    => '{{ns:project}}:Checkuser',
	'checkuser-reason'       => 'Přičina',
	'checkuser-showlog'      => 'Protokol pokazać',
	'checkuser-log'          => 'Protokol wužiwarskeje kontrole',
	'checkuser-query'        => 'Poslednje změny wotprašeć',
	'checkuser-target'       => 'Wužiwar abo IP-adresa',
	'checkuser-users'        => 'Wužiwarjow pokazać',
	'checkuser-edits'        => 'Změny z IP-adresy přinjesć',
	'checkuser-ips'          => 'IP-adresy pokazać',
	'checkuser-search'       => 'Pytać',
	'checkuser-empty'        => 'Protokol njewobsahuje zapiski.',
	'checkuser-nomatch'      => 'Žane wotpowědniki namakane.',
	'checkuser-check'        => 'Pruwować',
	'checkuser-log-fail'     => 'Njemóžno protokolowy zapisk přidać.',
	'checkuser-nolog'        => 'Žadyn protokol namakany.',
	'checkuser-blocked'      => 'Zablokowany',
	'checkuser-too-many'     => 'Přewjele wuslědkow, prošu zamjezuj IP-adresu:',
);

/** Hungarian (Magyar)
 * @author Bdanee
 */
$messages['hu'] = array(
	'group-checkuser'        => 'IP-ellenőrök',
	'group-checkuser-member' => 'IP-ellenőr',
	'grouppage-checkuser'    => '{{ns:project}}:IP-ellenőrök',

);

$messages['id'] = array(
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

/** Icelandic (Íslenska)
 * @author SPQRobin
 * @author Spacebirdy
 * @author Jóna Þórunn
 */
$messages['is'] = array(
	'checkuser'              => 'Skoða notanda',
	'group-checkuser'        => 'Athuga notendur',
	'group-checkuser-member' => 'Athuga notanda',
	'checkuser-reason'       => 'Ástæða',
	'checkuser-target'       => 'Notandi eða IP',
	'checkuser-search'       => 'Leita',
	'checkuser-nomatch'      => 'Engar niðurstöður fundust.',
	'checkuser-check'        => 'Athuga',
);

/** Italian (Italiano)
 * @author Gianfranco
 * @author BrokenArrow
 * @author .anaconda
 */
$messages['it'] = array(
	'checkuser-summary'      => 'Questo strumento analizza le modifiche recenti per recuperare gli indirizzi IP utilizzati da un utente o mostrare contributi e dati di un IP. Utenti e contributi di un client IP possono essere rintracciati attraverso gli header XFF aggiungendo all\'IP il suffisso "/xff". Sono supportati IPv4 (CIDR 16-32) e IPv6 (CIDR 64-128). Non saranno restituite più di 5.000 modifiche, per ragioni di prestazioni. Usa questo strumento in stretta conformità alle policy.',
	'checkuser-logcase'      => "La ricerca nei log è ''case sensitive'' (distingue fra maiuscole e minuscole).",
	'checkuser'              => 'Controllo utenze',
	'group-checkuser'        => 'Controllori',
	'group-checkuser-member' => 'Controllore',
	'grouppage-checkuser'    => '{{ns:project}}:Controllo utenze',
	'checkuser-reason'       => 'Motivazione',
	'checkuser-showlog'      => 'Mostra il log',
	'checkuser-log'          => 'Log dei checkuser',
	'checkuser-query'        => 'Cerca nelle ultime modifiche',
	'checkuser-target'       => 'Utente o IP',
	'checkuser-users'        => 'Cerca utenti',
	'checkuser-edits'        => 'Vedi i contributi degli IP',
	'checkuser-ips'          => 'Cerca IP',
	'checkuser-search'       => 'Cerca',
	'checkuser-empty'        => 'Il log non contiene dati.',
	'checkuser-nomatch'      => 'Nessun risultato trovato.',
	'checkuser-check'        => 'Controlla',
	'checkuser-log-fail'     => 'Impossibile aggiungere la voce al log',
	'checkuser-nolog'        => 'Non è stato trovato alcun file di log.',
	'checkuser-blocked'      => 'Bloccato',
	'checkuser-too-many'     => 'Il numero di risultati è eccessivo, usare un CIDR più ristretto. Di seguito sono indicati gli indirizzi IP utilizzati (fino a un massimo di 5000, ordinati per indirizzo):',
);

$messages['ja'] = array(
	'checkuser-summary'      => 'チェックユーザーでは、利用者が使っているIPアドレスや、IPアドレスから編集及び利用者データを、最近の更新から調査します。
クライアントIPによる利用者と編集は、IPアドレスと共に「/xff」を追加すれば、XFFヘッダを通して検索出来ます。
IPv4 (CIDR 16-32) と IPv6 (CIDR 64-128) が利用出来ます。
パフォーマンス上の理由により、5000件の編集しか返答出来ません。
方針に従って利用してください。',
	'checkuser-logcase'      => 'ログの検索では大文字と小文字を区別します。',
	'checkuser'              => 'チェックユーザー',
	'group-checkuser'        => 'チェックユーザー',
	'group-checkuser-member' => 'チェックユーザー',
	'grouppage-checkuser'    => '{{ns:project}}:チェックユーザー',
	'checkuser-reason'       => '理由',
	'checkuser-showlog'      => 'ログを閲覧',
	'checkuser-log'          => 'チェックユーザー・ログ',
	'checkuser-query'        => '最近の更新を照会',
	'checkuser-target'       => '利用者名又はIPアドレス',
	'checkuser-users'        => '利用者名を得る',
	'checkuser-edits'        => 'IPアドレスからの編集を得る',
	'checkuser-ips'          => 'IPアドレスを得る',
	'checkuser-search'       => '検索',
	'checkuser-empty'        => 'ログ内にアイテムがありません。',
	'checkuser-check'        => '調査',
	'checkuser-nolog'        => 'ログファイルが見つかりません。',
);

$messages['kk-cyrl'] = array(
	'checkuser-summary'      => 'Бұл құрал пайдаланушы қолданған IP жайлар үшін, немесе IP жай түзету/пайдаланушы деректерін көрсету үшін жуықтағы өзгерістерді қарап шығады.
	Пайдаланушыларды мен түзетулерді XFF IP арқылы IP жайға «/xff» дегенді қосып келтіруге болады. IPv4 (CIDR 16-32) және IPv6 (CIDR 64-128) арқауланады.
	Орындаушылық себептерімен 5000 түзетуден артық қайтарылмайды. Бұны ережелерге сәйкес пайдаланыңыз.',
	'checkuser-logcase'      => 'Журналдан іздеу әріп бас-кішілігін айырады.',
	'checkuser'              => 'Пайдаланушыны сынау',
	'group-checkuser'        => 'Пайдаланушы сынаушылар',
	'group-checkuser-member' => 'пайдаланушы сынаушы',
	'grouppage-checkuser'    => '{{ns:project}}:Пайдаланушыны сынау',
	'checkuser-reason'       => 'Себебі',
	'checkuser-showlog'      => 'Журналды көрсет',
	'checkuser-log'          => 'Пайдаланушыны сынау журналы',
	'checkuser-query'        => 'Жуықтағы өзгерістерді сұраныстау',
	'checkuser-target'       => 'Пайдаланушы аты / IP жай',
	'checkuser-users'        => 'Пайдаланушыларды келтіру',
	'checkuser-edits'        => 'IP жайдан жасалған түзетулерді келтіру',
	'checkuser-ips'          => 'IP жайларды келтіру',
	'checkuser-search'       => 'Іздеу',
	'checkuser-empty'        => 'Журналда еш жазба жоқ.',
	'checkuser-nomatch'      => 'Сәйкес табылмады.',
	'checkuser-check'        => 'Сынау',
	'checkuser-log-fail'     => 'Журналға жазба үстелінбеді',
	'checkuser-nolog'        => 'Журнал файлы табылмады.',
	'checkuser-blocked'      => 'Бұғатталған',
);

$messages['kk-latn'] = array(
	'checkuser-summary'      => 'Bul qural paýdalanwşı qoldanğan IP jaýlar üşin, nemese IP jaý tüzetw/paýdalanwşı derekterin körsetw üşin jwıqtağı özgeristerdi qarap şığadı.
	Paýdalanwşılardı men tüzetwlerdi XFF IP arqılı IP jaýğa «/xff» degendi qosıp keltirwge boladı. IPv4 (CIDR 16-32) jäne IPv6 (CIDR 64-128) arqawlanadı.
	Orındawşılıq sebepterimen 5000 tüzetwden artıq qaýtarılmaýdı. Bunı erejelerge säýkes paýdalanıñız.',
	'checkuser-logcase'      => 'Jwrnaldan izdew ärip bas-kişiligin aýıradı.',
	'checkuser'              => 'Paýdalanwşını sınaw',
	'group-checkuser'        => 'Paýdalanwşı sınawşılar',
	'group-checkuser-member' => 'paýdalanwşı sınawşı',
	'grouppage-checkuser'    => '{{ns:project}}:Paýdalanwşını sınaw',
	'checkuser-reason'       => 'Sebebi',
	'checkuser-showlog'      => 'Jwrnaldı körset',
	'checkuser-log'          => 'Paýdalanwşını sınaw jwrnalı',
	'checkuser-query'        => 'Jwıqtağı özgeristerdi suranıstaw',
	'checkuser-target'       => 'Paýdalanwşı atı / IP jaý',
	'checkuser-users'        => 'Paýdalanwşılardı keltirw',
	'checkuser-edits'        => 'IP jaýdan jasalğan tüzetwlerdi keltirw',
	'checkuser-ips'          => 'IP jaýlardı keltirw',
	'checkuser-search'       => 'İzdew',
	'checkuser-empty'        => 'Jwrnalda eş jazba joq.',
	'checkuser-nomatch'      => 'Säýkes tabılmadı.',
	'checkuser-check'        => 'Sınaw',
	'checkuser-log-fail'     => 'Jwrnalğa jazba üstelinbedi',
	'checkuser-nolog'        => 'Jwrnal faýlı tabılmadı.',
	'checkuser-blocked'      => 'Buğattalğan',
);

$messages['kk-arab'] = array(
	'checkuser-summary'      => 'بۇل قۇرال پايدالانۋشى قولدانعان IP جايلار ٷشٸن, نەمەسە IP جاي تٷزەتۋ/پايدالانۋشى دەرەكتەرٸن كٶرسەتۋ ٷشٸن جۋىقتاعى ٶزگەرٸستەردٸ قاراپ شىعادى.
	پايدالانۋشىلاردى مەن تٷزەتۋلەردٸ XFF IP ارقىلى IP جايعا «/xff» دەگەندٸ قوسىپ كەلتٸرۋگە بولادى. IPv4 (CIDR 16-32) جٵنە IPv6 (CIDR 64-128) ارقاۋلانادى.
	ورىنداۋشىلىق سەبەپتەرٸمەن 5000 تٷزەتۋدەن ارتىق قايتارىلمايدى. بۇنى ەرەجەلەرگە سٵيكەس پايدالانىڭىز.',
	'checkuser-logcase'      => 'جۋرنالدان ٸزدەۋ ٵرٸپ باس-كٸشٸلٸگٸن ايىرادى.',
	'checkuser'              => 'پايدالانۋشىنى سىناۋ',
	'group-checkuser'        => 'پايدالانۋشى سىناۋشىلار',
	'group-checkuser-member' => 'پايدالانۋشى سىناۋشى',
	'grouppage-checkuser'    => '{{ns:project}}:پايدالانۋشىنى سىناۋ',
	'checkuser-reason'       => 'سەبەبٸ',
	'checkuser-showlog'      => 'جۋرنالدى كٶرسەت',
	'checkuser-log'          => 'پايدالانۋشىنى سىناۋ جۋرنالى',
	'checkuser-query'        => 'جۋىقتاعى ٶزگەرٸستەردٸ سۇرانىستاۋ',
	'checkuser-target'       => 'پايدالانۋشى اتى / IP جاي',
	'checkuser-users'        => 'پايدالانۋشىلاردى كەلتٸرۋ',
	'checkuser-edits'        => 'IP جايدان جاسالعان تٷزەتۋلەردٸ كەلتٸرۋ',
	'checkuser-ips'          => 'IP جايلاردى كەلتٸرۋ',
	'checkuser-search'       => 'ٸزدەۋ',
	'checkuser-empty'        => 'جۋرنالدا ەش جازبا جوق.',
	'checkuser-nomatch'      => 'سٵيكەس تابىلمادى.',
	'checkuser-check'        => 'سىناۋ',
	'checkuser-log-fail'     => 'جۋرنالعا جازبا ٷستەلٸنبەدٸ',
	'checkuser-nolog'        => 'جۋرنال فايلى تابىلمادى.',
	'checkuser-blocked'      => 'بۇعاتتالعان',
);

$messages['kn'] = array(
	'checkuser'              => 'ಸದಸ್ಯನನ್ನು ಚೆಕ್ ಮಾಡಿ',
);

$messages['la'] = array(
	'checkuser-reason'       => 'Causa',
	'checkuser-search'       => 'Quaerere',
);

/** Luxembourgish (Lëtzebuergesch)
 * @author Robby
 */
$messages['lb'] = array(
	'checkuser-logcase' => "D'Sich am Logbuch mecht en Ënnerscheed tëschent groussen a klenge Buchstawen (Caractèren).",
	'checkuser'         => 'Benotzer-Check',
	'checkuser-reason'  => 'Grond',
	'checkuser-showlog' => 'Logbuch weisen',
	'checkuser-target'  => 'Benotzer oder IP-Adress',
	'checkuser-search'  => 'Sichen',
	'checkuser-empty'   => 'Dëst Logbuch ass eidel.',
	'checkuser-blocked' => 'Gespaart',
);

$messages['lo'] = array(
	'checkuser'              => 'ກວດຜູ້ໃຊ້',
	'checkuser-reason'       => 'ເຫດຜົນ',
	'checkuser-showlog'      => 'ສະແດງບັນທຶກ',
	'checkuser-log'          => 'ບັນທຶກການກວດຜູ້ໃຊ້',
	'checkuser-target'       => 'ຜູ້ໃຊ້ ຫຼື IP',
	'checkuser-edits'        => 'ເອົາ ການດັດແກ້ ຈາກ ທີ່ຢູ່ IP',
	'checkuser-ips'          => 'ເອົາ ທີ່ຢູ່ IP',
	'checkuser-search'       => 'ຊອກຫາ',
	'checkuser-empty'        => 'ບໍ່ມີເນື້ອໃນຖືກບັນທຶກ',
	'checkuser-nomatch'      => 'ບໍ່ພົບສິ່ງທີ່ຊອກຫາ',
	'checkuser-check'        => 'ກວດ',
);

$messages['mk'] = array(
	'checkuser'              => 'Провери корисник',
);

$messages['myv'] = array(
	'checkuser-search'       => 'Вешнемс',
);

$messages['nap'] = array(
	'checkuser-search'       => 'Truova',
);

$messages['nds'] = array(
	'checkuser'              => 'Bruker nakieken',
	'group-checkuser'        => 'Brukers nakieken',
	'group-checkuser-member' => 'Bruker nakieken',
	'grouppage-checkuser'    => '{{ns:project}}:Checkuser',
);

$messages['nl'] = array(
	'checkuser-summary'      => 'Dit hulpmiddel bekijkt recente wijzigingen om IP-adressen die een gebruiker heeft gebruikt te achterhalen of toont de bewerkings- en gebruikersgegegevens voor een IP-adres.
	Gebruikers en bewerkingen van een IP-adres van een client kunnen achterhaald worden via XFF-haeders door "/xff" achter het IP-adres toe te voegen. IPv4 (CIDR 16-32) en IPv6 (CIDR 64-128) worden ondersteund.
	Om prestatieredenen worden niet meer dan 5.000 bewerkingen getoond. Gebruik dit hulpmiddel volgens het vastgestelde beleid.',
	'checkuser-logcase'      => 'Zoeken in het logboek is hoofdlettergevoelig.',
	'checkuser'              => 'Gebruiker controleren',
	'group-checkuser'        => 'Gebruikers controleren',
	'group-checkuser-member' => 'Gebruiker controleren',
	'grouppage-checkuser'    => '{{ns:project}}:Gebruiker controleren',
	'checkuser-reason'       => 'Reden',
	'checkuser-showlog'      => 'Toon logboek',
	'checkuser-log'          => 'Logboek controleren gebruikers',
	'checkuser-query'        => 'Bevraag recente wijzigingen',
	'checkuser-target'       => 'Gebruiker of IP-adres',
	'checkuser-users'        => 'Vraag gebruikers op',
	'checkuser-edits'        => 'Vraag bewerkingen van IP-adres op',
	'checkuser-ips'          => 'Vraag IP-adressen op',
	'checkuser-search'       => 'Zoeken',
	'checkuser-empty'        => 'Het logboek bevat geen regels.',
	'checkuser-nomatch'      => 'Geen overeenkomsten gevonden.',
	'checkuser-check'        => 'Controleer',
	'checkuser-log-fail'     => 'Logboekregel toevoegen niet mogelijk',
	'checkuser-nolog'        => 'Geen logboek gevonden.',
	'checkuser-blocked'      => 'Geblokkeerd',
	'checkuser-too-many'     => 'Te veel resultaten. Maak de IP-reeks kleiner:',
);

/** Norwegian (‪Norsk (bokmål)‬)
 * @author Jon Harald Søby
 */
$messages['no'] = array(
	'checkuser-summary'      => 'Dette verktøyet går gjennom siste endringer for å hente IP-ene som er brukt av en bruker, eller viser redigerings- eller brukerinformasjonen for en IP.

Brukere og redigeringer kan hentes med en XFF-IP ved å legge til «/xff» bak IP-en. IPv4 (CIDR 16-32) og IPv6 (CIDR 64-128) støttes.

Av ytelsesgrunner vises maksimalt 5000 redigeringer. Bruk dette verktøyet i samsvar med retningslinjer.',
	'checkuser-logcase'      => 'Loggsøket er sensitivt for store/små bokstaver.',
	'checkuser'              => 'Brukersjekk',
	'group-checkuser'        => 'IP-kontrollører',
	'group-checkuser-member' => 'IP-kontrollør',
	'grouppage-checkuser'    => '{{ns:project}}:IP-kontrollør',
	'checkuser-reason'       => 'Grunn',
	'checkuser-showlog'      => 'Vis logg',
	'checkuser-log'          => 'Brukersjekkingslogg',
	'checkuser-query'        => 'Søk i siste endringer',
	'checkuser-target'       => 'Bruker eller IP',
	'checkuser-users'        => 'Få brukere',
	'checkuser-edits'        => 'Få redigeringer fra IP',
	'checkuser-ips'          => 'Få IP-er',
	'checkuser-search'       => 'Søk',
	'checkuser-empty'        => 'Loggen inneholder ingen elementer.',
	'checkuser-nomatch'      => 'Ingen treff.',
	'checkuser-check'        => 'Sjekk',
	'checkuser-log-fail'     => 'Kunne ikke legge til loggelement.',
	'checkuser-nolog'        => 'Ingen loggfil funnet.',
	'checkuser-blocked'      => 'Blokkert',
	'checkuser-too-many'     => 'For mange resultater, vennligst innskrenk CIDR. Her er de brukte IP-ene (maks 5000, sortert etter adresse):',
);

/** Occitan (Occitan)
 * @author Cedric31
 */
$messages['oc'] = array(
	'checkuser-summary'      => "Aqueste esplech passa en revista los cambiaments recents per recercar l'IPS emplegada per un utilizaire, mostrar totas las edicions fachas per una IP, o per enumerar los utilizaires qu'an emplegat las IPs. Los utilizaires e las modificacions pòdon èsser trobatss amb una IP XFF se s'acaba amb « /xff ». IPv4 (CIDR 16-32) e IPv6(CIDR 64-128) son suportats. Emplegatz aquò segon las cadenas de caractèrs.",
	'checkuser-logcase'      => 'La recèrca dins lo Jornal es sensibla a la cassa.',
	'checkuser'              => 'Verificator d’utilizaire',
	'group-checkuser'        => 'Verificators d’utilizaire',
	'group-checkuser-member' => 'Verificator d’utilizaire',
	'grouppage-checkuser'    => '{{ns:project}}:Verificator d’utilizaire',
	'checkuser-reason'       => 'Explicacion',
	'checkuser-showlog'      => 'Mostrar la lista obtenguda',
	'checkuser-log'          => "Notacion de Verificator d'utilizaire",
	'checkuser-query'        => 'Recèrca pels darrièrs cambiaments',
	'checkuser-target'       => "Nom de l'utilizaire o IP",
	'checkuser-users'        => 'Obténer los utilizaires',
	'checkuser-edits'        => "Obténer las modificacions de l'IP",
	'checkuser-ips'          => 'Obténer las IPs',
	'checkuser-search'       => 'Recèrca',
	'checkuser-empty'        => "Lo jornal conten pas cap d'article",
	'checkuser-nomatch'      => 'Recèrcas infructuosas.',
	'checkuser-check'        => 'Recèrca',
	'checkuser-log-fail'     => "Incapaç d'ajustar la dintrada del jornal.",
	'checkuser-nolog'        => 'Cap de dintrada dins lo Jornal.',
	'checkuser-blocked'      => 'Blocat',
	'checkuser-too-many'     => 'Tròp de resultats. Limitatz la recerca sus las adreças IP :',

);

/** Polish (Polski)
 * @author Derbeth
 * @author Sp5uhe
 */
$messages['pl'] = array(
	'checkuser-summary'      => 'To narzędzie skanuje ostatnie zmiany by znaleźć adresy IP użyte przez użytkownika lub pokazać edycje/użytkowników dla adresu IP. Użytkownicy i edycje spod adresu IP mogą być pozyskane przez nagłówki XFF przez dodanie do IP "/xff". Obsługiwane są adresy IPv4 (CIDR 16-32) I IPv6 (CIDR 64-128). Ze względu na wydajność, zostanie zwróconych nie więcej niż 5000 edycji. Prosimy o używanie tej funkcji zgodnie z zasadami.',
	'checkuser-logcase'      => 'Szukanie w logu jest czułe na wielkość znaków',
	'checkuser'              => 'Sprawdzanie IP użytkownika',
	'group-checkuser'        => 'CheckUser',
	'group-checkuser-member' => 'CheckUser',
	'grouppage-checkuser'    => '{{ns:project}}:CheckUser',
	'checkuser-reason'       => 'Powód',
	'checkuser-showlog'      => 'Pokaż log',
	'checkuser-log'          => 'Log CheckUser',
	'checkuser-query'        => 'Przeanalizuj ostatnie zmiany',
	'checkuser-target'       => 'Użytkownik lub IP',
	'checkuser-users'        => 'Znajdź użytkowników',
	'checkuser-edits'        => 'Znajdź edycje z IP',
	'checkuser-ips'          => 'Znajdź adresy IP',
	'checkuser-search'       => 'Szukaj',
	'checkuser-empty'        => 'Log nie zawiera żadnych wpisów.',
	'checkuser-nomatch'      => 'Nie znaleziono niczego.',
	'checkuser-check'        => 'Log nie zawiera żadnych wpisów.',
	'checkuser-log-fail'     => 'Nie udało się dodać wpisu do logu.',
	'checkuser-nolog'        => 'Nie znaleziono pliku logu.',
	'checkuser-blocked'      => 'Zablokowany',
	'checkuser-too-many'     => 'Zbyt wiele wyników, proszę ogranicz CIDR. Użytych adresów IP jest (do 5000 posortowanych wg adresu):',
);

$messages['pms'] = array(
	'checkuser-summary'      => 'St\'utiss-sì as passa j\'ùltime modìfiche për tiré sù j\'adrësse IP dovra da n\'utent ò pura mostré lòn ch\'as fa da n\'adrëssa IP e che dat utent ch\'a l\'abia associà.
	J\'utent ch\'a dòvro n\'adrëssa IP e le modìfiche faite d\'ambelelì as peulo tiresse sù ën dovrand le testà XFF, për felo tache-ie dapress l\'adrëssa e "/xff". A travaja tant con la forma IPv4 (CIDR 16-32) che con cola IPv6 (CIDR 64-128).
	Për na question ëd caria ëd travaj a tira nen sù pì che 5000 modìfiche. A va dovrà comforma a ij deuit për ël process ëd contròl.',
	'checkuser-logcase'      => 'L\'arsërca ant ël registr a conta ëdcò maiùscole e minùscole.',
	'checkuser'              => 'Contròl dj\'utent',
	'group-checkuser'        => 'Controlor',
	'group-checkuser-member' => 'Controlor',
	'grouppage-checkuser'    => '{{ns:project}}:Contròl dj\'utent',
	'checkuser-reason'       => 'Rason',
	'checkuser-showlog'      => 'Smon ël registr',
	'checkuser-log'          => 'Registr dël contròl dj\'utent',
	'checkuser-query'        => 'Anterogassion dj\'ùltime modìfiche',
	'checkuser-target'       => 'Stranòm ò adrëssa IP',
	'checkuser-users'        => 'Tira sù j\'utent',
	'checkuser-edits'        => 'Tiré sù le modìfiche faite da na midema adrëssa IP',
	'checkuser-ips'          => 'Tiré sù j\'adrësse IP',
	'checkuser-search'       => 'Sërca',
	'checkuser-empty'        => 'Ës registr-sì a l\'é veujd.',
	'checkuser-nomatch'      => 'A-i é pa gnun-a ròba parej.',
	'checkuser-check'        => 'Contròl',
	'checkuser-log-fail'     => 'I-i la fom nen a gionte-ie na riga ant sël registr',
	'checkuser-nolog'        => 'Pa gnun registr ch\'a sia trovasse.',
	'checkuser-blocked'      => 'Blocà',
);
$messages['pt'] = array(
	'checkuser-summary'      => 'Esta ferramenta varre as Mudanças recentes para obter os endereços de IP de um utilizador ou para exibir os dados de edições/utilizadores para um IP.
	Utilizadores edições podem ser obtidos por um IP XFF colocando-se "/xff" no final do endereço. São suportados endereços IPv4 (CIDR 16-32) e IPv6 (CIDR 64-128).
	Não serão retornadas mais de 5000 edições por motivos de desempenho. O uso desta ferramenta deverá estar de acordo com as políticas.',
	'checkuser-logcase'      => 'As buscas nos registos são sensíveis a letras maiúsculas ou minúsculas.',
	'checkuser'              => 'Verificar utilizador',
	'group-checkuser'        => 'CheckUser',
	'group-checkuser-member' => 'CheckUser',
	'grouppage-checkuser'    => '{{ns:project}}:CheckUser',
	'checkuser-reason'       => 'Motivo',
	'checkuser-showlog'      => 'Exibir registos',
	'checkuser-log'          => 'Registos de verificação de utilizadores',
	'checkuser-query'        => 'Examinar as Mudanças recentes',
	'checkuser-target'       => 'Utilizador ou IP',
	'checkuser-users'        => 'Obter utilizadores',
	'checkuser-edits'        => 'Obter edições de IPs',
	'checkuser-ips'          => 'Obter IPs',
	'checkuser-search'       => 'Pesquisar',
	'checkuser-empty'        => 'O registo não contém itens.',
	'checkuser-nomatch'      => 'Não foram encontrados resultados.',
	'checkuser-check'        => 'Verificar',
	'checkuser-log-fail'     => 'Não foi possível adicionar entradas ao registo',
	'checkuser-nolog'        => 'Não foi encontrado um arquivo de registos.',
	'checkuser-blocked'      => 'Bloqueado',
	'checkuser-too-many'     => 'Demasiados resultados; por favor, restrinja o CIDR. Aqui estão os IPs usados (5000 no máx., ordenados por endereço):',
);
$messages['rm'] = array(
	'checkuser-reason'       => 'Motiv',
	'checkuser-search'       => 'Tschertgar',
);
$messages['ro'] = array(
	'checkuser'              => 'Verifică utilizatorul',
	'group-checkuser'        => 'Checkuseri',
	'group-checkuser-member' => 'Checkuser',
	'grouppage-checkuser'    => '{{ns:project}}:Checkuser',
);

/** Russian (Русский)
 * @author .:Ajvol:.
 */
$messages['ru'] = array(
	'checkuser-summary'      => "Данный инструмент может быть использован, чтобы получить IP-адреса, использовавшиеся участником, либо чтобы показать правки/участников, работавших с IP-адреса.
	Правки и пользователи, которые правили с опрделеннного IP-адреса, указанного в X-Forwarded-For, можно получить, добавив префикс <code>/xff</code> к IP-адресу. Поддерживаемые версии IP: 4 (CIDR 16—32) и 6 (CIDR 64—128).
	Из соображений производительности будут показаны только первые 5000 правок. Используйте эту страницу '''только в соответствии с правилами'''.",
	'checkuser-logcase'      => 'Поиск по журналу чувствителен к регистру.',
	'checkuser'              => 'Проверить участника',
	'group-checkuser'        => 'Проверяющие',
	'group-checkuser-member' => 'проверяющий',
	'grouppage-checkuser'    => '{{ns:project}}:Проверка участников',
	'checkuser-reason'       => 'Причина',
	'checkuser-showlog'      => 'Показать журнал',
	'checkuser-log'          => 'Журнал проверки участников',
	'checkuser-query'        => 'Запросить свежие правки',
	'checkuser-target'       => 'Пользователь или IP-адрес',
	'checkuser-users'        => 'Получить пользователей',
	'checkuser-edits'        => 'Запросить правки, сделанные с IP-адреса',
	'checkuser-ips'          => 'Запросить IP-адреса',
	'checkuser-search'       => 'Искать',
	'checkuser-empty'        => 'Журнал пуст.',
	'checkuser-nomatch'      => 'Совпадений не найдено.',
	'checkuser-check'        => 'Проверить',
	'checkuser-log-fail'     => 'Невозможно добавить запись в журнал',
	'checkuser-nolog'        => 'Файл журнала не найден.',
	'checkuser-blocked'      => 'Заблокирован',
	'checkuser-too-many'     => 'Слишком много результатов, пожалуйста, сузьте CIDR. Использованные IP (максимум 5000, отсортировано по адресу):',
);

/** Slovak (Slovenčina)
 * @author Helix84
 * @author Martin Kozák
 */
$messages['sk'] = array(
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
	'checkuser-blocked'      => 'Zablokovaný',
	'checkuser-too-many'     => 'Príliš veľa výsledkov, prosím zúžte CIDR. Tu sú použité IP (max. 5 000, zoradené podľa adresy):',
);

$messages['sq'] = array(
	'checkuser'              => 'Kontrollo përdoruesin',
);

/** Seeltersk (Seeltersk)
 * @author Pyt
 */
$messages['stq'] = array(
	'checkuser-summary'      => 'Disse Reewe truchsäkt do lääste Annerengen, uum ju IP-Adresse fon n Benutser
	blw. do Beoarbaidengen/Benutsernoomen foar ne IP-Adresse fäästtoustaalen. Benutsere un
Beoarbaidengen fon ne IP-Adresse konnen uk ätter Informatione uut do XFF-Headere
	oufräiged wäide, as an ju IP-Adresse n „/xff“ anhonged wäd. (CIDR 16-32) un IPv6 (CIDR 64-128) wäide unnerstutsed.
	Uut Perfomance-Gruunde wäide maximoal 5000 Beoarbaidengen uutroat. Benutsje CheckUser bloot in Uureenstämmenge mäd do Doatenschutsgjuchtlienjen.',
	'checkuser-logcase'      => 'Ju Säike in dät Logbouk unnerschat twiske Groot- un Littikschrieuwen.',
	'checkuser'              => 'Checkuser',
	'group-checkuser'        => 'Checkusers',
	'group-checkuser-member' => 'Checkuser-Begjuchtigde',
	'grouppage-checkuser'    => '{{ns:project}}:CheckUser',
	'checkuser-reason'       => 'Gruund',
	'checkuser-showlog'      => 'Logbouk anwiese',
	'checkuser-log'          => 'Checkuser-Logbouk',
	'checkuser-query'        => 'Lääste Annerengen oufräigje',
	'checkuser-target'       => 'Benutser of IP-Adresse',
	'checkuser-users'        => 'Hoal Benutsere',
	'checkuser-edits'        => 'Hoal Beoarbaidengen fon IP-Adresse',
	'checkuser-ips'          => 'Hoal IP-Adressen',
	'checkuser-search'       => 'Säike',
	'checkuser-empty'        => 'Dät Logbouk änthaalt neen Iendraage.',
	'checkuser-nomatch'      => 'Neen Uureenstämmengen fuunen.',
	'checkuser-check'        => 'Uutfiere',
	'checkuser-log-fail'     => 'Logbouk-Iendraach kon nit bietouföiged wäide.',
	'checkuser-nolog'        => 'Neen Logbouk fuunen.',
	'checkuser-blocked'      => 'speerd',
	'checkuser-too-many'     => 'Ju Lieste fon Resultoate is tou loang, gränsje dän IP-Beräk fääre ien. Hier sunt do benutsede IP-Adressen (maximoal 5000, sortierd ätter Adresse):',

);

$messages['sr-ec'] = array(
	'checkuser'              => 'Чекјузер',
	'group-checkuser'        => 'Чекјузери',
	'group-checkuser-member' => 'Чекјузер',
	'grouppage-checkuser'    => '{{ns:project}}:Чекјузер',
);

$messages['sr-el'] = array(
	'checkuser'              => 'Čekjuzer',
	'group-checkuser'        => 'Čekjuzeri',
	'group-checkuser-member' => 'Čekjuzer',
	'grouppage-checkuser'    => '{{ns:project}}:Čekjuzer',
);

$messages['sr'] = $messages['sr-ec'];

$messages['sv'] = array(
	'checkuser-summary'      => 'Det här verktyget söker igenom de senaste ändringarna för att hämta IP-adresser för en användare, eller redigeringar och användare för en IP-adress.
Användare och redigeringar kan visas med IP-adress från XFF genom att lägga till "/xff" efter IP-adressen. Verktyget stödjer IPv4 (CIDR 16-32) och IPv6 (CIDR 64-128).
På grund av prestandaskäl så visas inte mer än 5000 redigeringar. Använd verktyget i enlighet med policy.',
	'checkuser-logcase'      => 'Loggsökning är skiftlägeskänslig.',
	'checkuser'              => 'Kontrollera användare',
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
	'checkuser-nolog'        => 'Hittade ingen loggfil.',
	'checkuser-blocked'      => 'Blockerad',
);

$messages['tet'] = array(
	'checkuser-target'       => 'Uza-na\'in ka IP',
	'checkuser-search'       => 'Buka',
);

/** Tonga (faka-Tonga)
 * @author SPQRobin
 */
$messages['to'] = array(
	'checkuser'              => 'Siviʻi ʻa e ʻetita',
	'group-checkuser'        => 'Siviʻi kau ʻetita',
	'group-checkuser-member' => 'Siviʻi ʻa e ʻetita',
);

$messages['tr'] = array(
	'checkuser'              => 'IP denetçisi',
);

/** Volapük (Volapük)
 * @author Malafaya
 */
$messages['vo'] = array(
	'checkuser-reason' => 'Kod',
);

$messages['wa'] = array(
	'checkuser' => 'Verifyî l\' uzeu',
);

$messages['yue'] = array(
	'checkuser-summary'      => '呢個工具會響最近更改度掃瞄對一位用戶用過嘅IP地址，或者係睇一個IP嘅用戶資料同埋佢嘅編輯記錄。
	響用戶同埋用戶端IP嘅編輯係可幾經由XFF頭，加上 "/xff" 就可幾拎到。呢個工具係支援 IPv4 (CIDR 16-32) 同埋 IPv6 (CIDR 64-128)。
	由於為咗效能方面嘅原因，將唔會顯示多過5000次嘅編輯。請根源政策去用呢個工具。',
	'checkuser-logcase'      => '搵呢個日誌係有分大細楷嘅。',
	'checkuser'              => '核對用戶',
	'group-checkuser'        => '稽查員',
	'group-checkuser-member' => '稽查員',
	'grouppage-checkuser'    => '{{ns:project}}:稽查員',
	'checkuser-reason'       => '原因',
	'checkuser-showlog'      => '顯示日誌',
	'checkuser-log'          => '核對用戶日誌',
	'checkuser-query'        => '查詢最近更改',
	'checkuser-target'       => '用戶名或IP',
	'checkuser-users'        => '拎用戶',
	'checkuser-edits'        => '拎IP嘅編輯',
	'checkuser-ips'          => '拎IP',
	'checkuser-search'       => '搵',
	'checkuser-empty'        => '呢個日誌無任何嘅項目。',
	'checkuser-nomatch'      => '搵唔到符合嘅資訊。',
	'checkuser-check'        => '查',
	'checkuser-log-fail'     => '唔能夠加入日誌項目',
	'checkuser-nolog'        => '搵唔到日誌檔。',
	'checkuser-blocked'      => '已經封鎖',
);

$messages['zh-hans'] = array(
	'checkuser-summary'      => '本工具会从{{int:recentchanges}}中查询使用者使用过的IP位址，或是一个IP位址发送出来的任何编辑记录。本工具支持IPv4及IPv6的位址。由于技术上的限制，本工具只能查询最近5000笔的记录。请确定你的行为符合守则。',
	'checkuser-logcase'      => '搜寻时请注意大小写的区分',
	'checkuser'              => '核对用户',
	'group-checkuser'        => '账户核查',
	'group-checkuser-member' => '账户核查',
	'grouppage-checkuser'    => '{{ns:project}}:账户核查',
	'checkuser-reason'       => '理由',
	'checkuser-showlog'      => '显示日志',
	'checkuser-log'          => '用户查核日志',
	'checkuser-query'        => '查询最近更改',
	'checkuser-target'       => '用户名称或IP位扯',
	'checkuser-users'        => '查询用户名称',
	'checkuser-edits'        => '从IP位址查询编辑日志',
	'checkuser-ips'          => '查询IP位址',
	'checkuser-search'       => '搜寻',
	'checkuser-empty'        => '日志里没有资料。',
	'checkuser-nomatch'      => '没有符合的资讯',
	'checkuser-check'        => '查询',
	'checkuser-log-fail'     => '无法更新日志。',
	'checkuser-nolog'        => '找不到记录档',
	'checkuser-blocked'      => '已经查封',
);

$messages['zh-hant'] = array(
	'checkuser-summary'      => '本工具會從{{int:recentchanges}}中查詢使用者使用過的IP位址，或是一個IP位址發送出來的任何編輯記錄。本工具支援IPv4及IPv6的位址。由於技術上的限制，本工具只能查詢最近5000筆的記錄。請確定您的行為符合守則。',
	'checkuser-logcase'      => '搜尋時請注意大小寫的區分',
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
	'checkuser-nolog'        => '找不到記錄檔',
	'checkuser-blocked'      => '已經查封',
);

# Kazakh fallbacks
$messages['kk-kz'] = $messages['kk-cyrl'];
$messages['kk-tr'] = $messages['kk-latn'];
$messages['kk-cn'] = $messages['kk-arab'];
$messages['kk'] = $messages['kk-cyrl'];

# Chinese fallbacks
$messages['zh'] = $messages['zh-hans'];
$messages['zh-cn'] = $messages['zh-hans'];
$messages['zh-hk'] = $messages['zh-hant'];
$messages['zh-sg'] = $messages['zh-hans'];
$messages['zh-tw'] = $messages['zh-hant'];
$messages['zh-yue'] = $messages['yue'];
