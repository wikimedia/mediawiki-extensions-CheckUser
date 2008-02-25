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
	'checkuser-desc'         => 'Grants users with the appropriate permission the ability to check user\'s IP addresses and other information',
	'checkuser-logcase'      => 'The log search is case sensitive.',
	'checkuser'              => 'Check user',
	'group-checkuser'        => 'Check users',
	'group-checkuser-member' => 'Check user',
	'grouppage-checkuser'    => '{{ns:project}}:Check user',
	'checkuser-reason'       => 'Reason',
	'checkuser-showlog'      => 'Show log',
	'checkuser-log'          => 'CheckUser log',
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
	'checkuser-user-nonexistent' => 'The specified user does not exist.',
	'checkuser-search-form'  => 'Find log entries where the $1 is $2',
	'checkuser-search-submit'=> 'Search',
	'checkuser-search-initiator' => 'initiator',
	'checkuser-search-target' => 'target',
	'checkuser-log-subpage'  => 'Log',
	'checkuser-log-return'   => 'Return to CheckUser main form',

	'checkuser-log-userips'      => '$1 got IPs for $2', 
	'checkuser-log-ipedits'      => '$1 got edits for $2',
	'checkuser-log-ipusers'      => '$1 got users for $2', 
	'checkuser-log-ipedits-xff'  => '$1 got edits for XFF $2', 
	'checkuser-log-ipusers-xff'  => '$1 got users for XFF $2',
);

/** Message documentation (Message documentation)
 * @author Lejonel
 */
$messages['qqq'] = array(
	'checkuser' => 'Check user extension. The name of the special page were checkusers can check the IP addresses of users. The message is used in the list of special pages, and at the top of Special:Checkuser.',
);

/** Afrikaans (Afrikaans)
 * @author SPQRobin
 */
$messages['af'] = array(
	'checkuser-showlog'       => 'Wys logboek',
	'checkuser-search'        => 'Soek',
	'checkuser-search-submit' => 'Soek',
	'checkuser-log-subpage'   => 'Logboek',
);

$messages['ang'] = array(
	'checkuser-reason'       => 'Racu',
);

/** Arabic (العربية)
 * @author Meno25
 * @author Mido
 */
$messages['ar'] = array(
	'checkuser-summary'          => 'هذه الأداة تفحص أحدث التغييرات لاسترجاع الأيبيهات المستخدمة بواسطة مستخدم أو عرض بيانات التعديل/المستخدم لأيبي.
	المستخمون والتعديلات بواسطة أيبي عميل يمكن استرجاعها من خلال عناوين XFF عبر طرق الأيبي IP ب"/xff". IPv4 (CIDR 16-32) و IPv6 (CIDR 64-128) مدعومان.
	لا أكثر من 5000 تعديل سيتم عرضها لأسباب تتعلق بالأداء. استخدم هذا بالتوافق مع السياسة.',
	'checkuser-desc'             => 'يمنح المستخدمين بالسماح المطلوب القدرة على فحص عناوين الأيبي لمستخدم ما ومعلومات أخرى',
	'checkuser-logcase'          => 'بحث السجل حساس لحالة الحروف.',
	'checkuser'                  => 'تدقيق مستخدم',
	'group-checkuser'            => 'مدققو مستخدم',
	'group-checkuser-member'     => 'مدقق مستخدم',
	'grouppage-checkuser'        => '{{ns:project}}:تدقيق مستخدم',
	'checkuser-reason'           => 'السبب',
	'checkuser-showlog'          => 'عرض السجل',
	'checkuser-log'              => 'سجل تدقيق المستخدم',
	'checkuser-query'            => 'فحص أحدث التغييرات',
	'checkuser-target'           => 'مستخدم أو عنوان أيبي',
	'checkuser-users'            => 'عرض المستخدمين',
	'checkuser-edits'            => 'عرض التعديلات من الأيبي',
	'checkuser-ips'              => 'عرض الأيبيهات',
	'checkuser-search'           => 'بحث',
	'checkuser-empty'            => 'لا توجد مدخلات في السجل.',
	'checkuser-nomatch'          => 'لم يتم العثور على مدخلات مطابقة.',
	'checkuser-check'            => 'فحص',
	'checkuser-log-fail'         => 'غير قادر على إضافة مدخلة للسجل',
	'checkuser-nolog'            => 'لم يتم العثور على ملف سجل.',
	'checkuser-blocked'          => 'ممنوع',
	'checkuser-too-many'         => 'نتائج كثيرة جدا، من فضلك قم بتضييق عنوان الأيبي:',
	'checkuser-user-nonexistent' => 'المستخدم المحدد غير موجود.',
	'checkuser-search-form'      => 'اعثر على مدخلات السجل حيث $1 هو $2',
	'checkuser-search-submit'    => 'بحث',
	'checkuser-search-initiator' => 'باديء',
	'checkuser-search-target'    => 'هدف',
	'checkuser-log-subpage'      => 'سجل',
	'checkuser-log-return'       => 'ارجع إلى استمارة تدقيق المستخدم الرئيسية',
	'checkuser-log-userips'      => '$1 حصل على الأيبيهات ل $2',
	'checkuser-log-ipedits'      => '$1 حصل على التعديلات ل $2',
	'checkuser-log-ipusers'      => '$1 حصل على المستخدمين ل $2',
	'checkuser-log-ipedits-xff'  => '$1 حصل على التعديلات للإكس إف إف $2',
	'checkuser-log-ipusers-xff'  => '$1 حصل على المستخدمين للإكس إف إف $2',
);

/** Asturian (Asturianu)
 * @author Esbardu
 * @author SPQRobin
 */
$messages['ast'] = array(
	'checkuser-summary'          => "Esta ferramienta escanea los cambeos recientes pa obtener les IP usaes por un usuariu o p'amosar les ediciones o usuarios d'una IP.
	Los usuarios y ediciones correspondientes a una IP puen obtenese per aciu de les cabeceres XFF añadiendo depués de la IP \\\"/xff\\\". Puen usase los protocolos IPv4 (CIDR 16-32) y IPv6 (CIDR 64-128).
	Por razones de rendimientu nun s'amosarán más de 5.000 ediciones. Emplega esta ferramienta  acordies cola política d'usu.",
	'checkuser-desc'             => "Permite a los usuarios colos permisos afechiscos la posibilidá de comprobar les direiciones IP d'usuarios y otres informaciones",
	'checkuser-logcase'          => 'La busca nel rexistru distingue ente mayúscules y minúscules.',
	'checkuser'                  => "Comprobador d'usuariu",
	'group-checkuser'            => "Comprobadores d'usuariu",
	'group-checkuser-member'     => "Comprobador d'usuariu",
	'grouppage-checkuser'        => "{{ns:project}}:Comprobador d'usuariu",
	'checkuser-reason'           => 'Razón',
	'checkuser-showlog'          => 'Amosar el rexistru',
	'checkuser-log'              => "Rexistru de comprobadores d'usuariu",
	'checkuser-query'            => 'Buscar nos cambeos recientes',
	'checkuser-target'           => 'Usuariu o IP',
	'checkuser-users'            => 'Obtener usuarios',
	'checkuser-edits'            => 'Obtener les ediciones de la IP',
	'checkuser-ips'              => 'Obtener les IP',
	'checkuser-search'           => 'Buscar',
	'checkuser-empty'            => 'El rexistru nun tien nengún elementu.',
	'checkuser-nomatch'          => "Nun s'atoparon coincidencies.",
	'checkuser-check'            => 'Comprobar',
	'checkuser-log-fail'         => 'Nun se pue añader la entrada nel rexistru',
	'checkuser-nolog'            => "Nun s'atopó l'archivu del rexistru.",
	'checkuser-blocked'          => 'Bloquiáu',
	'checkuser-too-many'         => 'Demasiaos resultaos, por favor mengua la CIDR. Estes son les IP usaes (5.000 como máximo, ordenaes por direición):',
	'checkuser-user-nonexistent' => "L'usuariu especificáu nun esiste.",
	'checkuser-search-form'      => 'Atopar les entraes de rexistru onde $1 ye $2',
	'checkuser-search-submit'    => 'Buscar',
	'checkuser-search-initiator' => 'aniciador',
	'checkuser-search-target'    => 'oxetivu',
	'checkuser-log-subpage'      => 'Rexistru',
	'checkuser-log-return'       => "Volver al formulariu principal de comprobador d'usuariu",
	'checkuser-log-userips'      => '$1 obtuvo les IP pa $2',
	'checkuser-log-ipedits'      => '$1 obtuvo les ediciones pa $2',
	'checkuser-log-ipusers'      => '$1 obtuvo los usuarios pa $2',
	'checkuser-log-ipedits-xff'  => '41 obtuvo les ediciones pa XFF $2',
	'checkuser-log-ipusers-xff'  => '$1 obtuvo los usuarios pa XFF $2',
);

/** Kotava (Kotava)
 * @author Wikimistusik
 */
$messages['avk'] = array(
	'checkuser'               => 'Stujera va favesik',
	'group-checkuser'         => 'Stujera va favesik',
	'group-checkuser-member'  => 'Stujera va favesik',
	'grouppage-checkuser'     => '{{ns:project}}:Stujera va favesik',
	'checkuser-reason'        => 'Lazava',
	'checkuser-showlog'       => 'Nedira va "log"',
	'checkuser-target'        => 'Favesik ok IP mane',
	'checkuser-search'        => 'Aneyara',
	'checkuser-empty'         => '"Log" iyeltak tir vlardaf.',
	'checkuser-nomatch'       => 'Nedoy trasiks',
	'checkuser-check'         => 'Stujera',
	'checkuser-nolog'         => 'Mek trasiyin "log" iyeltak.',
	'checkuser-blocked'       => 'Elekan',
	'checkuser-search-submit' => 'Aneyara',
	'checkuser-search-target' => 'jala',
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

/** Bulgarian (Български)
 * @author DCLXVI
 */
$messages['bg'] = array(
	'checkuser-summary'          => 'Този инструмент сканира последните промени и извлича IP адресите, използвани от потребител или показва информацията за редакциите/потребителя за посоченото IP.
	Потребители и редакции по клиентско IP могат да бъдат извлечени чрез XFF headers като се добави IP с "/xff". Поддържат се IPv4 (CIDR 16-32) и IPv6 (CIDR 64-128).
	От съображения, свързани с производителността на уикито, ще бъдат показани не повече от 5000 редакции. Използвайте инструмента съобразно установената политика.',
	'checkuser-desc'             => 'Предоставя на потребители с подходящите права възможност за проверка на потребителски IP адреси и друга информация',
	'checkuser-logcase'          => 'Търсенето в дневника различава главни от малки букви.',
	'checkuser'                  => 'Проверяване на потребител',
	'group-checkuser'            => 'Проверяващи',
	'group-checkuser-member'     => 'Проверяващ',
	'grouppage-checkuser'        => '{{ns:project}}:Проверяващи',
	'checkuser-reason'           => 'Причина',
	'checkuser-showlog'          => 'Показване на дневника',
	'checkuser-log'              => 'Дневник на проверяващите',
	'checkuser-query'            => 'Заявка към последните промени',
	'checkuser-target'           => 'Потребител или IP',
	'checkuser-users'            => 'Извличане на потребители',
	'checkuser-edits'            => 'Извличане на редакции от IP',
	'checkuser-ips'              => 'Извличане на IP адреси',
	'checkuser-search'           => 'Търсене',
	'checkuser-empty'            => 'Дневникът не съдържа записи.',
	'checkuser-nomatch'          => 'Няма открити съвпадения.',
	'checkuser-check'            => 'Проверка',
	'checkuser-log-fail'         => 'Беше невъзможно да се добави запис в дневника',
	'checkuser-nolog'            => 'Не беше открит дневник.',
	'checkuser-blocked'          => 'Блокиран',
	'checkuser-too-many'         => 'Твърде много резултати. Показани са използваните IP адреси (най-много 5000, сортирани по адрес):',
	'checkuser-user-nonexistent' => 'Посоченият потребител не съществува.',
	'checkuser-search-submit'    => 'Търсене',
	'checkuser-search-target'    => 'цел',
	'checkuser-log-subpage'      => 'Дневник',
	'checkuser-log-return'       => 'Връщане към основния формуляр за проверка',
);

/** Bengali (বাংলা)
 * @author Bellayet
 * @author Zaheen
 */
$messages['bn'] = array(
	'checkuser-summary'          => 'এই সরঞ্জামটি সাম্প্রতিক পরিবর্তনসমূহ বিশ্লেষণ করে কোন ব্যবহারকারীর ব্যবহৃত আইপিগুলি নিয়ে আসে কিংবা কোন একটি আইপির জন্য সম্পাদনা/ব্যবহারকারী উপাত্ত প্রদর্শন করে।
কোন ক্লায়েন্ট আইপি-র জন্য ব্যবহারকারী ও সম্পাদনা XFF হেডারসমূহের সাহায্যে নিয়ে আসা যায়; এজন্য আইপির সাথে "/xff" যোগ করতে হয়।
IPv4 (CIDR 16-32) এবং IPv6 (CIDR 64-128) এই সরঞ্জামে সমর্থিত।
দক্ষতাজনিত কারণে ৫০০০-এর বেশি সম্পাদনা নিয়ে আসা হবে না। নীতিমালা মেনে এটি ব্যবহার করুন।',
	'checkuser-desc'             => 'যথাযথ অনুমোদনপ্রাপ্ত ব্যবহারকারীদেরকে অন্য ব্যবহারকারীদের আইপি ঠিকানা এবং অন্যান্য তথ্য পরীক্ষা করার ক্ষমতা দেয়',
	'checkuser-logcase'          => 'লগ অনুসন্ধান বড়/ছোট হাতের অক্ষরের উপর নির্ভরশীল',
	'checkuser'                  => 'ব্যবহারকারী পরীক্ষণ',
	'group-checkuser'            => 'ব্যবহারকারীসমূহ পরীক্ষণ',
	'group-checkuser-member'     => 'ব্যবহারকারী পরীক্ষণ',
	'grouppage-checkuser'        => '{{ns:project}}:ব্যবহারকারী পরীক্ষণ',
	'checkuser-reason'           => 'কারণ',
	'checkuser-showlog'          => 'লগ দেখাও',
	'checkuser-log'              => 'CheckUser লগ',
	'checkuser-query'            => 'সাম্প্রতিক পরিবর্তনসমূহ জানুন',
	'checkuser-target'           => 'ব্যবহারকারী অথবা আইপি',
	'checkuser-users'            => 'ব্যবহারকারী সমূহ পাওয়া যাবে',
	'checkuser-edits'            => 'আইপি থেকে সম্পাদনাসমূহ পাওয়া যাবে',
	'checkuser-ips'              => 'আইপি সমূহ পাওয়া যাবে',
	'checkuser-search'           => 'অনুসন্ধান',
	'checkuser-empty'            => 'এই লগে কিছুই নেই।',
	'checkuser-nomatch'          => 'এর সাথে মিলে এমন কিছু পাওয়া যায়নি।',
	'checkuser-check'            => 'পরীক্ষা করুন',
	'checkuser-log-fail'         => 'লগ ভুক্তিতে যোগ করা সম্ভব হচ্ছে না',
	'checkuser-nolog'            => 'কোন লগ ফাইল পাওয়া যায়নি।',
	'checkuser-blocked'          => 'বাধা দেওয়া হয়েছে',
	'checkuser-too-many'         => 'অত্যধিক সংখ্যক ফলাফল, অনুগ্রহ করে CIDR সীমিত করুন। নিচের আইপিগুলি ব্যবহৃত হয়েছে (সর্বোচ্চ ৫০০০, ঠিকানা অনুযায়ী বিন্যস্ত):',
	'checkuser-user-nonexistent' => 'এই নির্দিষ্ট ব্যবহারকারী নেই।',
	'checkuser-search-form'      => 'এমনসব লগ ভুক্তি খুঁজে বের করুন যেখানে $1 হল $2',
	'checkuser-search-submit'    => 'অনুসন্ধান',
	'checkuser-search-initiator' => 'আরম্ভকারী',
	'checkuser-search-target'    => 'লক্ষ্য',
	'checkuser-log-subpage'      => 'লগ',
	'checkuser-log-return'       => 'CheckUser মূল ফর্মে ফেরত যান',
	'checkuser-log-userips'      => '$2 এর জন্য $1 আইপি  সমূহ পেয়েছে',
	'checkuser-log-ipedits'      => '$2 এর জন্য $1 সম্পাদনাসমূহ পেয়েছে',
	'checkuser-log-ipusers'      => '$2 এর জন্য $1 ব্যবহারকারীসমূহ পেয়েছে',
	'checkuser-log-ipedits-xff'  => '$2 এর জন্য XFF $1 সম্পাদনাসমূহ পেয়েছে',
	'checkuser-log-ipusers-xff'  => '$2 এর জন্য XFF $1 ব্যবহারকারীসমূহ পেয়েছে',
);

/** Breton (Brezhoneg)
 * @author Fulup
 */
$messages['br'] = array(
	'checkuser'               => 'Gwiriañ an implijer',
	'group-checkuser'         => 'Gwiriañ an implijerien',
	'group-checkuser-member'  => 'Gwiriañ an implijer',
	'grouppage-checkuser'     => '{{ns:project}}:Gwiriañ an implijer',
	'checkuser-reason'        => 'Abeg',
	'checkuser-showlog'       => 'Diskouez ar marilh',
	'checkuser-search'        => 'Klask',
	'checkuser-check'         => 'Gwiriañ',
	'checkuser-blocked'       => 'Stanket',
	'checkuser-search-submit' => 'Klask',
);

/** Catalan (Català)
 * @author SMP
 * @author Toniher
 */
$messages['ca'] = array(
	'checkuser-summary'          => "Aquest instrument efectua una cerca als canvis recents per a comprovar les adreces IP fetes servir per un usuari o per a mostrar les edicions d'una certa adreça IP.
Les edicions i usuaris d'un client IP es poden obtenir via capçaleres XFF afegint /xff al final de la IP. Tant les adreces IPv4 (CIDR 16-32) com les IPv6 (CIDR 64-128) són admeses.
Per raons d'efectivitat i de memòria no es retornen més de 5000 edicions. Recordeu que aquesta eina només es pot usar d'acord amb les polítiques corresponents i amb respecte a la legislació sobre privacitat.",
	'checkuser-desc'             => "Permet als usuaris amb els permisos adients l'habilitat de comprovar les adreces IP que fan servir els usuaris enregistrats.",
	'checkuser-logcase'          => 'Les majúscules es tracten de manera diferenciada en la cerca dins el registre.',
	'checkuser'                  => "Comprova l'usuari",
	'group-checkuser'            => 'Checkusers',
	'group-checkuser-member'     => 'CheckUser',
	'grouppage-checkuser'        => '{{ns:project}}:Checkuser',
	'checkuser-reason'           => 'Motiu',
	'checkuser-showlog'          => 'Mostra registre',
	'checkuser-log'              => 'Registre de Checkuser',
	'checkuser-query'            => 'Cerca als canvis recents',
	'checkuser-target'           => 'Usuari o IP',
	'checkuser-users'            => 'Retorna els usuaris',
	'checkuser-edits'            => 'Retorna les edicions de la IP',
	'checkuser-ips'              => 'Retorna adreces IP',
	'checkuser-search'           => 'Cerca',
	'checkuser-empty'            => 'El registre no conté entrades.',
	'checkuser-nomatch'          => "No s'han trobat coincidències.",
	'checkuser-check'            => 'Comprova',
	'checkuser-log-fail'         => "No s'ha pogut afegir al registre",
	'checkuser-nolog'            => "No s'ha trobat el fitxer del registre.",
	'checkuser-blocked'          => 'Blocat',
	'checkuser-too-many'         => 'Hi ha massa resultats, cal que useu un CIDR més petit. Aquí teniu les IP usades (màx. 5000 ordenades per adreça):',
	'checkuser-user-nonexistent' => "L'usuari especificat no existeix.",
	'checkuser-search-form'      => 'Cerca entrades al registre on $1 és $2',
	'checkuser-search-submit'    => 'Cerca',
	'checkuser-search-initiator' => "l'iniciador",
	'checkuser-search-target'    => 'el consultat',
	'checkuser-log-subpage'      => 'Registre',
	'checkuser-log-return'       => 'Retorna al formulari de CheckUser',
	'checkuser-log-userips'      => '$1 consulta les IP de $2',
	'checkuser-log-ipedits'      => '$1 consulta les edicions de $2',
	'checkuser-log-ipusers'      => '$1 consulta els usuaris de $2',
	'checkuser-log-ipedits-xff'  => '$1 consulta les edicions del XFF $2',
	'checkuser-log-ipusers-xff'  => '$1 consulta els usuaris del XFF $2',
);

$messages['cdo'] = array(
	'checkuser-search'       => 'Sìng-tō̤',
);

/** Chechen (Нохчийн)
 * @author SPQRobin
 */
$messages['ce'] = array(
	'checkuser-target' => 'Юзер я IP-адрес',
);

$messages['co'] = array(
	'group-checkuser'        => 'Controllori',
	'group-checkuser-member' => 'Controllore',
	'grouppage-checkuser'    => '{{ns:project}}:Controllori',
);

/** Czech (Česky)
 * @author Li-sung
 * @author Beren
 */
$messages['cs'] = array(
	'checkuser-summary'          => 'Tento nástroj zkoumá poslední změny a umožňuje získat IP adresy uživatelů nebo zobrazit editace a uživatele z dané IP adresy.
Uživatele a editace z klientské IP adresy lze získat z hlaviček XFF přidáním „/xff“ k IP. Je podporováno  IPv4 (CIDR 16-32) a IPv6 (CIDR 64-128).
Z výkonnostních důvodů lze zobrazit maximálně 5000 editací. Používejte tento nástroj v souladu s pravidly.',
	'checkuser-desc'             => 'Poskytuje uživatelům s příslušným oprávněním možnost zjišťovat IP adresy uživatelů a další související informace',
	'checkuser-logcase'          => 'Hledání v záznamech rozlišuje velikosti písmen.',
	'checkuser'                  => 'Kontrola uživatele',
	'group-checkuser'            => 'Revizoři',
	'group-checkuser-member'     => 'Revizor',
	'grouppage-checkuser'        => '{{ns:project}}:Revize uživatele',
	'checkuser-reason'           => 'Důvod',
	'checkuser-showlog'          => 'Zobrazit záznamy',
	'checkuser-log'              => 'Kniha kontroly uživatelů',
	'checkuser-query'            => 'Dotaz na poslední změny',
	'checkuser-target'           => 'Uživatel nebo IP',
	'checkuser-users'            => 'Najít uživatele',
	'checkuser-edits'            => 'Najít editace z IP',
	'checkuser-ips'              => 'Najít IP adresy',
	'checkuser-search'           => 'Hledat',
	'checkuser-empty'            => 'Kniha neobsahuje žádné položky',
	'checkuser-nomatch'          => 'Nic odpovídajícího nebylo nalezeno.',
	'checkuser-check'            => 'Zkontrolovat',
	'checkuser-log-fail'         => 'Nepodařilo se zapsat do záznamů',
	'checkuser-nolog'            => 'Soubor záznamů nebyl nalezen.',
	'checkuser-blocked'          => 'zablokováno',
	'checkuser-too-many'         => 'Příliš mnoho výsledků, zkuste omezit CIDR. Níže jsou použité IP adresy (nejvýše 500, seřazené abecedně):',
	'checkuser-user-nonexistent' => 'Zadaný uživatel neexistuje.',
	'checkuser-search-form'      => 'Hledej záznamy, kde $1 je $2',
	'checkuser-search-submit'    => 'Hledat',
	'checkuser-search-initiator' => 'kontrolující',
	'checkuser-search-target'    => 'kontrolováno',
	'checkuser-log-subpage'      => 'Záznamy',
	'checkuser-log-return'       => 'Návrat na hlavní formulář Kontroly uživatele',
	'checkuser-log-userips'      => '$1 zjišťuje IP adresy uživatele $2',
	'checkuser-log-ipedits'      => '$1 zjišťuje editace z IP $2',
	'checkuser-log-ipusers'      => '$1 zjišťuje uživatele z IP $2',
	'checkuser-log-ipedits-xff'  => '$1 zjišťuje editace s XFF $2',
	'checkuser-log-ipusers-xff'  => '$1 zjišťuje uživatele s XFF $2',
);

/** Danish (Dansk)
 * @author M.M.S.
 */
$messages['da'] = array(
	'checkuser-search'        => 'Søg',
	'checkuser-search-submit' => 'Søg',
);

/** German (Deutsch)
 * @author Raimond Spekking
 */
$messages['de'] = array(
	'checkuser-summary'	     => 'Dieses Werkzeug durchsucht die letzten Änderungen, um die IP-Adressen eines Benutzers
	bzw. die Bearbeitungen/Benutzernamen für eine IP-Adresse zu ermitteln. Benutzer und Bearbeitungen einer IP-Adresse können auch nach Informationen aus den XFF-Headern
	abgefragt werden, indem der IP-Adresse ein „/xff“ angehängt wird. IPv4 (CIDR 16-32) und IPv6 (CIDR 64-128) werden unterstützt.
	Aus Performance-Gründen werden maximal 5000 Bearbeitungen ausgegeben. Benutze CheckUser ausschließlich in Übereinstimmung mit den Datenschutzrichtlinien.',
	'checkuser-desc'             => 'Erlaubt Benutzern mit den entsprechenden Rechten die IP-Adressen sowie weitere Informationen von Benutzern zu prüfen',
	'checkuser-logcase'	     => 'Die Suche im Logbuch unterscheidet zwischen Groß- und Kleinschreibung.',
	'checkuser'                  => 'Checkuser',
	'group-checkuser'            => 'Checkusers',
	'group-checkuser-member'     => 'Checkuser-Berechtigter',
	'grouppage-checkuser'        => '{{ns:project}}:CheckUser',
	'checkuser-reason'	     => 'Grund',
	'checkuser-showlog'	     => 'Logbuch anzeigen',
	'checkuser-log'		     => 'Checkuser-Logbuch',
	'checkuser-query'	     => 'Letzte Änderungen abfragen',
	'checkuser-target'	     => 'Benutzer oder IP-Adresse',
	'checkuser-users'	     => 'Hole Benutzer',
	'checkuser-edits'	     => 'Hole Bearbeitungen von IP-Adresse',
	'checkuser-ips'	  	     => 'Hole IP-Adressen',
	'checkuser-search'	     => 'Suchen',
	'checkuser-empty'	     => 'Das Logbuch enthält keine Einträge.',
	'checkuser-nomatch'	     => 'Keine Übereinstimmungen gefunden.',
	'checkuser-check'	     => 'Ausführen',
	'checkuser-log-fail'	     => 'Logbuch-Eintrag kann nicht hinzugefügt werden.',
	'checkuser-nolog'	     => 'Kein Logbuch vorhanden.',
	'checkuser-blocked'          => 'gesperrt',
	'checkuser-too-many'         => 'Die Ergebnisliste ist zu lang, bitte grenze den IP-Bereich weiter ein. Hier sind die benutzten IP-Adressen (maximal 5000, sortiert nach Adresse):',
	'checkuser-user-nonexistent' => 'Der angegebene Benutzer ist nicht vorhanden.',
	'checkuser-search-form'      => 'Suche Logbucheinträge, wo $1 $2 ist.',
	'checkuser-search-submit'    => 'Suche',
	'checkuser-search-initiator' => 'Initiator',
	'checkuser-search-target'    => 'Ziel',
	'checkuser-log-subpage'      => 'Logbuch',
	'checkuser-log-return'       => 'Zurück zum CheckUser-Hauptformular',

	'checkuser-log-userips'      => '$1 holte IP-Adressen für $2', 
	'checkuser-log-ipedits'      => '$1 holte Bearbeitungen für $2',
	'checkuser-log-ipusers'      => '$1 holte Benutzer für $2', 
	'checkuser-log-ipedits-xff'  => '$1 holte Bearbeitungen für XFF $2', 
	'checkuser-log-ipusers-xff'  => '$1 holte Benutzer für XFF $2',
);

/** Ewe (Eʋegbe)
 * @author M.M.S.
 */
$messages['ee'] = array(
	'checkuser-search'        => 'Dii',
	'checkuser-search-submit' => 'Dii',
);

/** Greek (Ελληνικά)
 * @author ZaDiak
 * @author Consta
 */
$messages['el'] = array(
	'checkuser-reason'        => 'Λόγος',
	'checkuser-target'        => 'Χρήστης ή IP',
	'checkuser-search'        => 'Αναζήτηση',
	'checkuser-nomatch'       => 'Δεν βρέθηκαν σχετικές σελίδες.',
	'checkuser-search-submit' => 'Αναζήτηση',
	'checkuser-search-target' => 'στόχος',
);

/** Esperanto (Esperanto)
 * @author Yekrats
 */
$messages['eo'] = array(
	'checkuser-reason' => 'Kialo',
);

/** Spanish (Español)
 * @author Spacebirdy
 * @author Dmcdevit
 * @author Lin linao
 */
$messages['es'] = array(
	'checkuser-summary'          => 'Esta herramienta explora los cambios recientes para obtener las IPs utilizadas por un usuario o para mostrar la información de ediciones/usuarios de una IP.
También se pueden obtener los usuarios y las ediciones de un cliente IP vía XFF añadiendo "/xff". IPv4 (CIDR 16-32) y IPv6 (CIDR 64-128) funcionan.
No se muestran más de 5000 ediciones por motivos de rendimiento. Usa esta herramienta en acuerdo con la ley orgánica de protección de datos.',
	'checkuser-logcase'          => 'El buscador de registros distingue entre mayúsculas y minúsculas.',
	'checkuser'                  => 'Verificador de usuarios',
	'group-checkuser'            => 'Verificadores de usuarios',
	'group-checkuser-member'     => 'Verificador de usuarios',
	'grouppage-checkuser'        => '{{ns:project}}:verificador de usuarios',
	'checkuser-reason'           => 'Motivo',
	'checkuser-showlog'          => 'Ver registro',
	'checkuser-log'              => 'Registro de CheckUser',
	'checkuser-query'            => 'Buscar en cambios recientes',
	'checkuser-target'           => 'Usuario o IP',
	'checkuser-users'            => 'Obtener usuarios',
	'checkuser-edits'            => 'Obtener ediciones de IP',
	'checkuser-ips'              => 'Obtener IPs',
	'checkuser-search'           => 'Buscar',
	'checkuser-empty'            => 'No hay elementos en el registro.',
	'checkuser-nomatch'          => 'No hay elementos en el registro con esas condiciones.',
	'checkuser-check'            => 'Examinar',
	'checkuser-log-fail'         => 'No se puede añadir este elemento al registro.',
	'checkuser-nolog'            => 'No se encuentra ningún archivo del registro',
	'checkuser-blocked'          => 'bloqueado',
	'checkuser-too-many'         => 'Hay demasiados resultados. Por favor limita el CIDR. Aquí se ven las IPs usadas (máximo 5000, ordenadas según dirección):',
	'checkuser-user-nonexistent' => 'El usuario especificado no existe.',
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

/** فارسی (فارسی)
 * @author Huji
 */
$messages['fa'] = array(
	'checkuser-summary'          => 'این ابزار تغییرات اخیر را برای به دست آوردن نشانی‌های اینترنتی (IP) استفاده شده توسط یک کاربر و یا تعیین ویرایش‌های انجام شده از طریق یک نشانی اینترنتی جستجو می‌کند.
کاربرها و ویرایش‌های مرتبط با یک نشانی اینترنتی را می‌توان با توجه به اطلاعات سرآیند XFF (با افزودن «‏‎/xff» به انتهای نشانی IP) پیدا کرد.
هر دو پروتکل IPv4 (معادل CIDR 16-32) و IPv6 (معادل CIDR 64-128) توسط این ابزار پشتیبانی می‌شوند.',
	'checkuser-desc'             => 'به کاربرها اختیارات لازم را برای بررسی نشانی اینترنتی کاربرها و اطلاعات دیگر می‌دهد',
	'checkuser-logcase'          => 'جستجوی سیاهه به کوچک یا بزرگ بودن حروف حساس است.',
	'checkuser'                  => 'بازرسی کاربر',
	'group-checkuser'            => 'بازرسان کاربر',
	'group-checkuser-member'     => 'بازرس کاربر',
	'grouppage-checkuser'        => '{{ns:project}}:بازرسی کاربر',
	'checkuser-reason'           => 'دلیل',
	'checkuser-showlog'          => 'نمایش سیاهه',
	'checkuser-log'              => 'سیاهه بازرسی کاربر',
	'checkuser-query'            => 'جستجوی تغییرات اخیر',
	'checkuser-target'           => 'کاربر یا نشانی اینترنتی',
	'checkuser-users'            => 'فهرست کردن کاربرها',
	'checkuser-edits'            => 'نمایش ویرایش‌های مربوط به این نشانی اینترنتی',
	'checkuser-ips'              => 'فهرست کردن نشانی‌های اینترنتی',
	'checkuser-search'           => 'جستجو',
	'checkuser-empty'            => 'سیاهه خالی است.',
	'checkuser-nomatch'          => 'موردی که مطابقت داشته باشد پیدا نشد.',
	'checkuser-check'            => 'بررسی',
	'checkuser-log-fail'         => 'امکان افزودن اطلاعات به سیاهه وجود ندارد',
	'checkuser-nolog'            => 'پرونده سیاهه پیدا نشد.',
	'checkuser-blocked'          => 'دسترسی قطع شد',
	'checkuser-too-many'         => 'تعداد نتایج بسیار زیاد است. لطفاً CIDR را باریک‌تر کنید. در زیر نشانی‌های اینترنتی استفاده شده را می‌بینید (حداثر ۵۰۰۰ مورد، به ترتیب نشانی):',
	'checkuser-user-nonexistent' => 'کاربر مورد نظر وجود ندارد.',
	'checkuser-search-form'      => 'پیدا کردن مواردی در سیاهه‌ها که $1 همان $2 است',
	'checkuser-search-submit'    => 'جستجو',
	'checkuser-search-initiator' => 'آغازگر',
	'checkuser-search-target'    => 'هدف',
	'checkuser-log-subpage'      => 'سیاهه',
	'checkuser-log-return'       => 'بازگشت به فرم اصلی بازرسی کاربر',
	'checkuser-log-userips'      => '$1 نشانی‌های اینترنتی $2 را گرفت',
	'checkuser-log-ipedits'      => '$1 ویرایش‌های $2 را گرفت',
	'checkuser-log-ipusers'      => '$1 کاربرهای مربوط به $2 را گرفت',
	'checkuser-log-ipedits-xff'  => '$1 ویرایش‌های XFF $2 را گرفت',
	'checkuser-log-ipusers-xff'  => '$1 کاربرهای مربوط به XFF $2 را گرفت',

);

/** Finnish (Suomi)
 * @author Crt
 * @author Cimon Avaro
 */
$messages['fi'] = array(
	'checkuser-summary'          => 'Tämän työkalun avulla voidaan tutkia tuoreet muutokset ja paljastaa käyttäjien IP-osoitteet tai noutaa IP-osoitteiden muokkaukset ja käyttäjätiedot.
	Käyttäjät ja muokkaukset voidaan hakea myös uudelleenohjausosoitteen (X-Forwarded-For) takaa käyttämällä IP-osoitteen perässä <tt>/xff</tt> -merkintää. Työkalu tukee sekä IPv4 (CIDR 16–32) ja IPv6 (CIDR 64–128) -standardeja.',
	'checkuser-logcase'          => 'Haku lokista on kirjainkokoriippuvainen.',
	'checkuser'                  => 'Osoitepaljastin',
	'group-checkuser'            => 'osoitepaljastimen käyttäjät',
	'group-checkuser-member'     => 'osoitepaljastimen käyttäjä',
	'grouppage-checkuser'        => '{{ns:project}}:Osoitepaljastin',
	'checkuser-reason'           => 'Syy',
	'checkuser-showlog'          => 'Näytä loki',
	'checkuser-log'              => 'Osoitepaljastinloki',
	'checkuser-query'            => 'Hae tuoreet muutokset',
	'checkuser-target'           => 'Käyttäjä tai IP-osoite',
	'checkuser-users'            => 'Hae käyttäjät',
	'checkuser-edits'            => 'Hae IP-osoitteen muokkaukset',
	'checkuser-ips'              => 'Hae IP-osoitteet',
	'checkuser-search'           => 'Etsi',
	'checkuser-empty'            => 'Ei lokitapahtumia.',
	'checkuser-nomatch'          => 'Hakuehtoihin sopivia tuloksia ei löytynyt.',
	'checkuser-check'            => 'Tarkasta',
	'checkuser-log-fail'         => 'Lokitapahtuman lisäys epäonnistui',
	'checkuser-nolog'            => 'Lokitiedostoa ei löytynyt.',
	'checkuser-blocked'          => 'Estetty',
	'checkuser-too-many'         => 'Liian monta tulosta, rajoita IP-osoitetta:',
	'checkuser-user-nonexistent' => 'Määritettyä käyttäjää ei ole olemassa.',
	'checkuser-search-submit'    => 'Hae',
);

$messages['fo'] = array(
	'checkuser'              => 'Rannsakanar brúkari',
	'group-checkuser'        => 'Rannsakanar brúkari',
	'group-checkuser-member' => 'Rannsakanar brúkarir',
	'grouppage-checkuser'    => '{{ns:project}}:Rannsakanar brúkari',
	'checkuser-search'       => 'Leita',
);

/** French (Français)
 * @author Grondin
 * @author Sherbrooke
 * @author ChrisPtDe
 */
$messages['fr'] = array(
	'checkuser-summary'          => 'Cet outil parcourt la liste des changements récents à la recherche de l’adresse IP employée par un utilisateur, affiche toutes les éditions d’une adresse IP (même enregistrée), ou liste les comptes utilisés par une adresse IP. Les comptes et les modifications peuvent être trouvés avec une IP XFF si elle finit avec « /xff ». Il est possible d’utiliser les protocoles IPv4 (CIDR 16-32) et IPv6 (CIDR 64-128). Le nombre d’éditions affichables est limité à {{formatnum:5000}} pour des questions de performance du serveur. Veuillez utiliser cet outil dans les limites de la charte d’utilisation.',
	'checkuser-desc'         => 'Donne la possibilité aux personnes dûment autorisées de vérifier les adresses IP des utilisateurs ainsi que d’autres informations les concernant',
	'checkuser-logcase'          => 'La recherche dans le journal est sensible à la casse.',
	'checkuser'                  => 'Vérificateur d’utilisateur',
	'group-checkuser'            => 'Vérificateurs d’utilisateur',
	'group-checkuser-member'     => 'Vérificateur d’utilisateur',
	'grouppage-checkuser'        => '{{ns:projet}}:Vérificateur d’utilisateur',
	'checkuser-reason'           => 'Motif',
	'checkuser-showlog'          => 'Afficher le journal',
	'checkuser-log'              => 'Journal de vérificateur d’utilisateur',
	'checkuser-query'            => 'Recherche par les changements récents',
	'checkuser-target'           => "Nom d'utilisateur ou adresse IP",
	'checkuser-users'            => 'Obtenir les utilisateurs',
	'checkuser-edits'            => 'Obtenir les modifications de l’adresse IP',
	'checkuser-ips'              => 'Obtenir les adresses IP',
	'checkuser-search'           => 'Recherche',
	'checkuser-empty'            => 'Le journal ne contient aucun article',
	'checkuser-nomatch'          => 'Recherches infructueuses.',
	'checkuser-check'            => 'Recherche',
	'checkuser-log-fail'         => 'Impossible d’ajouter l’entrée du journal.',
	'checkuser-nolog'            => 'Aucune entrée dans le journal',
	'checkuser-blocked'          => 'Bloqué',
	'checkuser-too-many'         => 'Trop de résultats. Veuillez limiter la recherche sur les adresses IP :',
	'checkuser-user-nonexistent' => "L’utilisateur indiqué n'existe pas",
	'checkuser-search-form'      => 'Chercher le journal des entrées où $1 est $2.',
	'checkuser-search-submit'    => 'Rechercher',
	'checkuser-search-initiator' => 'l’initiateur',
	'checkuser-search-target'    => 'la cible',
	'checkuser-log-subpage'      => 'Journal',
	'checkuser-log-return'       => "Retourner au formulaire principal de la vérification d'utilisateur",
	'checkuser-log-userips'      => '$1 a obtenu des IP pour $2',
	'checkuser-log-ipedits'      => '$1 a obtenu des modifications pour $2',
	'checkuser-log-ipusers'      => '$1 a obtenu des utilisateurs pour $2',
	'checkuser-log-ipedits-xff'  => '$1 a obtenu des modifications pour XFF  $2',
	'checkuser-log-ipusers-xff'  => '$1 a obtenu des utilisateurs pour XFF $2',
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
	'checkuser-summary'          => 'Ceti outil parcôrt la lista des dèrriérs changements a la rechèrche de l’adrèce IP empleyê per un utilisator, afiche totes les èdicions d’una adrèce IP (méma enregistrâ), ou ben liste los comptos utilisâs per una adrèce IP.
	Los comptos et les modificacions pôvont étre trovâs avouéc una IP XFF se sè chavone avouéc « /xff ». O est possiblo d’utilisar los protocolos IPv4 (CIDR 16-32) et IPv6 (CIDR 64-128).
	Lo nombro d’èdicions afichâbles est limitâ a {{formatnum:5000}} por des quèstions de pèrformence du sèrvior. Volyéd utilisar ceti outil dens les limites de la chârta d’usâjo.',
	'checkuser-desc'             => 'Balye la possibilitât a les gens qu’ont la pèrmission que vat avouéc de controlar les adrèces IP des utilisators et pués d’ôtres enformacions los regardent.',
	'checkuser-logcase'          => 'La rechèrche dens lo jornal est sensibla a la câssa.',
	'checkuser'                  => 'Controlor d’utilisator',
	'group-checkuser'            => 'Controlors d’utilisator',
	'group-checkuser-member'     => 'Controlor d’utilisator',
	'grouppage-checkuser'        => '{{ns:project}}:Controlors d’utilisator',
	'checkuser-reason'           => 'Rêson',
	'checkuser-showlog'          => 'Afichiér lo jornal',
	'checkuser-log'              => 'Jornal de controlor d’utilisator',
	'checkuser-query'            => 'Rechèrche per los dèrriérs changements',
	'checkuser-target'           => 'Nom d’utilisator ou adrèce IP',
	'checkuser-users'            => 'Obtegnir los utilisators',
	'checkuser-edits'            => 'Obtegnir les modificacions de l’adrèce IP',
	'checkuser-ips'              => 'Obtegnir les adrèces IP',
	'checkuser-search'           => 'Rechèrche',
	'checkuser-empty'            => 'Lo jornal contint gins d’articllo.',
	'checkuser-nomatch'          => 'Rechèrches que balyont ren.',
	'checkuser-check'            => 'Rechèrche',
	'checkuser-log-fail'         => 'Empossiblo d’apondre l’entrâ du jornal.',
	'checkuser-nolog'            => 'Niona entrâ dens lo jornal.',
	'checkuser-blocked'          => 'Blocâ',
	'checkuser-too-many'         => 'Trop de rèsultats. Volyéd limitar la rechèrche sur les adrèces IP :',
	'checkuser-user-nonexistent' => 'L’utilisator endicâ ègziste pas.',
	'checkuser-search-form'      => 'Chèrchiér lo jornal de les entrâs yô que $1 est $2.',
	'checkuser-search-submit'    => 'Rechèrchiér',
	'checkuser-search-initiator' => 'l’iniciator',
	'checkuser-search-target'    => 'la ciba',
	'checkuser-log-subpage'      => 'Jornal',
	'checkuser-log-return'       => 'Tornar u formulèro principâl du contrôlo d’utilisator',
	'checkuser-log-userips'      => '$1 at obtegnu des IP por $2',
	'checkuser-log-ipedits'      => '$1 at obtegnu des modificacions por $2',
	'checkuser-log-ipusers'      => '$1 at obtegnu des utilisators por $2',
	'checkuser-log-ipedits-xff'  => '$1 at obtegnu des modificacions por XFF $2',
	'checkuser-log-ipusers-xff'  => '$1 at obtegnu des utilisators por XFF $2',
);

/** Irish (Gaeilge)
 * @author Alison
 */
$messages['ga'] = array(
	'checkuser-summary'  => 'Scanann an uirlis seo na athruithe is déanaí chun na seolaidh IP úsáideoira a fháil ná taispeáin na sonraí eagarthóireachta/úsáideoira don seoladh IP.
Is féidir úsáideoirí agus eagarthóireachta mar IP cliant a fháil le ceanntáisc XFF mar an IP a iarcheangail le "/xff". IPv4 (CIDR 16-32) agus IPv6 (CIDR 64-128) atá tacaíocht.
Le fáth feidhmiúcháin, ní féidir níos mó ná 5000 eagarthóireachta a thabhairt ar ais ar an am cheana. Déan úsáid de réir polsaí.',
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

/** Galician (Galego)
 * @author Alma
 * @author Xosé
 */
$messages['gl'] = array(
	'checkuser-summary'          => 'Esta ferramenta analiza os cambios recentes para recuperar os enderezos IPs utilizados por un usuario ou amosar as edicións / datos do usuario dun enderezo de IP.
Os usuarios e as edicións por un cliente IP poden ser recuperados a través das cabeceiras XFF engadindo o enderezo IP con "/ xff". IPv4 (CIDR 16-32) e o IPv6 (CIDR 64-128) están soportadas.',
	'checkuser-logcase'          => 'O rexistro de búsqueda é sensíbel a maiúsculas e minúsculas.',
	'checkuser'                  => 'Verificador de usuarios',
	'group-checkuser'            => 'Verificadores de usuarios',
	'group-checkuser-member'     => 'Verificador usuarios',
	'grouppage-checkuser'        => '{{ns:project}}:Verificador de usuarios',
	'checkuser-reason'           => 'Razón',
	'checkuser-showlog'          => 'Amosar rexistro',
	'checkuser-log'              => 'Rexistro de verificador de usuarios',
	'checkuser-query'            => 'Consulta de cambios recentes',
	'checkuser-target'           => 'Usuario ou enderezo IP',
	'checkuser-users'            => 'Obter usuarios',
	'checkuser-edits'            => 'Obter edicións de enderezos IP',
	'checkuser-ips'              => 'Conseguir enderezos IPs',
	'checkuser-search'           => 'Buscar',
	'checkuser-empty'            => 'O rexistro non contén artigos.',
	'checkuser-nomatch'          => 'Non se atoparon coincidencias.',
	'checkuser-check'            => 'Comprobar',
	'checkuser-log-fail'         => 'Non é posíbel engadir unha entrada no rexistro',
	'checkuser-nolog'            => 'Ningún arquivo de rexistro.',
	'checkuser-blocked'          => 'Bloqueado',
	'checkuser-too-many'         => 'Hai demasiados resultados, restrinxa o enderezo IP:',
	'checkuser-user-nonexistent' => 'Non existe o usuario especificado.',
	'checkuser-search-form'      => 'Atopar entradas do rexistro nas que $1 é $2',
	'checkuser-search-submit'    => 'Procurar',
	'checkuser-search-initiator' => 'iniciador',
	'checkuser-search-target'    => 'destino',
	'checkuser-log-subpage'      => 'Rexistro',
	'checkuser-log-return'       => 'Voltar ao formulario principal de Verificador de Usuarios',
	'checkuser-log-userips'      => '$1 enderezos IPs obtidos para $2',
	'checkuser-log-ipedits'      => '$1 edicións obtidas para $2',
	'checkuser-log-ipusers'      => '$1 usuarios obtidos para $2',
	'checkuser-log-ipedits-xff'  => '$1 edicións obtidas para XFF $2',
	'checkuser-log-ipusers-xff'  => '$1 usuarios obtidos para XFF $2',
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
	'checkuser-summary'          => 'כלי זה סורק את השינויים האחרונים במטרה למצוא את כתובות ה־IP שהשתמש בהן משתמש מסוים או כדי להציג את כל המידע על המשתמשים שהשתמשו בכתובת IP ועל העריכות שבוצעו ממנה.
	ניתן לקבל עריכות ומשתמשים מכתובות IP של הכותרת X-Forwarded-For באמצעות הוספת הטקסט "/xff" לסוף הכתובת. הן כתובות IPv4 (כלומר, CIDR 16-32) והן כתובות IPv6 (כלומר, CIDR 64-128) נתמכות.
	לא יוחזרו יותר מ־5000 עריכות מסיבות של עומס על השרתים. אנא השתמשו בכלי זה בהתאם למדיניות.',
	'checkuser-logcase'          => 'החיפוש ביומנים הוא תלוי־רישיות.',
	'checkuser'                  => 'בדיקת משתמש',
	'group-checkuser'            => 'בודקים',
	'group-checkuser-member'     => 'בודק',
	'grouppage-checkuser'        => 'Project:בודק',
	'checkuser-reason'           => 'סיבה',
	'checkuser-showlog'          => 'הצגת יומן',
	'checkuser-log'              => 'יומן בדיקות',
	'checkuser-query'            => 'בדיקת שינויים אחרונים',
	'checkuser-target'           => 'שם משתמש או כתובת IP',
	'checkuser-users'            => 'הצגת משתמשים',
	'checkuser-edits'            => 'הצגת עריכות מכתובת IP מסוימת',
	'checkuser-ips'              => 'הצגת כתובות IP',
	'checkuser-search'           => 'חיפוש',
	'checkuser-empty'            => 'אין פריטים ביומן.',
	'checkuser-nomatch'          => 'לא נמצאו התאמות.',
	'checkuser-check'            => 'בדיקה',
	'checkuser-log-fail'         => 'לא ניתן היה להוסיף פריט ליומן',
	'checkuser-nolog'            => 'לא נמצא קובץ יומן.',
	'checkuser-blocked'          => 'חסום',
	'checkuser-too-many'         => 'נמצאו תוצאות רבות מדי, אנא צמצו את טווח כתובות ה־IP. להלן כתובת ה־IP שנעשה בהן שימוש (מוצגות 5,000 לכל היותר, וממוינות):',
	'checkuser-user-nonexistent' => 'המשתמש לא נמצא.',
	'checkuser-search-form'      => 'מציאת ערכים ביומן שבהם ה$1 הוא $2',
	'checkuser-search-submit'    => 'חיפוש',
	'checkuser-search-initiator' => 'בודק',
	'checkuser-search-target'    => 'נבדק',
	'checkuser-log-subpage'      => 'יומן',
	'checkuser-log-return'       => 'חזרה לטופס הבדיקה הכללי',

	'checkuser-log-userips'     => '$1 בדק את כתובות ה־IP של $2',
	'checkuser-log-ipedits'     => '$1 בדק את העריכות של $2',
	'checkuser-log-ipusers'     => '$1 בדק את המשתמשים של $2',
	'checkuser-log-ipedits-xff' => '$1 בדק את העריכות של כתובת ה־XFF $2',
	'checkuser-log-ipusers-xff' => '$1 בדק את המשתמשים של כתובת ה־XFF $2',
);

/** Croatian (Hrvatski)
 * @author SpeedyGonsales
 */
$messages['hr'] = array(
	'checkuser-summary'          => 'Ovaj alat pretražuje nedavne promjene i pronalazi IP adrese suradnika ili prikazuje uređivanja/ime suradnika ako je zadana IP adresa. Suradnici i uređivanja mogu biti dobiveni po XFF zaglavljima dodavanjem "/xff" na kraj IP adrese. Podržane su IPv4 (CIDR 16-32) i IPv6 (CIDR 64-128) adrese. Rezultat ima maksimalno 5.000 zapisa iz tehničkih razloga. Rabite ovaj alat u skladu s pravilima.',
	'checkuser-logcase'          => 'Provjera evidencije razlikuje velika i mala slova',
	'checkuser'                  => 'Provjeri suradnika',
	'group-checkuser'            => 'Check users',
	'group-checkuser-member'     => 'Check user',
	'grouppage-checkuser'        => '{{ns:project}}:Checkuser',
	'checkuser-reason'           => 'Razlog',
	'checkuser-showlog'          => 'Pokaži evidenciju',
	'checkuser-log'              => 'Checkuser evidencija',
	'checkuser-query'            => 'Provjeri nedavne promjene',
	'checkuser-target'           => 'Suradnik ili IP',
	'checkuser-users'            => 'suradničko ime',
	'checkuser-edits'            => 'uređivanja tog IP-a',
	'checkuser-ips'              => 'Nađi IP adrese',
	'checkuser-search'           => 'Traži',
	'checkuser-empty'            => 'Evidencija je prazna.',
	'checkuser-nomatch'          => 'Nema suradnika s tom IP adresom.',
	'checkuser-check'            => 'Provjeri',
	'checkuser-log-fail'         => 'Ne mogu dodati zapis',
	'checkuser-nolog'            => 'Evidencijska datoteka nije nađena',
	'checkuser-blocked'          => 'Blokiran',
	'checkuser-too-many'         => 'Previše rezultata, molimo suzite opseg (CIDR). Slijede rabljene IP adrese (najviše njih 5000, poredano abecedno):',
	'checkuser-user-nonexistent' => 'Traženi suradnik (suradničko ime) ne postoji.',
	'checkuser-search-form'      => 'Nađi zapise u evidenciji gdje $1 je $2',
	'checkuser-search-submit'    => 'Traži',
	'checkuser-search-initiator' => 'pokretač',
	'checkuser-search-target'    => 'cilj (traženi pojam)',
	'checkuser-log-subpage'      => 'Evidencija',
	'checkuser-log-return'       => 'Vrati se na glavnu formu za provjeru',
	'checkuser-log-userips'      => '$1 tražio je IP adrese suradnika $2',
	'checkuser-log-ipedits'      => '$1 tražio je uređivanja suradnika $2',
	'checkuser-log-ipusers'      => '$1 tražio je suradnička imena za IP adresu $2',
	'checkuser-log-ipedits-xff'  => '$1 tražio je uređivanja za XFF $2',
	'checkuser-log-ipusers-xff'  => '$1 tražio je imena suradnika za XFF $2',
);

/** Upper Sorbian (Hornjoserbsce)
 * @author Michawiki
 */
$messages['hsb'] = array(
	'checkuser-summary'          => 'Tutón nastroj přepytuje aktualne změny, zo by IP-adresy wužiwarja zwěsćił abo změny abo wužiwarske daty za IP pokazał.
Wužiwarjo a změny IP-adresy dadźa so přez XFF-hłowy wotwołać, připowěšo "/xff" na IP-adresu. IPv4 (CIDR 16-32) a IPv6 (CIDR 64-128) so podpěrujetej.',
	'checkuser-desc'             => 'Dawa wužiwarjam z trěbnym prawom móžnosć IP-adresy a druhe informacije wužiwarja kontrolować',
	'checkuser-logcase'          => 'Pytanje w protokolu rozeznawa mjez wulko- a małopisanjom.',
	'checkuser'                  => 'Wužiwarja kontrolować',
	'group-checkuser'            => 'Kontrolerojo',
	'group-checkuser-member'     => 'Kontroler',
	'grouppage-checkuser'        => '{{ns:project}}:Checkuser',
	'checkuser-reason'           => 'Přičina',
	'checkuser-showlog'          => 'Protokol pokazać',
	'checkuser-log'              => 'Protokol wužiwarskeje kontrole',
	'checkuser-query'            => 'Poslednje změny wotprašeć',
	'checkuser-target'           => 'Wužiwar abo IP-adresa',
	'checkuser-users'            => 'Wužiwarjow pokazać',
	'checkuser-edits'            => 'Změny z IP-adresy přinjesć',
	'checkuser-ips'              => 'IP-adresy pokazać',
	'checkuser-search'           => 'Pytać',
	'checkuser-empty'            => 'Protokol njewobsahuje zapiski.',
	'checkuser-nomatch'          => 'Žane wotpowědniki namakane.',
	'checkuser-check'            => 'Pruwować',
	'checkuser-log-fail'         => 'Njemóžno protokolowy zapisk přidać.',
	'checkuser-nolog'            => 'Žadyn protokol namakany.',
	'checkuser-blocked'          => 'Zablokowany',
	'checkuser-too-many'         => 'Přewjele wuslědkow, prošu zamjezuj IP-adresu:',
	'checkuser-user-nonexistent' => 'Podaty wužiwar njeeksistuje.',
	'checkuser-search-form'      => 'Protokolowe zapiski namakać, hdźež $1 je $2',
	'checkuser-search-submit'    => 'Pytać',
	'checkuser-search-initiator' => 'iniciator',
	'checkuser-search-target'    => 'cil',
	'checkuser-log-subpage'      => 'Protokol',
	'checkuser-log-return'       => 'Wróćo k hłownemu formularej CheckUser',
	'checkuser-log-userips'      => '$1 dósta IP za $2',
	'checkuser-log-ipedits'      => '$1 dósta změny za $2',
	'checkuser-log-ipusers'      => '$1 dósta wužiwarjow za $2',
	'checkuser-log-ipedits-xff'  => '$1 dósta změny za XFF $2',
	'checkuser-log-ipusers-xff'  => '$1 dósta wužiwarjow za XFF $2',
);

/** Hungarian (Magyar)
 * @author Bdanee
 * @author KossuthRad
 * @author Dorgan
 */
$messages['hu'] = array(
	'checkuser-summary'          => 'Ez az eszköz végigvizsgálja a friss változásokat, hogy lekérje egy adott felhasználó IP-címeit vagy megjelenítse egy adott IP-címet használó szerkesztőket és az IP szerkesztéseit.
Egy kliens IP-cím által végzett szerkesztések és felhasználói XFF fejlécek segítségével kérhetőek le, az IP-cím utáni „/xff” parancssal. Az IPv4 (CIDR 16-32) és az IPv6 (CIDR 64-128) is támogatott.
Maximum 5000 szerkesztés fog megjelenni teljesítményi okok miatt. Az eszközt a szabályoknak megfelelően használd.',
	'checkuser-desc'             => 'Lehetővé teszi olyan felhasználói jogok kiosztását, mely segítségével megtekinthetőek a felhasználók IP-címei és más adatok',
	'checkuser-logcase'          => 'A kereső kis- és nagybetűérzékeny.',
	'checkuser'                  => 'IP-ellenőr',
	'group-checkuser'            => 'IP-ellenőrök',
	'group-checkuser-member'     => 'IP-ellenőr',
	'grouppage-checkuser'        => '{{ns:project}}:IP-ellenőrök',
	'checkuser-reason'           => 'Ok',
	'checkuser-showlog'          => 'Napló megjelenítése',
	'checkuser-log'              => 'IP-ellenőr-napló',
	'checkuser-query'            => 'Kétséges aktuális változások',
	'checkuser-target'           => 'Felhasználó vagy IP-cím',
	'checkuser-users'            => 'Felhasználók keresése',
	'checkuser-edits'            => 'Szerkesztések keresése IP-cím alapján',
	'checkuser-ips'              => 'IP-címek keresése',
	'checkuser-search'           => 'Keresés',
	'checkuser-empty'            => 'A napló nem tartalmaz elemeket.',
	'checkuser-nomatch'          => 'A párja nem található.',
	'checkuser-check'            => 'Ellenőrzés',
	'checkuser-log-fail'         => 'Nem sikerült az elem hozzáadása',
	'checkuser-nolog'            => 'A naplófájl nem található.',
	'checkuser-blocked'          => 'Blokkolva',
	'checkuser-too-many'         => 'Túl sok eredmény, kérlek szűkítsd le a CIDR-t. Itt vannak a használt IP-címek (maximum 5000, cím alapján rendezve):',
	'checkuser-user-nonexistent' => 'A megadott szerkesztő nem létezik.',
	'checkuser-search-form'      => 'Naplóbejegyzések keresése, ahol $1 $2',
	'checkuser-search-submit'    => 'Keresés',
	'checkuser-search-initiator' => 'kezdeményező',
	'checkuser-search-target'    => 'Cél',
	'checkuser-log-subpage'      => 'IP-ellenőrzési napló',
	'checkuser-log-return'       => 'Vissza az IP-ellenőri oldalra',
	'checkuser-log-userips'      => '$1 lekérte $2 IP-címeit',
	'checkuser-log-ipedits'      => '$1 lekérte $2 szerkesztéseit',
	'checkuser-log-ipusers'      => '$1 lekérte a(z) $2 IP-címhez tarzozó szerkesztőket',
	'checkuser-log-ipedits-xff'  => '$ lekérte XFF $2 szerkesztéseit',
	'checkuser-log-ipusers-xff'  => '$ lekérte XFF $2 szerkesztőit',
);

/** Indonesian (Bahasa Indonesia)
 * @author Borgx
 */
$messages['id'] = array(
	'checkuser-summary'          => 'Peralatan ini memindai perubahan terbaru untuk mendapatkan IP yang digunakan oleh seorang pengguna atau menunjukkan data suntingan/pengguna untuk suatu IP.
	Pengguna dan suntingan dapat diperoleh dari suatu IP XFF dengan menambahkan "/xff" pada suatu IP. IPv4 (CIDR 16-32) dan IPv6 (CIDR 64-128) dapat digunakan.
	Karena alasan kinerja, maksimum hanya 5000 suntingan yang dapat diambil. Harap gunakan peralatan ini sesuai dengan kebijakan yang ada.',
	'checkuser-logcase'          => 'Log ini bersifat sensitif terhadap kapitalisasi.',
	'checkuser'                  => 'Pemeriksaan pengguna',
	'group-checkuser'            => 'Pemeriksa',
	'group-checkuser-member'     => 'Pemeriksa',
	'grouppage-checkuser'        => '{{ns:project}}:Pemeriksa',
	'checkuser-reason'           => 'Alasan',
	'checkuser-showlog'          => 'Tampilkan log',
	'checkuser-log'              => 'Log pemeriksaan pengguna',
	'checkuser-query'            => 'Kueri perubahan terbaru',
	'checkuser-target'           => 'Pengguna atau IP',
	'checkuser-users'            => 'Cari pengguna',
	'checkuser-edits'            => 'Cari suntingan dari IP',
	'checkuser-ips'              => 'Cari IP',
	'checkuser-search'           => 'Cari',
	'checkuser-empty'            => 'Log kosong.',
	'checkuser-nomatch'          => 'Data yang sesuai tidak ditemukan.',
	'checkuser-check'            => 'Periksa',
	'checkuser-log-fail'         => 'Entri log tidak dapat ditambahkan',
	'checkuser-nolog'            => 'Berkas log tidak ditemukan.',
	'checkuser-blocked'          => 'Diblok',
	'checkuser-too-many'         => 'Terlalu banyak hasil pencarian, mohon persempit CIDR. Berikut adalah alamat-alamat IP yang digunakan (5000 maks, diurut berdasarkan alamat):',
	'checkuser-user-nonexistent' => 'Pengguna tidak eksis',
	'checkuser-search-form'      => 'Cari catatan log dimana $1 adalah $2',
	'checkuser-search-submit'    => 'Cari',
	'checkuser-search-initiator' => 'pemeriksa',
	'checkuser-search-target'    => 'target',
	'checkuser-log-return'       => 'Kembali ke halaman utama Pemeriksa',
	'checkuser-log-userips'      => '$1 melihat IP dari $2',
	'checkuser-log-ipedits'      => '$1 melihat suntingan dari $2',
	'checkuser-log-ipusers'      => '$1 melihat nama pengguna dari $2',
	'checkuser-log-ipedits-xff'  => '$1 melihat suntingan dari XFF $2',
	'checkuser-log-ipusers-xff'  => '$1 melihat nama pengguna dari XFF $2',
);

/** Icelandic (Íslenska)
 * @author S.Örvarr.S
 * @author SPQRobin
 * @author Spacebirdy
 * @author Jóna Þórunn
 */
$messages['is'] = array(
	'checkuser'               => 'Skoða notanda',
	'group-checkuser'         => 'Athuga notendur',
	'group-checkuser-member'  => 'Athuga notanda',
	'checkuser-reason'        => 'Ástæða',
	'checkuser-showlog'       => 'Sýna skrá',
	'checkuser-query'         => 'Sækja nýlegar breytingar',
	'checkuser-target'        => 'Notandi eða vistfang',
	'checkuser-users'         => 'Sækja notendur',
	'checkuser-edits'         => 'Sækja breytingar eftir vistang',
	'checkuser-ips'           => 'Sækja vistföng',
	'checkuser-search'        => 'Leita',
	'checkuser-nomatch'       => 'Engar niðurstöður fundust.',
	'checkuser-check'         => 'Athuga',
	'checkuser-nolog'         => 'Engin skrá fundin.',
	'checkuser-blocked'       => 'Bannaður',
	'checkuser-search-submit' => 'Leita',
	'checkuser-log-subpage'   => 'Skrá',
);

/** Italian (Italiano)
 * @author BrokenArrow
 * @author Gianfranco
 * @author .anaconda
 */
$messages['it'] = array(
	'checkuser-summary'          => 'Questo strumento analizza le modifiche recenti per recuperare gli indirizzi IP utilizzati da un utente o mostrare contributi e dati di un IP. Utenti e contributi di un client IP possono essere rintracciati attraverso gli header XFF aggiungendo all\'IP il suffisso "/xff". Sono supportati IPv4 (CIDR 16-32) e IPv6 (CIDR 64-128). Non saranno restituite più di 5.000 modifiche, per ragioni di prestazioni. Usa questo strumento in stretta conformità alle policy.',
	'checkuser-desc'             => 'Consente agli utenti con le opportune autorizzazioni di sottoporre a verifica gli indirizzi IP e altre informazioni relative agli utenti',
	'checkuser-logcase'          => "La ricerca nei log è ''case sensitive'' (distingue fra maiuscole e minuscole).",
	'checkuser'                  => 'Controllo utenze',
	'group-checkuser'            => 'Controllori',
	'group-checkuser-member'     => 'Controllore',
	'grouppage-checkuser'        => '{{ns:project}}:Controllo utenze',
	'checkuser-reason'           => 'Motivazione',
	'checkuser-showlog'          => 'Mostra il log',
	'checkuser-log'              => 'Log dei checkuser',
	'checkuser-query'            => 'Cerca nelle ultime modifiche',
	'checkuser-target'           => 'Utente o IP',
	'checkuser-users'            => 'Cerca utenti',
	'checkuser-edits'            => 'Vedi i contributi degli IP',
	'checkuser-ips'              => 'Cerca IP',
	'checkuser-search'           => 'Cerca',
	'checkuser-empty'            => 'Il log non contiene dati.',
	'checkuser-nomatch'          => 'Nessun risultato trovato.',
	'checkuser-check'            => 'Controlla',
	'checkuser-log-fail'         => 'Impossibile aggiungere la voce al log',
	'checkuser-nolog'            => 'Non è stato trovato alcun file di log.',
	'checkuser-blocked'          => 'Bloccato',
	'checkuser-too-many'         => 'Il numero di risultati è eccessivo, usare un CIDR più ristretto. Di seguito sono indicati gli indirizzi IP utilizzati (fino a un massimo di 5000, ordinati per indirizzo):',
	'checkuser-user-nonexistent' => "L'utente indicato non esiste.",
	'checkuser-search-form'      => 'Trova le voci del log per le quali $1 è $2',
	'checkuser-search-submit'    => 'Ricerca',
	'checkuser-search-initiator' => 'iniziatore',
	'checkuser-search-target'    => 'obiettivo',
	'checkuser-log-subpage'      => 'Log',
	'checkuser-log-return'       => 'Torna al modulo principale di Controllo utenze',
	'checkuser-log-userips'      => '$1 ha ottenuto gli indirizzi IP di $2',
	'checkuser-log-ipedits'      => '$1 ha ottenuto le modifiche di $2',
	'checkuser-log-ipusers'      => '$1 ha ottenuto le utenze di $2',
	'checkuser-log-ipedits-xff'  => '$1 ha ottenuto le modifiche di $2 via XFF',
	'checkuser-log-ipusers-xff'  => '$1 ha ottenuto le utenze di $2 via XFF',
);

/** Japanese (日本語)
 * @author JtFuruhata
 * @author Kahusi
 * @author Suisui
 */
$messages['ja'] = array(
	'checkuser-summary'          => 'このツールは最近の更新から行った調査を元に、ある利用者が使用したIPアドレスの検索、または、あるIPアドレスからなされた編集および利用者名の表示を行います。
IPアドレスと共に「/xff」オプションを指定すると、XFF（X-Forwarded-For）ヘッダを通じてクライアントIPアドレスを取得し、そこからなされた編集および利用者名の検索をすることが可能です。
IPv4（16から32ビットのCIDR表記）と IPv6（64から128ビットのCIDR表記）をサポートしています。
パフォーマンス上の理由により、5000件の編集しか返答出来ません。
「チェックユーザーの方針」に従って利用してください。',
	'checkuser-desc'             => '利用者のIPアドレスやその他の情報をチェックする権限を持つ特定のユーザーに対し、その能力を与える',
	'checkuser-logcase'          => 'ログの検索では大文字と小文字を区別します。',
	'checkuser'                  => 'チェックユーザー',
	'group-checkuser'            => 'チェックユーザー',
	'group-checkuser-member'     => 'チェックユーザー',
	'grouppage-checkuser'        => '{{ns:project}}:チェックユーザー',
	'checkuser-reason'           => '理由',
	'checkuser-showlog'          => 'ログを閲覧',
	'checkuser-log'              => 'チェックユーザー・ログ',
	'checkuser-query'            => '最近の更新を照会',
	'checkuser-target'           => '利用者名又はIPアドレス',
	'checkuser-users'            => '利用者名を得る',
	'checkuser-edits'            => 'IPアドレスからの編集を得る',
	'checkuser-ips'              => 'IPアドレスを得る',
	'checkuser-search'           => '検索',
	'checkuser-empty'            => 'ログ内にアイテムがありません。',
	'checkuser-nomatch'          => '該当するものはありません。',
	'checkuser-check'            => '調査',
	'checkuser-log-fail'         => 'ログに追加することができません',
	'checkuser-nolog'            => 'ログファイルが見つかりません。',
	'checkuser-blocked'          => 'ブロック済み',
	'checkuser-too-many'         => '検索結果が多すぎます、CIDRの指定を小さく絞り込んでください。利用されたIPは以下の通りです（5000件を上限に、アドレス順でソートされています）:',
	'checkuser-user-nonexistent' => '指定されたユーザーは存在しません。',
	'checkuser-search-form'      => 'ログ検索条件　$1 が $2',
	'checkuser-search-submit'    => '検索',
	'checkuser-search-initiator' => 'チェック者',
	'checkuser-search-target'    => 'チェック対象',
	'checkuser-log-subpage'      => 'ログ',
	'checkuser-log-return'       => 'チェックユーザーのメインフォームへ戻る',
	'checkuser-log-userips'      => '$1 は $2 が使用したIPアドレスを取得した',
	'checkuser-log-ipedits'      => '$1 は $2 からなされた編集を取得した',
	'checkuser-log-ipusers'      => '$1 は $2 からアクセスされた利用者名を取得した',
	'checkuser-log-ipedits-xff'  => '$1 は XFF $2 からなされた編集を取得した',
	'checkuser-log-ipusers-xff'  => '$1 は XFF $2 からアクセスされた利用者名を取得した',
);

/** Jutish (Jysk)
 * @author Huslåke
 */
$messages['jut'] = array(
	'checkuser'              => 'Check user',
	'group-checkuser'        => 'Check users',
	'group-checkuser-member' => 'Check user',
	'grouppage-checkuser'    => '{{ns:project}}:Check user',
	'checkuser-showlog'      => "Se'n log",
	'checkuser-log'          => 'CheckUser log',
	'checkuser-target'       => 'Bruger æller IP',
	'checkuser-users'        => 'Gæt bruger!',
	'checkuser-edits'        => 'Gæt redigærer IPs!',
	'checkuser-ips'          => 'Gæt IP!',
	'checkuser-search'       => 'Søĝ',
	'checkuser-check'        => 'Check',
);

$messages['kk-arab'] = array(
'checkuser-summary'      => 'بۇل قۇرال پايدالانۋشى قولدانعان IP جايلار ٴۇشىن, نەمەسە IP جاي تۇزەتۋ/پايدالانۋشى دەرەكتەرىن كورسەتۋ ٴۇشىن جۋىقتاعى وزگەرىستەردى قاراپ شىعادى.
	پايدالانۋشىلاردى مەن تۇزەتۋلەردى XFF IP ارقىلى IP جايعا «/xff» دەگەندى قوسىپ كەلتىرۋگە بولادى. IPv4 (CIDR 16-32) جانە IPv6 (CIDR 64-128) ارقاۋلانادى.
	ورىنداۋشىلىق سەبەپتەرىمەن 5000 تۇزەتۋدەن ارتىق قايتارىلمايدى. بۇنى ەرەجەلەرگە سايكەس پايدالانىڭىز.',
	'checkuser-logcase'      => 'جۋرنالدان ىزدەۋ ٴارىپ باس-كىشىلىگىن ايىرادى.',
	'checkuser'              => 'قاتىسۋشىنى سىناۋ',
	'group-checkuser'        => 'قاتىسۋشى سىناۋشىلار',
	'group-checkuser-member' => 'قاتىسۋشى سىناۋشى',
	'grouppage-checkuser'    => '{{ns:project}}:قاتىسۋشىنى سىناۋ',
	'checkuser-reason'       => 'سەبەبى',
	'checkuser-showlog'      => 'جۋرنالدى كورسەت',
	'checkuser-log'          => 'قاتىسۋشى سىناۋ جۋرنالى',
	'checkuser-query'        => 'جۋىقتاعى وزگەرىستەردى سۇرانىمداۋ',
	'checkuser-target'       => 'قاتىسۋشى اتى / IP جاي',
	'checkuser-users'        => 'قاتىسۋشىلاردى كەلتىرۋ',
	'checkuser-edits'        => 'IP جايدان جاسالعان تۇزەتۋلەردى كەلتىرۋ',
	'checkuser-ips'          => 'IP جايلاردى كەلتىرۋ',
	'checkuser-search'       => 'ىزدەۋ',
	'checkuser-empty'        => 'جۋرنالدا ەش جازبا جوق.',
	'checkuser-nomatch'      => 'سايكەس تابىلمادى.',
	'checkuser-check'        => 'سىناۋ',
	'checkuser-log-fail'     => 'جۋرنالعا جازبا ۇستەلىنبەدى',
	'checkuser-nolog'        => 'جۋرنال فايلى تابىلمادى.',
	'checkuser-blocked'      => 'بۇعاتتالعان',
	'checkuser-too-many'         => 'تىم كوپ ناتىيجە كەلتىرىلدى, CIDR دەگەندى تارىلتىپ كورىڭىز. مىندا پايدالانىلعان IP جايلار كورسەتىلگەن (بارىنشا 5000, جايىمەن سۇرىپتالعان):',
	'checkuser-user-nonexistent' => 'ەنگىزىلگەن قاتىسۋشى جوق.',
	'checkuser-search-form'      => 'جۋرنالداعى وقىيعالاردى تابۋ ($1 دەگەن $2 ەكەن جايىنداعى)',
	'checkuser-search-submit'    => 'ىزدەۋ',
	'checkuser-search-initiator' => 'باستاماشى',
	'checkuser-search-target'    => 'نىسانا',
	'checkuser-log-subpage'      => 'جۋرنال',
	'checkuser-log-return'       => 'CheckUser باسقى پىشىنىنە  ورالۋ',
	'checkuser-log-userips'      => '$2 ٴۇشىن $1 IP جاي الىندى',
	'checkuser-log-ipedits'      => '$2 ٴۇشىن $1 تۇزەتۋ الىندى',
	'checkuser-log-ipusers'      => '$2 ٴۇشىن $1 IP قاتىسۋشى الىندى',
	'checkuser-log-ipedits-xff'  => 'XFF $2 ٴۇشىن $1 تۇزەتۋ الىندى',
	'checkuser-log-ipusers-xff'  => 'XFF $2 ٴۇشىن $1 قاتىسۋشى الىندى',
);

$messages['kk-cyrl'] = array(
	'checkuser-summary'      => 'Бұл құрал пайдаланушы қолданған IP жайлар үшін, немесе IP жай түзету/пайдаланушы деректерін көрсету үшін жуықтағы өзгерістерді қарап шығады.
	Пайдаланушыларды мен түзетулерді XFF IP арқылы IP жайға «/xff» дегенді қосып келтіруге болады. IPv4 (CIDR 16-32) және IPv6 (CIDR 64-128) арқауланады.
	Орындаушылық себептерімен 5000 түзетуден артық қайтарылмайды. Бұны ережелерге сәйкес пайдаланыңыз.',
	'checkuser-logcase'      => 'Журналдан іздеу әріп бас-кішілігін айырады.',
	'checkuser'              => 'Қатысушыны сынау',
	'group-checkuser'        => 'Қатысушы сынаушылар',
	'group-checkuser-member' => 'қатысушы сынаушы',
	'grouppage-checkuser'    => '{{ns:project}}:Қатысушыны сынау',
	'checkuser-reason'       => 'Себебі',
	'checkuser-showlog'      => 'Журналды көрсет',
	'checkuser-log'          => 'Қатысушы сынау журналы',
	'checkuser-query'        => 'Жуықтағы өзгерістерді сұранымдау',
	'checkuser-target'       => 'Қатысушы аты / IP жай',
	'checkuser-users'        => 'Қатысушыларды келтіру',
	'checkuser-edits'        => 'IP жайдан жасалған түзетулерді келтіру',
	'checkuser-ips'          => 'IP жайларды келтіру',
	'checkuser-search'       => 'Іздеу',
	'checkuser-empty'        => 'Журналда еш жазба жоқ.',
	'checkuser-nomatch'      => 'Сәйкес табылмады.',
	'checkuser-check'        => 'Сынау',
	'checkuser-log-fail'     => 'Журналға жазба үстелінбеді',
	'checkuser-nolog'        => 'Журнал файлы табылмады.',
	'checkuser-blocked'      => 'Бұғатталған',
	'checkuser-too-many'         => 'Тым көп нәтиже келтірілді, CIDR дегенді тарылтып көріңіз. Мында пайдаланылған IP жайлар көрсетілген (барынша 5000, жайымен сұрыпталған):',
	'checkuser-user-nonexistent' => 'Енгізілген қатысушы жоқ.',
	'checkuser-search-form'      => 'Журналдағы оқиғаларды табу ($1 деген $2 екен жайындағы)',
	'checkuser-search-submit'    => 'Іздеу',
	'checkuser-search-initiator' => 'бастамашы',
	'checkuser-search-target'    => 'нысана',
	'checkuser-log-subpage'      => 'Журнал',
	'checkuser-log-return'       => 'CheckUser басқы пішініне  оралу',
	'checkuser-log-userips'      => '$2 үшін $1 IP жай алынды',
	'checkuser-log-ipedits'      => '$2 үшін $1 түзету алынды',
	'checkuser-log-ipusers'      => '$2 үшін $1 IP қатысушы алынды',
	'checkuser-log-ipedits-xff'  => 'XFF $2 үшін $1 түзету алынды',
	'checkuser-log-ipusers-xff'  => 'XFF $2 үшін $1 қатысушы алынды',
);

$messages['kk-latn'] = array(
	'checkuser-summary'      => 'Bul qural paýdalanwşı qoldanğan IP jaýlar üşin, nemese IP jaý tüzetw/paýdalanwşı derekterin körsetw üşin jwıqtağı özgeristerdi qarap şığadı.
	Paýdalanwşılardı men tüzetwlerdi XFF IP arqılı IP jaýğa «/xff» degendi qosıp keltirwge boladı. IPv4 (CIDR 16-32) jäne IPv6 (CIDR 64-128) arqawlanadı.
	Orındawşılıq sebepterimen 5000 tüzetwden artıq qaýtarılmaýdı. Bunı erejelerge säýkes paýdalanıñız.',
	'checkuser-logcase'      => 'Jwrnaldan izdew ärip bas-kişiligin aýıradı.',
	'checkuser'              => 'Qatıswşını sınaw',
	'group-checkuser'        => 'Qatıswşı sınawşılar',
	'group-checkuser-member' => 'qatıswşı sınawşı',
	'grouppage-checkuser'    => '{{ns:project}}:Qatıswşını sınaw',
	'checkuser-reason'       => 'Sebebi',
	'checkuser-showlog'      => 'Jwrnaldı körset',
	'checkuser-log'          => 'Qatıswşı sınaw jwrnalı',
	'checkuser-query'        => 'Jwıqtağı özgeristerdi suranımdaw',
	'checkuser-target'       => 'Qatıswşı atı / IP jaý',
	'checkuser-users'        => 'Qatıswşılardı keltirw',
	'checkuser-edits'        => 'IP jaýdan jasalğan tüzetwlerdi keltirw',
	'checkuser-ips'          => 'IP jaýlardı keltirw',
	'checkuser-search'       => 'İzdew',
	'checkuser-empty'        => 'Jwrnalda eş jazba joq.',
	'checkuser-nomatch'      => 'Säýkes tabılmadı.',
	'checkuser-check'        => 'Sınaw',
	'checkuser-log-fail'     => 'Jwrnalğa jazba üstelinbedi',
	'checkuser-nolog'        => 'Jwrnal faýlı tabılmadı.',
	'checkuser-blocked'      => 'Buğattalğan',
	'checkuser-too-many'         => 'Tım köp nätïje keltirildi, CIDR degendi tarıltıp köriñiz. Mında paýdalanılğan IP jaýlar körsetilgen (barınşa 5000, jaýımen surıptalğan):',
	'checkuser-user-nonexistent' => 'Engizilgen qatıswşı joq.',
	'checkuser-search-form'      => 'Jwrnaldağı oqïğalardı tabw ($1 degen $2 eken jaýındağı)',
	'checkuser-search-submit'    => 'İzdew',
	'checkuser-search-initiator' => 'bastamaşı',
	'checkuser-search-target'    => 'nısana',
	'checkuser-log-subpage'      => 'Jwrnal',
	'checkuser-log-return'       => 'CheckUser basqı pişinine  oralw',
	'checkuser-log-userips'      => '$2 üşin $1 IP jaý alındı',
	'checkuser-log-ipedits'      => '$2 üşin $1 tüzetw alındı',
	'checkuser-log-ipusers'      => '$2 üşin $1 IP qatıswşı alındı',
	'checkuser-log-ipedits-xff'  => 'XFF $2 üşin $1 tüzetw alındı',
	'checkuser-log-ipusers-xff'  => 'XFF $2 üşin $1 qatıswşı alındı',
);

/** Khmer (ភាសាខ្មែរ)
 * @author Chhorran
 */
$messages['km'] = array(
	'checkuser'               => 'ឆែក អ្នកប្រើប្រាស់',
	'group-checkuser'         => 'ឆែក អ្នកប្រើប្រាស់',
	'group-checkuser-member'  => 'ឆែក អ្នកប្រើប្រាស់',
	'checkuser-reason'        => 'ហេតុផល',
	'checkuser-showlog'       => 'បង្ហាញ កំណត់ហេតុ',
	'checkuser-target'        => 'អ្នកប្រើប្រាស់ ឬ IP',
	'checkuser-search'        => 'ស្វែងរក',
	'checkuser-check'         => 'ឆែក',
	'checkuser-search-submit' => 'ស្វែងរក',
	'checkuser-log-subpage'   => 'កំណត់ហេតុ',
);

$messages['kn'] = array(
	'checkuser'              => 'ಸದಸ್ಯನನ್ನು ಚೆಕ್ ಮಾಡಿ',
);

/** Korean (한국어)
 * @author Klutzy
 */
$messages['ko'] = array(
	'checkuser'              => '체크유저',
	'group-checkuser'        => '체크유저',
	'group-checkuser-member' => '체크유저',
	'grouppage-checkuser'    => '{{ns:project}}:체크유저',
	'checkuser-reason'       => '이유',
	'checkuser-search'       => '찾기',
);

$messages['la'] = array(
	'checkuser-reason'       => 'Causa',
	'checkuser-search'       => 'Quaerere',
);

/** Luxembourgish (Lëtzebuergesch)
 * @author Robby
 */
$messages['lb'] = array(
	'checkuser-logcase'          => "D'Sich am Logbuch mecht en Ënnerscheed tëschent groussen a klenge Buchstawen (Caractèren).",
	'checkuser'                  => 'Benotzer-Check',
	'group-checkuser'            => 'Benotzer Kontrolleren',
	'group-checkuser-member'     => 'Benotzer Kontroller',
	'grouppage-checkuser'        => '{{ns:projet}}:Benotzer Kontroller',
	'checkuser-reason'           => 'Grond',
	'checkuser-showlog'          => 'Logbuch weisen',
	'checkuser-log'              => 'Lëscht vun de Benotzer Kontrollen',
	'checkuser-query'            => 'Rezent Ännerungen offroen',
	'checkuser-target'           => 'Benotzer oder IP-Adress',
	'checkuser-users'            => 'Benotzer kréien',
	'checkuser-edits'            => "Weis d'Ännerungen vun der IP-Adress",
	'checkuser-ips'              => 'IP-Adresse kréien/uweisen',
	'checkuser-search'           => 'Sichen',
	'checkuser-empty'            => 'Dës Lëscht ass eidel.',
	'checkuser-nomatch'          => 'Et goufe keng Iwwereneestëmmunge fonnt.',
	'checkuser-check'            => 'Kontrolléieren',
	'checkuser-nolog'            => "D'Logbuch gouf net fonnt.",
	'checkuser-blocked'          => 'Gespaart',
	'checkuser-user-nonexistent' => 'De gesichte Benotzer gëtt et net.',
	'checkuser-search-submit'    => 'Sichen',
	'checkuser-search-initiator' => 'Initiator',
	'checkuser-search-target'    => 'Zil',
	'checkuser-log-subpage'      => 'Lëscht',
	'checkuser-log-return'       => 'Zréck op den Haaptformulair vun der Benotzer Kontroll',
);

/** Limburgish (Limburgs)
 * @author Ooswesthoesbes
 */
$messages['li'] = array(
	'checkuser-summary'          => "Dit hölpmiddel bekiek recènte verangeringe óm IP-adresse die 'ne gebroeker haet gebroek te achterhaole of toeantj de bewèrkings- en gebroekersgegaeves veur 'n IP-adres.
Gebroekers en bewèrkinge van 'n IP-adres van 'ne cliënt kinne achterhaoldj waere via XFF-headers door \"/xff\" achter 't IP-adres toe te voege. IPv4 (CIDR 16-32) en IPv6 (CIDR 64-128) waere óngersteundj.
Óm prestatiereej waere neet mieë es 5.000 bewèrkinge getoeantj. Gebroek dit hölpmiddel volges 't vasgesteldje beleid.",
	'checkuser-desc'             => 'Läöt geautproseerde gebroekers IP-adresse en angere informatie van gebroekers achterhaole',
	'checkuser-logcase'          => "Zeuke in 't logbook is huidlèttergeveulig.",
	'checkuser'                  => 'Konterleer gebroeker',
	'group-checkuser'            => 'Gebroekerkonterleerders',
	'group-checkuser-member'     => 'Gebroekerkonterleerder',
	'grouppage-checkuser'        => '{{ns:project}}:Gebroekerkonterleerder',
	'checkuser-reason'           => 'Reej',
	'checkuser-showlog'          => 'Toean logbook',
	'checkuser-log'              => 'Logbook KonterleerGebroeker',
	'checkuser-query'            => 'Bevraog recènte verangeringe',
	'checkuser-target'           => 'Gebroeker of IP-adres',
	'checkuser-users'            => 'Vraog gebroekers op',
	'checkuser-edits'            => 'Vraog bewèrkinge van IP-adres op',
	'checkuser-ips'              => 'Vraof IP-adresse op',
	'checkuser-search'           => 'Zeuk',
	'checkuser-empty'            => "'t Logbook bevat gein regels.",
	'checkuser-nomatch'          => 'Gein euvereinkómste gevónje.',
	'checkuser-check'            => 'Conterleer',
	'checkuser-log-fail'         => 'Logbookregel toevoege neet meugelik',
	'checkuser-nolog'            => 'Gein logbook gevónje.',
	'checkuser-blocked'          => 'Geblokkeerdj',
	'checkuser-too-many'         => 'Te väöl rezultaote. Maak de IP-reiks kleinder:',
	'checkuser-user-nonexistent' => 'De opgegaeve gebroeker besteit neet.',
	'checkuser-search-form'      => 'Logbookregels zeuke wo de $1 $2 is',
	'checkuser-search-submit'    => 'Zeuk',
	'checkuser-search-initiator' => 'aanvraoger',
	'checkuser-search-target'    => 'óngerwèrp',
	'checkuser-log-subpage'      => 'Logbook',
	'checkuser-log-return'       => "Nao 't huidformeleer van KonterleerGebroeker trökgaon",
	'checkuser-log-userips'      => '$1 haet IP-adresse veur $2',
	'checkuser-log-ipedits'      => '$1 haet bewèrkinge veur $2',
	'checkuser-log-ipusers'      => '$1 haet gebroekers veur $2',
	'checkuser-log-ipedits-xff'  => '$1 haet bewèrkinge veur XFF $2',
	'checkuser-log-ipusers-xff'  => '$1 haet gebrokers veur XFF $2',
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

/** Lithuanian (Lietuvių)
 * @author Matasg
 */
$messages['lt'] = array(
	'checkuser-reason'        => 'Priežastis',
	'checkuser-showlog'       => 'Rodyti sąrašą',
	'checkuser-target'        => 'Naudotojas arba IP',
	'checkuser-ips'           => 'Gauti IP',
	'checkuser-search'        => 'Ieškoti',
	'checkuser-check'         => 'Tikrinti',
	'checkuser-blocked'       => 'Užblokuotas',
	'checkuser-search-submit' => 'Ieškoti',
	'checkuser-log-subpage'   => 'Sąrašas',
);

$messages['mk'] = array(
	'checkuser'              => 'Провери корисник',
);

/** Erzya (эрзянь кель)
 * @author Tupikovs
 * @author Amdf
 */
$messages['myv'] = array(
	'checkuser-target' => 'Совиця эли IP',
	'checkuser-search' => 'Вешнемс',
);

$messages['nap'] = array(
	'checkuser-search'       => 'Truova',
);

/** Low German (Plattdüütsch)
 * @author Slomox
 */
$messages['nds'] = array(
	'checkuser'              => 'Bruker nakieken',
	'group-checkuser'        => 'Brukers nakieken',
	'group-checkuser-member' => 'Bruker nakieken',
	'grouppage-checkuser'    => '{{ns:project}}:Checkuser',
	'checkuser-reason'       => 'Grund',
	'checkuser-search'       => 'Söken',
	'checkuser-nolog'        => 'Keen Loogbook funnen.',
);

/** Dutch (Nederlands)
 * @author Siebrand
 * @author SPQRobin
 * @author Troefkaart
 */
$messages['nl'] = array(
	'checkuser-summary'          => 'Dit hulpmiddel bekijkt recente wijzigingen om IP-adressen die een gebruiker heeft gebruikt te achterhalen of toont de bewerkings- en gebruikersgegegevens voor een IP-adres.
	Gebruikers en bewerkingen van een IP-adres van een client kunnen achterhaald worden via XFF-headers door "/xff" achter het IP-adres toe te voegen. IPv4 (CIDR 16-32) en IPv6 (CIDR 64-128) worden ondersteund.
	Om prestatieredenen worden niet meer dan 5.000 bewerkingen getoond. Gebruik dit hulpmiddel volgens het vastgestelde beleid.',
	'checkuser-desc'             => 'Laat bevoegde gebruikers IP-adressen en andere informatie van gebruikers achterhalen',
	'checkuser-logcase'          => 'Zoeken in het logboek is hoofdlettergevoelig.',
	'checkuser'                  => 'Gebruiker controleren',
	'group-checkuser'            => 'Controlegebruikers',
	'group-checkuser-member'     => 'Controlegebruiker',
	'grouppage-checkuser'        => '{{ns:project}}:Controlegebruiker',
	'checkuser-reason'           => 'Reden',
	'checkuser-showlog'          => 'Logboek tonen',
	'checkuser-log'              => 'Logboek controleren gebruikers',
	'checkuser-query'            => 'Bevraag recente wijzigingen',
	'checkuser-target'           => 'Gebruiker of IP-adres',
	'checkuser-users'            => 'Gebruikers opvragen',
	'checkuser-edits'            => 'Bewerkingen van IP-adres opvragen',
	'checkuser-ips'              => 'IP-adressen opvragen',
	'checkuser-search'           => 'Zoeken',
	'checkuser-empty'            => 'Het logboek bevat geen regels.',
	'checkuser-nomatch'          => 'Geen overeenkomsten gevonden.',
	'checkuser-check'            => 'Controleren',
	'checkuser-log-fail'         => 'Logboekregel toevoegen niet mogelijk',
	'checkuser-nolog'            => 'Geen logboek gevonden.',
	'checkuser-blocked'          => 'Geblokkeerd',
	'checkuser-too-many'         => 'Te veel resultaten. Maak de IP-reeks kleiner:',
	'checkuser-user-nonexistent' => 'De opgegeven gebruiker bestaat niet.',
	'checkuser-search-form'      => 'Logboekregels zoeken waar de $1 $2 is',
	'checkuser-search-submit'    => 'Zoeken',
	'checkuser-search-initiator' => 'aanvrager',
	'checkuser-search-target'    => 'onderwerp',
	'checkuser-log-subpage'      => 'Logboek',
	'checkuser-log-return'       => 'Naar het hoofdformulier van ControleGebruiker terugkeren',
	'checkuser-log-userips'      => '$1 heeft IP-adressen voor $2',
	'checkuser-log-ipedits'      => '$1 heeft bewerkingen voor $2',
	'checkuser-log-ipusers'      => '$1 heeft gebruikers voor $2',
	'checkuser-log-ipedits-xff'  => '$1 heeft bewerkingen voor XFF $2',
	'checkuser-log-ipusers-xff'  => '$1 heeft gebruikers voor XFF $2',
);

/** Norwegian (‪Norsk (bokmål)‬)
 * @author Jon Harald Søby
 */
$messages['no'] = array(
	'checkuser-summary'          => 'Dette verktøyet går gjennom siste endringer for å hente IP-ene som er brukt av en bruker, eller viser redigerings- eller brukerinformasjonen for en IP.

Brukere og redigeringer kan hentes med en XFF-IP ved å legge til «/xff» bak IP-en. IPv4 (CIDR 16-32) og IPv6 (CIDR 64-128) støttes.

Av ytelsesgrunner vises maksimalt 5000 redigeringer. Bruk dette verktøyet i samsvar med retningslinjer.',
	'checkuser-desc'             => 'Gir brukere med de tilhørende rettighetene muligheten til å sjekke brukeres IP-adresser og annen informasjon',
	'checkuser-logcase'          => 'Loggsøket er sensitivt for store/små bokstaver.',
	'checkuser'                  => 'Brukersjekk',
	'group-checkuser'            => 'IP-kontrollører',
	'group-checkuser-member'     => 'IP-kontrollør',
	'grouppage-checkuser'        => '{{ns:project}}:IP-kontrollør',
	'checkuser-reason'           => 'Grunn',
	'checkuser-showlog'          => 'Vis logg',
	'checkuser-log'              => 'Brukersjekkingslogg',
	'checkuser-query'            => 'Søk i siste endringer',
	'checkuser-target'           => 'Bruker eller IP',
	'checkuser-users'            => 'Få brukere',
	'checkuser-edits'            => 'Få redigeringer fra IP',
	'checkuser-ips'              => 'Få IP-er',
	'checkuser-search'           => 'Søk',
	'checkuser-empty'            => 'Loggen inneholder ingen elementer.',
	'checkuser-nomatch'          => 'Ingen treff.',
	'checkuser-check'            => 'Sjekk',
	'checkuser-log-fail'         => 'Kunne ikke legge til loggelement.',
	'checkuser-nolog'            => 'Ingen loggfil funnet.',
	'checkuser-blocked'          => 'Blokkert',
	'checkuser-too-many'         => 'For mange resultater, vennligst innskrenk CIDR. Her er de brukte IP-ene (maks 5000, sortert etter adresse):',
	'checkuser-user-nonexistent' => 'Det gitte brukernavnet finnes ikke.',
	'checkuser-search-form'      => 'Finn loggelementer der $1 er $2',
	'checkuser-search-submit'    => 'Søk',
	'checkuser-search-initiator' => 'IP-kontrolløren',
	'checkuser-search-target'    => 'målet',
	'checkuser-log-subpage'      => 'Logg',
	'checkuser-log-return'       => 'Tilbake til hovedskjema for brukersjekking',
	'checkuser-log-userips'      => '$1 fikk IP-adressene til $2',
	'checkuser-log-ipedits'      => '$1 fikk endringer av $2',
	'checkuser-log-ipusers'      => '$1 fikk brukere av $2',
	'checkuser-log-ipedits-xff'  => '$1 fikk endringer av XFF-en $2',
	'checkuser-log-ipusers-xff'  => '$1 fikk brukere av XFF-en $2',
);

/** Novial (Novial)
 * @author MF-Warburg
 */
$messages['nov'] = array(
	'checkuser-search' => 'Sercha',
);

/** Northern Sotho (Sesotho sa Leboa)
 * @author Mohau
 */
$messages['nso'] = array(
	'checkuser-reason'        => 'Lebaka',
	'checkuser-target'        => 'Mošomiši goba IP',
	'checkuser-search'        => 'Fetleka',
	'checkuser-blocked'       => 'Thibilwe',
	'checkuser-search-submit' => 'Fetleka',
);

/** Occitan (Occitan)
 * @author Cedric31
 */
$messages['oc'] = array(
	'checkuser-summary'          => "Aqueste esplech passa en revista los cambiaments recents per recercar l'IPS emplegada per un utilizaire, mostrar totas las edicions fachas per una IP, o per enumerar los utilizaires qu'an emplegat las IPs. Los utilizaires e las modificacions pòdon èsser trobatss amb una IP XFF se s'acaba amb « /xff ». IPv4 (CIDR 16-32) e IPv6(CIDR 64-128) son suportats. Emplegatz aquò segon las cadenas de caractèrs.",
	'checkuser-desc'             => 'Balha la possibilitat a las personas exprèssament autorizadas de verificar las adreças IP dels utilizaires e mai d’autras entre-senhas los concernent',
	'checkuser-logcase'          => 'La recèrca dins lo Jornal es sensibla a la cassa.',
	'checkuser'                  => 'Verificator d’utilizaire',
	'group-checkuser'            => 'Verificators d’utilizaire',
	'group-checkuser-member'     => 'Verificator d’utilizaire',
	'grouppage-checkuser'        => '{{ns:project}}:Verificator d’utilizaire',
	'checkuser-reason'           => 'Explicacion',
	'checkuser-showlog'          => 'Mostrar la lista obtenguda',
	'checkuser-log'              => "Notacion de Verificator d'utilizaire",
	'checkuser-query'            => 'Recèrca pels darrièrs cambiaments',
	'checkuser-target'           => "Nom de l'utilizaire o IP",
	'checkuser-users'            => 'Obténer los utilizaires',
	'checkuser-edits'            => "Obténer las modificacions de l'IP",
	'checkuser-ips'              => 'Obténer las IPs',
	'checkuser-search'           => 'Recèrca',
	'checkuser-empty'            => "Lo jornal conten pas cap d'article",
	'checkuser-nomatch'          => 'Recèrcas infructuosas.',
	'checkuser-check'            => 'Recèrca',
	'checkuser-log-fail'         => "Incapaç d'ajustar la dintrada del jornal.",
	'checkuser-nolog'            => 'Cap de dintrada dins lo Jornal.',
	'checkuser-blocked'          => 'Blocat',
	'checkuser-too-many'         => 'Tròp de resultats. Limitatz la recèrca sus las adreças IP :',
	'checkuser-user-nonexistent' => 'L’utilizaire indicat existís pas',
	'checkuser-search-form'      => 'Cercar lo jornal de las entradas ont $1 es $2.',
	'checkuser-search-submit'    => 'Recercar',
	'checkuser-search-initiator' => 'l’iniciador',
	'checkuser-search-target'    => 'la cibla',
	'checkuser-log-subpage'      => 'Jornal',
	'checkuser-log-return'       => "Tornar al formulari principal de la verificacion d'utilizaire",
	'checkuser-log-userips'      => "$1 a obtengut d'IP per $2",
	'checkuser-log-ipedits'      => '$1 a obtengut de modificacions per $2',
	'checkuser-log-ipusers'      => "$1 a obtengut d'utilizaires per $2",
	'checkuser-log-ipedits-xff'  => '$1 a obtengut de modificacions per XFF  $2',
	'checkuser-log-ipusers-xff'  => "$1 a obtengut d'utilizaires per XFF $2",
);

/** Pangasinan (Pangasinan)
 * @author SPQRobin
 */
$messages['pag'] = array(
	'checkuser-reason' => 'Katonongan',
	'checkuser-target' => 'Manag-usar odino IP',
	'checkuser-users'  => 'Alaen so manag-usar',
	'checkuser-search' => 'Anapen',
);

/** Pampanga (Kapampangan)
 * @author SPQRobin
 */
$messages['pam'] = array(
	'checkuser'         => 'Surian ya ing gagamit',
	'checkuser-reason'  => 'Sangkan',
	'checkuser-showlog' => 'Pakit ya ing log',
	'checkuser-search'  => 'Manintun',
);

/** Polish (Polski)
 * @author Sp5uhe
 * @author Derbeth
 */
$messages['pl'] = array(
	'checkuser-summary'          => 'To narzędzie skanuje ostatnie zmiany by znaleźć adresy IP użyte przez użytkownika lub pokazać edycje/użytkowników dla adresu IP. Użytkownicy i edycje spod adresu IP mogą być pozyskane przez nagłówki XFF przez dodanie do IP "/xff". Obsługiwane są adresy IPv4 (CIDR 16-32) I IPv6 (CIDR 64-128). Ze względu na wydajność, zostanie zwróconych nie więcej niż 5000 edycji. Prosimy o używanie tej funkcji zgodnie z zasadami.',
	'checkuser-desc'             => 'Umożliwia uprawnionym użytkownikom sprawdzenie adresów IP użytkowników oraz innych informacji',
	'checkuser-logcase'          => 'Szukanie w logu jest czułe na wielkość znaków',
	'checkuser'                  => 'Sprawdzanie IP użytkownika',
	'group-checkuser'            => 'CheckUser',
	'group-checkuser-member'     => 'CheckUser',
	'grouppage-checkuser'        => '{{ns:project}}:CheckUser',
	'checkuser-reason'           => 'Powód',
	'checkuser-showlog'          => 'Pokaż log',
	'checkuser-log'              => 'Log CheckUser',
	'checkuser-query'            => 'Przeanalizuj ostatnie zmiany',
	'checkuser-target'           => 'Użytkownik lub IP',
	'checkuser-users'            => 'Znajdź użytkowników',
	'checkuser-edits'            => 'Znajdź edycje z IP',
	'checkuser-ips'              => 'Znajdź adresy IP',
	'checkuser-search'           => 'Szukaj',
	'checkuser-empty'            => 'Log nie zawiera żadnych wpisów.',
	'checkuser-nomatch'          => 'Nie znaleziono niczego.',
	'checkuser-check'            => 'Log nie zawiera żadnych wpisów.',
	'checkuser-log-fail'         => 'Nie udało się dodać wpisu do logu.',
	'checkuser-nolog'            => 'Nie znaleziono pliku logu.',
	'checkuser-blocked'          => 'Zablokowany',
	'checkuser-too-many'         => 'Zbyt wiele wyników, proszę ogranicz CIDR. Użytych adresów IP jest (do 5000 posortowanych wg adresu):',
	'checkuser-user-nonexistent' => 'Taki użytkownik nie istnieje.',
	'checkuser-search-form'      => 'Szukaj wpisów w logu, dla których $1 jest $2',
	'checkuser-search-submit'    => 'Szukaj',
	'checkuser-search-initiator' => 'inicjator',
	'checkuser-search-target'    => 'cel',
	'checkuser-log-subpage'      => 'Rejestr',
	'checkuser-log-return'       => 'Powrót do głównego formularza CheckUser',
	'checkuser-log-userips'      => '$1 dostał adresy IP dla $2',
	'checkuser-log-ipedits'      => '$1 dostał edycje dla $2',
	'checkuser-log-ipusers'      => '$1 otrzymał listę użytkowników adresu IP $2',
	'checkuser-log-ipedits-xff'  => '$1 otrzymał listę edycji dla XFF $2',
	'checkuser-log-ipusers-xff'  => '$1 otrzymał listę użytkowników dla XFF $2',
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

/** Pashto (پښتو)
 * @author Ahmed-Najib-Biabani-Ibrahimkhel
 */
$messages['ps'] = array(
	'checkuser-reason'        => 'سبب',
	'checkuser-search'        => 'لټون',
	'checkuser-search-submit' => 'لټون',
	'checkuser-log-subpage'   => 'يادښت',
);

/** Portuguese (Português)
 * @author 555
 * @author Malafaya
 */
$messages['pt'] = array(
	'checkuser-summary'          => 'Esta ferramenta varre as Mudanças recentes para obter os endereços de IP de um utilizador ou para exibir os dados de edições/utilizadores para um IP.
	Utilizadores edições podem ser obtidos por um IP XFF colocando-se "/xff" no final do endereço. São suportados endereços IPv4 (CIDR 16-32) e IPv6 (CIDR 64-128).
	Não serão retornadas mais de 5000 edições por motivos de desempenho. O uso desta ferramenta deverá estar de acordo com as políticas.',
	'checkuser-desc'             => 'Concede a utilizadores com a permissão apropriada a possibilidade de verificar os endereços IP de um utilizador e outra informação',
	'checkuser-logcase'          => 'As buscas nos registos são sensíveis a letras maiúsculas ou minúsculas.',
	'checkuser'                  => 'Verificar utilizador',
	'group-checkuser'            => 'CheckUser',
	'group-checkuser-member'     => 'CheckUser',
	'grouppage-checkuser'        => '{{ns:project}}:CheckUser',
	'checkuser-reason'           => 'Motivo',
	'checkuser-showlog'          => 'Exibir registos',
	'checkuser-log'              => 'Registos de verificação de utilizadores',
	'checkuser-query'            => 'Examinar as Mudanças recentes',
	'checkuser-target'           => 'Utilizador ou IP',
	'checkuser-users'            => 'Obter utilizadores',
	'checkuser-edits'            => 'Obter edições de IPs',
	'checkuser-ips'              => 'Obter IPs',
	'checkuser-search'           => 'Pesquisar',
	'checkuser-empty'            => 'O registo não contém itens.',
	'checkuser-nomatch'          => 'Não foram encontrados resultados.',
	'checkuser-check'            => 'Verificar',
	'checkuser-log-fail'         => 'Não foi possível adicionar entradas ao registo',
	'checkuser-nolog'            => 'Não foi encontrado um arquivo de registos.',
	'checkuser-blocked'          => 'Bloqueado',
	'checkuser-too-many'         => 'Demasiados resultados; por favor, restrinja o CIDR. Aqui estão os IPs usados (5000 no máx., ordenados por endereço):',
	'checkuser-user-nonexistent' => 'O utilizador especificado não existe.',
	'checkuser-search-form'      => 'Procurar entradas no registo onde $1 seja $2',
	'checkuser-search-submit'    => 'Procurar',
	'checkuser-search-initiator' => 'iniciador',
	'checkuser-search-target'    => 'alvo',
	'checkuser-log-subpage'      => 'Registo',
	'checkuser-log-return'       => 'Retornar ao formulário principal de CheckUser',
	'checkuser-log-userips'      => '$1 obteve IPs de $2',
	'checkuser-log-ipedits'      => '$1 obteve edições de $2',
	'checkuser-log-ipusers'      => '$1 obteve utilizadores de $2',
	'checkuser-log-ipedits-xff'  => '$1 obteve edições para o XFF $2',
	'checkuser-log-ipusers-xff'  => '$1 obteve utilizadores para o XFF $2',
);

/** Quechua (Runa Simi)
 * @author AlimanRuna
 */
$messages['qu'] = array(
	'checkuser-summary'          => "Kay llamk'anaqa ñaqha hukchasqakunapim maskaykun huk ruraqpa llamk'achisqan IP huchhakunata chaskinapaq icha huk IP huchhap llamk'apusqamanta/ruraqmanta willankunata rikuchinapaq.
Ruraqkunata icha mink'akuq IP huchhap rurasqankunatapas XFF uma siq'iwanmi chaskiyta atinki IP huchhata \"/xff\" nisqawan yapaspa. IPv4 (CIDR 16-32), IPv6 (CIDR 64-128) nisqakunam llamk'akun.
Pichqa waranqamanta aswan llamk'apusqakunaqa manam kutimunqachu, allin rikuchinarayku. Kay llamk'anataqa kawpayllakama rurachiy.",
	'checkuser-logcase'          => "Hallch'a maskaqqa hatun sananchata uchuy sananchamantam sapaqchan.",
	'checkuser'                  => 'Ruraqta llanchiy',
	'group-checkuser'            => 'Ruraqkunata llanchiy',
	'group-checkuser-member'     => 'Ruraqta llanchiy',
	'grouppage-checkuser'        => '{{ns:project}}:Ruraqta llanchiy',
	'checkuser-reason'           => 'Imarayku',
	'checkuser-showlog'          => "Hallch'ata rikuchiy",
	'checkuser-log'              => "Ruraq llanchiy hallch'a",
	'checkuser-query'            => 'Ñaqha hukchasqakunapi maskay',
	'checkuser-target'           => 'Ruraqpa sutin icha IP huchha',
	'checkuser-users'            => 'Ruraqkunata chaskiy',
	'checkuser-edits'            => 'Ruraqkunap hukchasqankunata chaskiy',
	'checkuser-ips'              => 'IP huchhakunata chaskiy',
	'checkuser-search'           => 'Maskay',
	'checkuser-empty'            => "Manam kanchu ima hallch'asqapas.",
	'checkuser-nomatch'          => 'Manam imapas taripasqachu.',
	'checkuser-check'            => 'Llanchiy',
	'checkuser-log-fail'         => "Manam atinichu hallch'aman yapayta",
	'checkuser-nolog'            => "Manam hallch'ayta tarinichu",
	'checkuser-blocked'          => "Hark'asqa",
	'checkuser-too-many'         => "Nisyum tarisqakuna, ama hina kaspa CIDR nisqata k'ichkichay. Kaymi llamk'achisqa IP huchhakuna (5000-kama, tiyay sutikama siq'inchasqa):",
	'checkuser-user-nonexistent' => 'Nisqayki ruraqqa manam kanchu.',
	'checkuser-search-submit'    => 'Maskay',
	'checkuser-search-initiator' => 'qallarichiq',
	'checkuser-search-target'    => 'taripana',
	'checkuser-log-subpage'      => "Hallch'a",
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
	'checkuser-summary'          => "Данный инструмент может быть использован, чтобы получить IP-адреса, использовавшиеся участником, либо чтобы показать правки/участников, работавших с IP-адреса.
	Правки и пользователи, которые правили с опрделеннного IP-адреса, указанного в X-Forwarded-For, можно получить, добавив префикс <code>/xff</code> к IP-адресу. Поддерживаемые версии IP: 4 (CIDR 16—32) и 6 (CIDR 64—128).
	Из соображений производительности будут показаны только первые 5000 правок. Используйте эту страницу '''только в соответствии с правилами'''.",
	'checkuser-desc'             => 'Предоставление возможности проверять IP-адреса и другую информацию участников',
	'checkuser-logcase'          => 'Поиск по журналу чувствителен к регистру.',
	'checkuser'                  => 'Проверить участника',
	'group-checkuser'            => 'Проверяющие участников',
	'group-checkuser-member'     => 'проверяющий участников',
	'grouppage-checkuser'        => '{{ns:project}}:Проверка участников',
	'checkuser-reason'           => 'Причина',
	'checkuser-showlog'          => 'Показать журнал',
	'checkuser-log'              => 'Журнал проверки участников',
	'checkuser-query'            => 'Запросить свежие правки',
	'checkuser-target'           => 'Пользователь или IP-адрес',
	'checkuser-users'            => 'Получить пользователей',
	'checkuser-edits'            => 'Запросить правки, сделанные с IP-адреса',
	'checkuser-ips'              => 'Запросить IP-адреса',
	'checkuser-search'           => 'Найти',
	'checkuser-empty'            => 'Журнал пуст.',
	'checkuser-nomatch'          => 'Совпадений не найдено.',
	'checkuser-check'            => 'Проверить',
	'checkuser-log-fail'         => 'Невозможно добавить запись в журнал',
	'checkuser-nolog'            => 'Файл журнала не найден.',
	'checkuser-blocked'          => 'Заблокирован',
	'checkuser-too-many'         => 'Слишком много результатов, пожалуйста, сузьте CIDR. Использованные IP (максимум 5000, отсортировано по адресу):',
	'checkuser-user-nonexistent' => 'Указанного участника не существует',
	'checkuser-search-form'      => 'Найти записи журнала, где $1 является $2',
	'checkuser-search-submit'    => 'Найти',
	'checkuser-search-initiator' => 'инициатор',
	'checkuser-search-target'    => 'цель',
	'checkuser-log-subpage'      => 'Журнал',
	'checkuser-log-return'       => 'Возврат к главной форме проверки участников',
	'checkuser-log-userips'      => '$1 получил IP адреса для $2',
	'checkuser-log-ipedits'      => '$1 получил правки для $2',
	'checkuser-log-ipusers'      => '$1 получил учётные записи для $2',
	'checkuser-log-ipedits-xff'  => '$1 получил правки для XFF $2',
	'checkuser-log-ipusers-xff'  => '$1 получил учётные записи для XFF $2',
);

/** Yakut (Саха тыла)
 * @author HalanTul
 */
$messages['sah'] = array(
	'checkuser-summary'          => "Бу үстүрүмүөнү кыттааччы IP-ларын көрөргө, эбэтэр IP-аадырыһы туһаммыт хас да кыттааччы уларытыыларын көрөргө туттуохха сөп.
Биир IP-аадырыстан оҥоһуллубут көннөрүүлэри, эбэтэр ону туһаммыт X-Forwarded-For ыйыллыбыт кыттааччылары көрөргө, бу префиксы IP-га туруоран биэр: <code>/xff</code>. Поддерживаемые версии IP: 4 (CIDR 16—32) и 6 (CIDR 64—128).
Систиэмэни ноҕуруускалаамаары бастакы 5000 көннөрүү эрэ көрдөрүллүөҕэ. Бу сирэйи '''сиэрдээхтик''' тутун.",
	'checkuser-desc'             => 'Кыттаачылар IP-ларын уонна кинилэр тустарынан атын сибидиэнньэлэри көрөр кыаҕы биэрии.',
	'checkuser-logcase'          => 'Сурунаалга көрдөөһүн улахан/кыра буукубалары араарар.',
	'checkuser'                  => 'Кыттааччыны бэрэбиэркэлээ',
	'group-checkuser'            => 'Кыттааччылары бэрэбиэркэлээччилэр',
	'group-checkuser-member'     => 'Кыттааччылары бэрэбиэркэлээччи',
	'grouppage-checkuser'        => '{{ns:project}}:Кыттааччылары бэрэбиэркэлээһин',
	'checkuser-reason'           => 'Төрүөтэ',
	'checkuser-showlog'          => 'Сурунаалы көрдөр',
	'checkuser-log'              => 'Кыттаачылары бэрэбиэркэлээһин сурунаала',
	'checkuser-query'            => 'Саҥа көннөрүүлэри көрдөр',
	'checkuser-target'           => 'Кыттааччы эбэтэр IP',
	'checkuser-users'            => 'Кыттаачылары ыларга',
	'checkuser-edits'            => 'Бу IP-тан оҥоһуллубут көннөрүүлэри көрөргө',
	'checkuser-ips'              => 'IP-лары көрдөр',
	'checkuser-search'           => 'Көрдөө',
	'checkuser-empty'            => 'Сурунаал кураанах',
	'checkuser-nomatch'          => 'Сөп түбэһиилэр көстүбэтилэр',
	'checkuser-check'            => 'Бэрэбиэркэлээ',
	'checkuser-log-fail'         => 'Сурунаалга сурук эбэр табыллыбат(а)',
	'checkuser-nolog'            => 'Сурунаал билэтэ көстүбэтэ',
	'checkuser-blocked'          => 'Тугу эмэ гынара бобуллубут',
	'checkuser-too-many'         => 'Наһаа элбэх булулунна, бука диэн CIDR кыччатан биэр. Туһаныллыбыт IP (саамай элбэҕэ 5000, бу аадырыһынан наардаммыт):',
	'checkuser-user-nonexistent' => 'Маннык ааттаах кыттааччы суох',
	'checkuser-search-form'      => '$1 сурунаалга $2 буоларын бул',
	'checkuser-search-submit'    => 'Буларга',
	'checkuser-search-initiator' => 'саҕалааччы',
	'checkuser-search-target'    => 'сыал-сорук',
	'checkuser-log-subpage'      => 'Сурунаал',
	'checkuser-log-return'       => 'Кытааччылары бэрэбиэркэлээһин сүрүн сирэйигэр төнүн',
	'checkuser-log-userips'      => '$1 манна анаан $2 IP аадырыстаах',
	'checkuser-log-ipedits'      => '$1 манна анаан $2 көннөрүүлэрдээх',
	'checkuser-log-ipusers'      => '$1 манна анаан $2 ааттардаах (учётные записи)',
	'checkuser-log-ipedits-xff'  => '$1 манна анаан XFF $2 көннөрүүлэрдээх',
	'checkuser-log-ipusers-xff'  => '$1 кыттаачылары ылбыт (для XFF $2)',
);

/** Slovak (Slovenčina)
 * @author Helix84
 * @author Martin Kozák
 */
$messages['sk'] = array(
	'checkuser-summary'          => 'Tento nástroj kontroluje Posledné úpravy, aby získal IP adresy používané používateľom alebo zobrazil úpravy/používateľské dáta IP adresy.
	Používateľov a úpravy je možné získať s XFF IP pridaním „/xff“ k IP. Sú podporované IPv4 (CIDR 16-32) a IPv6 (CIDR 64-128).
	Z dôvodov výkonnosti nebude vrátených viac ako 5000 úprav. Túto funkciu využívajte len v súlade s platnou politikou.',
	'checkuser-desc'             => 'Dáva používateľom s príslušným oprávnením možnosť overovať IP adresu a iné informácie o používateľovi',
	'checkuser-logcase'          => 'Vyhľadávanie v zázname zohľadňuje veľkosť písmen.',
	'checkuser'                  => 'Overiť používateľa',
	'group-checkuser'            => 'Revízor',
	'group-checkuser-member'     => 'Revízori',
	'grouppage-checkuser'        => '{{ns:project}}:Revízia používateľa',
	'checkuser-reason'           => 'Dôvod',
	'checkuser-showlog'          => 'Zobraziť záznam',
	'checkuser-log'              => 'Záznam kontroly používateľov',
	'checkuser-query'            => 'Získať z posledných úprav',
	'checkuser-target'           => 'Používateľ alebo IP',
	'checkuser-users'            => 'Získať používateľov',
	'checkuser-edits'            => 'Získať úpravy z IP',
	'checkuser-ips'              => 'Získať IP adresy',
	'checkuser-search'           => 'Hľadať',
	'checkuser-empty'            => 'Záznam neobsahuje žiadne položky.',
	'checkuser-nomatch'          => 'Žiadny vyhovujúci záznam.',
	'checkuser-check'            => 'Skontrolovať',
	'checkuser-log-fail'         => 'Nebolo možné pridať položku záznamu',
	'checkuser-nolog'            => 'Nebol nájdený súbor záznamu.',
	'checkuser-blocked'          => 'Zablokovaný',
	'checkuser-too-many'         => 'Príliš veľa výsledkov, prosím zúžte CIDR. Tu sú použité IP (max. 5 000, zoradené podľa adresy):',
	'checkuser-user-nonexistent' => 'Uvedený používateľ neexistuje.',
	'checkuser-search-form'      => 'Nájsť položky záznamu, kde $1 je $2',
	'checkuser-search-submit'    => 'Hľadať',
	'checkuser-search-initiator' => 'začínajúci',
	'checkuser-search-target'    => 'cieľ',
	'checkuser-log-subpage'      => 'Záznam',
	'checkuser-log-return'       => 'Vrátiť sa na hlavný formulár CheckUser',
	'checkuser-log-userips'      => '$1 má IP adresy $2',
	'checkuser-log-ipedits'      => '$1 má úpravy $2',
	'checkuser-log-ipusers'      => '$1 má používateľov $2',
	'checkuser-log-ipedits-xff'  => '$1 má úpravy XFF $2',
	'checkuser-log-ipusers-xff'  => '$1 má používateľov XFF $2',
);

$messages['sq'] = array(
	'checkuser'              => 'Kontrollo përdoruesin',
);

/** ћирилица (ћирилица)
 * @author Sasa Stefanovic
 */
$messages['sr-ec'] = array(
	'checkuser'              => 'Чекјузер',
	'group-checkuser'        => 'Чекјузери',
	'group-checkuser-member' => 'Чекјузер',
	'grouppage-checkuser'    => '{{ns:project}}:Чекјузер',
	'checkuser-reason'       => 'Резлог',
	'checkuser-target'       => 'Корисник или ИП',
	'checkuser-search'       => 'Претрага',
	'checkuser-check'        => 'Провера',
	'checkuser-blocked'      => 'Блокиран',
);


$messages['sr-el'] = array(
	'checkuser'              => 'Čekjuzer',
	'group-checkuser'        => 'Čekjuzeri',
	'group-checkuser-member' => 'Čekjuzer',
	'grouppage-checkuser'    => '{{ns:project}}:Čekjuzer',
);

/** Seeltersk (Seeltersk)
 * @author Pyt
 */
$messages['stq'] = array(
	'checkuser-summary'          => 'Disse Reewe truchsäkt do lääste Annerengen, uum ju IP-Adresse fon n Benutser
	blw. do Beoarbaidengen/Benutsernoomen foar ne IP-Adresse fäästtoustaalen. Benutsere un
Beoarbaidengen fon ne IP-Adresse konnen uk ätter Informatione uut do XFF-Headere
	oufräiged wäide, as an ju IP-Adresse n „/xff“ anhonged wäd. (CIDR 16-32) un IPv6 (CIDR 64-128) wäide unnerstutsed.
	Uut Perfomance-Gruunde wäide maximoal 5000 Beoarbaidengen uutroat. Benutsje CheckUser bloot in Uureenstämmenge mäd do Doatenschutsgjuchtlienjen.',
	'checkuser-desc'             => 'Ferlööwet Benutsere mäd do äntspreekende Gjuchte do IP-Adressen as uk wiedere Informatione fon Benutsere tou wröigjen.',
	'checkuser-logcase'          => 'Ju Säike in dät Logbouk unnerschat twiske Groot- un Littikschrieuwen.',
	'checkuser'                  => 'Checkuser',
	'group-checkuser'            => 'Checkusers',
	'group-checkuser-member'     => 'Checkuser-Begjuchtigde',
	'grouppage-checkuser'        => '{{ns:project}}:CheckUser',
	'checkuser-reason'           => 'Gruund',
	'checkuser-showlog'          => 'Logbouk anwiese',
	'checkuser-log'              => 'Checkuser-Logbouk',
	'checkuser-query'            => 'Lääste Annerengen oufräigje',
	'checkuser-target'           => 'Benutser of IP-Adresse',
	'checkuser-users'            => 'Hoal Benutsere',
	'checkuser-edits'            => 'Hoal Beoarbaidengen fon IP-Adresse',
	'checkuser-ips'              => 'Hoal IP-Adressen',
	'checkuser-search'           => 'Säike',
	'checkuser-empty'            => 'Dät Logbouk änthaalt neen Iendraage.',
	'checkuser-nomatch'          => 'Neen Uureenstämmengen fuunen.',
	'checkuser-check'            => 'Uutfiere',
	'checkuser-log-fail'         => 'Logbouk-Iendraach kon nit bietouföiged wäide.',
	'checkuser-nolog'            => 'Neen Logbouk fuunen.',
	'checkuser-blocked'          => 'speerd',
	'checkuser-too-many'         => 'Ju Lieste fon Resultoate is tou loang, gränsje dän IP-Beräk fääre ien. Hier sunt do benutsede IP-Adressen (maximoal 5000, sortierd ätter Adresse):',
	'checkuser-user-nonexistent' => 'Die anroate Benutser bestoant nit.',
	'checkuser-search-form'      => 'Säik Lochboukiendraage, wier $1 $2 is.',
	'checkuser-search-submit'    => 'Säik',
	'checkuser-search-initiator' => 'Initiator',
	'checkuser-search-target'    => 'Siel',
	'checkuser-log-subpage'      => 'Logbouk',
	'checkuser-log-return'       => 'Tourääch ätter dät CheckUser-Haudformular',
	'checkuser-log-userips'      => '$1 hoalde IP-Adressen foar $2',
	'checkuser-log-ipedits'      => '$1 hoalde Beoarbaidengen foar $2',
	'checkuser-log-ipusers'      => '$1 hoalde Benutsere foar $2',
	'checkuser-log-ipedits-xff'  => '$1 hoalde Beoarbaidengen foar XFF $2',
	'checkuser-log-ipusers-xff'  => '$1 hoalde Benutsere foar XFF $2',
);

/** Swedish (Svenska)
 * @author Lejonel
 */
$messages['sv'] = array(
	'checkuser-summary'          => 'Det här verktyget söker igenom de senaste ändringarna för att hämta IP-adresser för en användare, eller redigeringar och användare för en IP-adress.
Användare och redigeringar kan visas med IP-adress från XFF genom att lägga till "/xff" efter IP-adressen. Verktyget stödjer IPv4 (CIDR 16-32) och IPv6 (CIDR 64-128).
På grund av prestandaskäl så visas inte mer än 5000 redigeringar. Använd verktyget i enlighet med policy.',
	'checkuser-desc'             => 'Ger möjlighet för användare med speciell behörighet att kontrollera användares IP-adresser och viss annan information',
	'checkuser-logcase'          => 'Loggsökning är skiftlägeskänslig.',
	'checkuser'                  => 'Kontrollera användare',
	'group-checkuser'            => 'Användarkontrollanter',
	'group-checkuser-member'     => 'Användarkontrollant',
	'grouppage-checkuser'        => '{{ns:project}}:Användarkontrollant',
	'checkuser-reason'           => 'Anledning',
	'checkuser-showlog'          => 'Visa logg',
	'checkuser-log'              => 'Logg över användarkontroller',
	'checkuser-query'            => 'Sök de senaste ändringarna',
	'checkuser-target'           => 'Användare eller IP',
	'checkuser-users'            => 'Hämta användare',
	'checkuser-edits'            => 'Hämta redigeringar från IP-adress',
	'checkuser-ips'              => 'Hämta IP-adresser',
	'checkuser-search'           => 'Sök',
	'checkuser-empty'            => 'Loggen innehåller inga poster.',
	'checkuser-nomatch'          => 'Inga träffar hittades.',
	'checkuser-check'            => 'Kontrollera',
	'checkuser-log-fail'         => 'Loggposten kunde inte läggas i loggfilen.',
	'checkuser-nolog'            => 'Hittade ingen loggfil.',
	'checkuser-blocked'          => 'Blockerad',
	'checkuser-too-many'         => 'För många resultat, du bör söka i ett mindre CIDR-block. Här följer de använda IP-adresserna (högst 5000, sorterade efter adress):',
	'checkuser-user-nonexistent' => 'Användarnamnet som angavs finns inte.',
	'checkuser-search-form'      => 'Sök  efter poster där $1 är $2',
	'checkuser-search-submit'    => 'Sök',
	'checkuser-search-initiator' => 'kontrollanten',
	'checkuser-search-target'    => 'kontrollmålet',
	'checkuser-log-subpage'      => 'Logg',
	'checkuser-log-return'       => 'Gå tillbaka till formuläret för användarkontroll',
	'checkuser-log-userips'      => '$1 hämtade IP-adresser för $2',
	'checkuser-log-ipedits'      => '$1 hämtade redigeringar från $2',
	'checkuser-log-ipusers'      => '$1 hämtade användare från $2',
	'checkuser-log-ipedits-xff'  => '$1 hämtade redigeringar från XFF $2',
	'checkuser-log-ipusers-xff'  => '$1 hämtade användare från XFF $2',
);

/** Telugu (తెలుగు)
 * @author Chaduvari
 * @author Veeven
 * @author వైజాసత్య
 * @author Mpradeep
 */
$messages['te'] = array(
	'checkuser-summary'          => 'ఈ పరికరం ఓ వాడుకరి వాడిన ఐపీలను, లేదా ఒక ఐపీకి చెందిన దిద్దుబాట్లు, వాడుకరుల డేటాను చూపిస్తుంది.
క్లయంటు ఐపీకి చెందిన వాడుకరులు, దిద్దుబాట్లను ఐపీకి /xff అని చేర్చి, XFF హెడర్ల ద్వారా వెలికితీయవచ్చు. IPv4 (CIDR 16-32) and IPv6 (CIDR 64-128) లు పనిచేస్తాయి.
పనితనపు కారణాల వలన 5000 దిద్దుబాట్లకు మించి చూపించము. విధానాల కనుగుణంగా దీన్ని వాడండి.',
	'checkuser-desc'             => 'వాడుకరి ఐపీ అడ్రసు, ఇతర సమాచారాన్ని చూడగలిగే అనుమతులను వాడుకరులకు ఇస్తుంది',
	'checkuser-logcase'          => 'లాగ్ అన్వేషణ కోసం ఇంగ్లీషు అన్వేషకం ఇస్తే.., అది కేస్ సెన్సిటివ్.',
	'checkuser'                  => 'సభ్యుల తనిఖీ',
	'group-checkuser'            => 'సభ్యుల తనిఖీదార్లు',
	'group-checkuser-member'     => 'సభ్యుల తనిఖీదారు',
	'grouppage-checkuser'        => '{{ns:project}}:వాడుకరిని పరిశీలించు',
	'checkuser-reason'           => 'కారణం',
	'checkuser-showlog'          => 'లాగ్ చూపించు',
	'checkuser-log'              => 'వాడుకరిపరిశీలన లాగ్',
	'checkuser-query'            => 'ఇటీవలి మార్పుల్లో చూడండి',
	'checkuser-target'           => 'వాడుకరి లేదా ఐపీ',
	'checkuser-users'            => 'వాడుకరులను తీసుకురా',
	'checkuser-edits'            => 'ఈ ఐపీ అడ్రస్సు నుండి చేసిన మార్పులను చూపించు',
	'checkuser-ips'              => 'ఐపీలను తీసుకురా',
	'checkuser-search'           => 'వెతుకు',
	'checkuser-empty'            => 'లాగ్&zwnj;లో అంశాలేమీ లేవు.',
	'checkuser-nomatch'          => 'సామీప్యాలు ఏమీ కనబడలేదు.',
	'checkuser-check'            => 'తనిఖీ',
	'checkuser-log-fail'         => 'లాగ్&zwnj;లో పద్దుని చేర్చలేకపోయాం',
	'checkuser-nolog'            => 'ఏ లాగ్ ఫైలు కనపడలేదు',
	'checkuser-blocked'          => 'నిరోధించాం',
	'checkuser-too-many'         => 'మరీ ఎక్కువ ఫలితాలొచ్చాయి. CIDR ను మరింత కుదించండి. వాడిన ఐపీలివిగో (గరిష్ఠంగా 5000 -అడ్రసు వారీగా పేర్చి)',
	'checkuser-user-nonexistent' => 'ఆ వాడుకరి ఉనికిలో లేరు.',
	'checkuser-search-form'      => '$1 అనేది $2గా ఉన్న లాగ్ పద్దులను కనుగొనండి',
	'checkuser-search-submit'    => 'వెతుకు',
	'checkuser-search-initiator' => 'ఆరంభకుడు',
	'checkuser-search-target'    => 'లక్ష్యం',
	'checkuser-log-subpage'      => 'లాగ్',
	'checkuser-log-return'       => 'CheckUser ముఖ్య ఫారముకు వెళ్ళు',
	'checkuser-log-userips'      => '$2 కోసం $1 ఐపీలను తెచ్చింది',
	'checkuser-log-ipedits'      => '$2 కోసం $1 దిద్దుబాట్లను తెచ్చింది',
	'checkuser-log-ipusers'      => '$2 కోసం $1 వాడుకరులను తెచ్చింది',
	'checkuser-log-ipedits-xff'  => 'XFF $2 కోసం $1, దిద్దుబాట్లను తెచ్చింది',
	'checkuser-log-ipusers-xff'  => 'XFF $2 కోసం $1, వాడుకరులను తెచ్చింది',
);

/** Tetum (Tetun)
 * @author MF-Warburg
 */
$messages['tet'] = array(
	'checkuser-log'    => 'Lista checkuser',
	'checkuser-target' => "Uza-na'in ka IP",
	'checkuser-users'  => "Uza-na'in sira",
	'checkuser-edits'  => 'Edita husi IP',
	'checkuser-ips'    => 'IP sira',
	'checkuser-search' => 'Buka',
);

/** Tajik (Тоҷикӣ)
 * @author Ibrahim
 */
$messages['tg'] = array(
	'checkuser-logcase'          => 'Ҷустуҷӯи гузориш ба хурд ё бузрг будани ҳарфҳо ҳасос аст.',
	'checkuser'                  => 'Бозрасии корбар',
	'group-checkuser'            => 'Бозрасии корбарон',
	'group-checkuser-member'     => 'Бозрасии корбар',
	'grouppage-checkuser'        => '{{ns:project}}:Бозрасии корбар',
	'checkuser-reason'           => 'Далел',
	'checkuser-showlog'          => 'Намоиши гузориш',
	'checkuser-log'              => 'БозрасиКорбар гузориш',
	'checkuser-query'            => 'Ҷустуҷӯи тағйироти охир',
	'checkuser-target'           => 'Корбар ё нишонаи IP',
	'checkuser-users'            => 'Феҳрист кардани корбарон',
	'checkuser-edits'            => 'Намоиши вироишҳои марбут ба ин нишонаи IP',
	'checkuser-ips'              => 'Феҳрист кардани нишонаҳои IP',
	'checkuser-search'           => 'Ҷустуҷӯ',
	'checkuser-empty'            => 'Гузориш холӣ аст.',
	'checkuser-nomatch'          => 'Мавриде ки мутобиқат дошта бошад пайдо нашуд',
	'checkuser-check'            => 'Барраси',
	'checkuser-log-fail'         => 'Имкони афзудани иттилоот ба гузориш вуҷуд надорад',
	'checkuser-nolog'            => 'Парвандаи гузориш пайдо нашуд.',
	'checkuser-blocked'          => 'Дастрасӣ қатъ шуд',
	'checkuser-too-many'         => 'Теъдоди натоиҷ бисёр зиёд аст. Лутфан CIDRро бориктар кунед. Дар зер нишонаҳои IP-ро мебинед (5000 ҳадди аксар, аз рбатартиби нинона):',
	'checkuser-user-nonexistent' => 'Корбари мавриди назар вуҷуд надорад.',
	'checkuser-search-submit'    => 'Ҷустуҷӯ',
	'checkuser-search-initiator' => 'оғозгар',
	'checkuser-search-target'    => 'ҳадаф',
	'checkuser-log-subpage'      => 'Гузориш',
	'checkuser-log-return'       => 'Бозгашт ба форми аслии бозрасии корбар',
);

/** Tonga (faka-Tonga)
 * @author SPQRobin
 */
$messages['to'] = array(
	'checkuser'              => 'Siviʻi ʻa e ʻetita',
	'group-checkuser'        => 'Siviʻi kau ʻetita',
	'group-checkuser-member' => 'Siviʻi ʻa e ʻetita',
);

/** Turkish (Türkçe)
 * @author Erkan Yilmaz
 * @author SPQRobin
 * @author Karduelis
 * @author Dbl2010
 */
$messages['tr'] = array(
	'checkuser'                  => 'IP denetçisi',
	'group-checkuser'            => 'Denetçiler',
	'group-checkuser-member'     => 'Denetçi',
	'grouppage-checkuser'        => '{{ns:project}}:Denetçi',
	'checkuser-reason'           => 'Sebep',
	'checkuser-showlog'          => 'Logu göster',
	'checkuser-target'           => 'Kullanıcı veya IP',
	'checkuser-users'            => 'Kullanıcıları bulup getir',
	'checkuser-ips'              => 'IPleri bulup getir',
	'checkuser-search'           => 'Ara',
	'checkuser-check'            => 'Kontrol et',
	'checkuser-blocked'          => 'Engellendi',
	'checkuser-search-submit'    => 'Ara',
	'checkuser-search-initiator' => 'Başlatan',
	'checkuser-search-target'    => 'Hedef',
);

/** Vietnamese (Tiếng Việt)
 * @author Vinhtantran
 * @author Minh Nguyen
 */
$messages['vi'] = array(
	'checkuser-summary'          => 'Công cụ này sẽ quét các thay đổi gần đây để lấy ra các IP được một thành viên sử dụng hoặc hiển thị dữ liệu sửa đổi/tài khoản của một IP. Các tài khoản và sửa đổi của một IP có thể được trích ra từ tiêu đề XFF bằng cách thêm vào IP “/xff”. IPv4 (CIDR 16-32) và IPv6 (CIDR 64-128) đều được hỗ trợ. Không quá 5000 sửa đổi sẽ được trả về vì lý do hiệu suất. Hãy dùng công cụ này theo đúng quy định.',
	'checkuser-desc'             => 'Cung cấp cho những người đủ tiêu chuẩn khả năng kiểm tra địa chỉ IP và thông tin khác của người dùng khác',
	'checkuser-logcase'          => 'Tìm kiếm nhật trình có phân biệt chữ hoa chữ thường',
	'checkuser'                  => 'Kiểm tra thành viên',
	'group-checkuser'            => 'Kiểm tra thành viên',
	'group-checkuser-member'     => 'Kiểm tra thành viên',
	'grouppage-checkuser'        => '{{ns:project}}:Kiểm tra thành viên',
	'checkuser-reason'           => 'Lý do',
	'checkuser-showlog'          => 'Xem nhật trình',
	'checkuser-log'              => 'Nhật trình Kiểm tra thành viên',
	'checkuser-query'            => 'Truy vấn các thay đổi gần đây',
	'checkuser-target'           => 'Thành viên hay IP',
	'checkuser-users'            => 'Lấy ra thành viên',
	'checkuser-edits'            => 'Lấy ra sửa đổi của IP',
	'checkuser-ips'              => 'Lấy ra IP',
	'checkuser-search'           => 'Tìm kiếm',
	'checkuser-empty'            => 'Nhật trình hiện chưa có gì.',
	'checkuser-nomatch'          => 'Không tìm thấy kết quả.',
	'checkuser-check'            => 'Kiểm tra',
	'checkuser-log-fail'         => 'Không thể ghi vào nhật trình',
	'checkuser-nolog'            => 'Không tìm thấy tập tin nhật trình',
	'checkuser-blocked'          => 'Đã cấm',
	'checkuser-too-many'         => 'Có quá nhiều kết quả, xin hãy thu hẹp CIDR. Đây là các IP sử dụng (tối đa 5000, xếp theo địa chỉ):',
	'checkuser-user-nonexistent' => 'Thành viên chỉ định không tồn tại.',
	'checkuser-search-form'      => 'Tìm thấy các mục nhật trình trong đó $1 là $2',
	'checkuser-search-submit'    => 'Tìm kiếm',
	'checkuser-search-initiator' => 'người khởi đầu',
	'checkuser-search-target'    => 'mục tiêu',
	'checkuser-log-subpage'      => 'Nhật trình',
	'checkuser-log-return'       => 'Trả về biểu mẫu chính Kiểm tra thành viên',
	'checkuser-log-userips'      => '$1 lấy IP để $2',
	'checkuser-log-ipedits'      => '$1 lấy sửa đổi cho $2',
	'checkuser-log-ipusers'      => '$1 lấy thành viên cho $2',
	'checkuser-log-ipedits-xff'  => '$1 lấy sửa đổi cho XFF $2',
	'checkuser-log-ipusers-xff'  => '$1 lấy thành viên cho XFF $2',
);

/** Volapük (Volapük)
 * @author Smeira
 * @author Malafaya
 */
$messages['vo'] = array(
	'checkuser-summary'          => 'Stum at vestigon votükamis brefabüik ad dagetön ladetis-IP fa geban semik pagebölis, ud ad jonön redakama- u gebananünis tefü ladet-IP semik.
Gebans e redakams se dona-IP kanons pagetön de tiäds: XFF medä läükoy eli „/xff“ ladete-IP. Els IPv4 (CIDR 16-32) e IPv6 (CIDR 64-128) kanons pagebön.
Redakams no plu 5000 pejonons sekü kods kaenavik. Gebolös stumi at bai nomem.',
	'checkuser-desc'             => 'Gevon gebanes labü däl zesüdik fägi ad vestigön ladeti(s)-IP gebana äsi nünis votik',
	'checkuser-logcase'          => 'Pö suk in registar mayuds e minuds padifükons.',
	'checkuser'                  => 'Vestigön gebani',
	'group-checkuser'            => 'Vestigön gebanis',
	'group-checkuser-member'     => 'Vestigön gebani',
	'grouppage-checkuser'        => '{{ns:project}}:Vestigön gebani',
	'checkuser-reason'           => 'Kod',
	'checkuser-showlog'          => 'Jonön jenotalisedi',
	'checkuser-log'              => 'Vestigön gebani: jenotalised',
	'checkuser-query'            => 'Vestigön votükamis brefabüik',
	'checkuser-target'           => 'Geban u ladet-IP',
	'checkuser-users'            => 'Tuvön gebanis',
	'checkuser-edits'            => 'Tuvön redakamis ladeta-IP',
	'checkuser-ips'              => 'Tuvön ladetis-IP',
	'checkuser-search'           => 'Sukolöd',
	'checkuser-empty'            => 'Lised vagon.',
	'checkuser-nomatch'          => 'Suk no eplöpon.',
	'checkuser-check'            => 'Vestigön',
	'checkuser-log-fail'         => 'No eplöpos ad laükön jenotalisede',
	'checkuser-nolog'            => 'Ragiv jenotaliseda no petuvon.',
	'checkuser-blocked'          => 'Peblokon',
	'checkuser-too-many'         => 'Sukaseks te mödiks, nedol gebön eli CIDR smalikum. Is palisedons ladets-IP pegeböl (jü 5000, peleodüköls ma ladet):',
	'checkuser-user-nonexistent' => 'Geban at no dabinon.',
	'checkuser-search-form'      => 'Tuvön lienis jenotaliseda, kö $1 binon $2',
	'checkuser-search-submit'    => 'Suk',
	'checkuser-search-initiator' => 'flagan',
	'checkuser-search-target'    => 'zeil',
	'checkuser-log-subpage'      => 'Jenotalised',
	'checkuser-log-return'       => 'Geikön lü cifafomet',
	'checkuser-log-userips'      => '$1 labon ladetis-IP ela $2',
	'checkuser-log-ipedits'      => '$1 labon redakamis ela $2',
	'checkuser-log-ipusers'      => '$1 labon gebanis ela $2',
	'checkuser-log-ipedits-xff'  => '$1 labon redakamis ela XFF $2',
	'checkuser-log-ipusers-xff'  => '$1 labon gebanis ela XFF $2',
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

