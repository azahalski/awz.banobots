<?php

namespace Awz\Banobots;

use Bitrix\Main\Service\GeoIp\Manager;
use Bitrix\Main\Config\Option;

class Handlers
{
    const MODULE_ID = 'awz.banobots';

    protected static function getOptionList($name, $default = '')
    {
        $val = trim((string)Option::get(self::MODULE_ID, $name, $default, ""));
        if ($val === '' && $default !== '') {
            $val = $default;
        }
        $ar = array();
        foreach (preg_split('/[\r\n]+/', $val) as $line) {
            foreach (explode(',', $line) as $item) {
                $item = trim((string)$item);
                if ($item !== '') {
                    $ar[] = $item;
                }
            }
        }
        return $ar;
    }

    protected static function maskMatch($subject, $pattern, $mode = 'contains')
    {
        $pattern = trim((string)$pattern);
        if ($pattern === '') return false;
        $subjectLower = mb_strtolower((string)$subject, 'UTF-8');
        $patternLower = mb_strtolower($pattern, 'UTF-8');
        if (strpos($pattern, '*') !== false) {
            $regex = '/^' . str_replace('\*', '.*', preg_quote($patternLower, '/')) . '$/u';
            return (bool)preg_match($regex, $subjectLower);
        }
        if ($mode === 'exact') {
            return $subjectLower === $patternLower;
        }
        return strpos($subjectLower, $patternLower) !== false;
    }

    protected static function isBanEnabled()
    {
        if (Option::get(self::MODULE_ID, 'BAN_ENABLED', 'N', "") !== 'Y') return false;

        // Ограничение работы модуля по дате (пусто - без ограничения)
        $banUntil = trim((string)Option::get(self::MODULE_ID, 'BAN_UNTIL_DATE', '', ""));
        if ($banUntil !== '') {
            $ts = MakeTimeStamp($banUntil, 'DD.MM.YYYY');
            if ($ts > 0 && $ts < time()) {
                return false;
            }
        }
        return true;
    }

    protected static function incrementOptionCounter($name)
    {
        try {
            $val = (int)Option::get(self::MODULE_ID, $name, '0', "");
            Option::set(self::MODULE_ID, $name, (string)($val + 1), "");
        } catch (\Exception $e) {
        }
    }

    protected static function incrementBlockedRequests()
    {
        self::incrementOptionCounter('COUNTER_BLOCKED_REQUESTS');
    }

    protected static function isAllowedLogFile($file)
    {
        if ($file === '') return false;
        $ext = mb_strtolower(pathinfo($file, PATHINFO_EXTENSION));
        return in_array($ext, array('txt', 'log'), true);
    }

    protected static function sanitizeLogValue($value)
    {
        // удаляем переводы строк, управляющие символы, кавычки и HTML-теги - защита от log injection и XSS
        $value = str_replace(array('"', "'", '`', '<', '>'), '', (string)$value);
        return preg_replace('/[\x00-\x1F\x7F]/u', ' ', $value);
    }

    protected static function appendLog($file, $line)
    {
        // разрешены только файлы .txt и .log
        if (!self::isAllowedLogFile($file)) return;
        $line = self::sanitizeLogValue($line);
        if ($line === '') return;
        file_put_contents($file, $line . PHP_EOL, FILE_APPEND | LOCK_EX);

        // Ограничение размера лога (в Mb). При превышении удаляются первые строки
        $maxSizeMb = (float)Option::get(self::MODULE_ID, 'LOG_MAX_SIZE_MB', '10', "");
        if ($maxSizeMb <= 0) return;
        $maxBytes = (int)round($maxSizeMb * 1024 * 1024);
        clearstatcache(true, $file);
        $size = filesize($file);
        if ($size === false || $size <= $maxBytes) return;

        $content = file_get_contents($file);
        if ($content === false) return;
        $lines = explode(PHP_EOL, $content);
        // удаляем строки с начала, пока не впишемся в лимит (минус запас на новую строку)
        while (count($lines) > 1) {
            array_shift($lines);
            $newSize = strlen(implode(PHP_EOL, $lines));
            if ($newSize <= $maxBytes) break;
        }
        file_put_contents($file, implode(PHP_EOL, $lines), LOCK_EX);
    }

    public static function autoBanByCountryAllowList()
    {
        // Не срабатываем в админке и при запуске скриптов из консоли (cron)
        if ((defined("ADMIN_SECTION") && ADMIN_SECTION === true) || php_sapi_name() === 'cli') {
            return;
        }

        if (!self::isBanEnabled()) return;

        $requestUri = $_SERVER['REQUEST_URI'] ?? '';

        // Ограничение по URL (подстроки или маски со * с новой строки). Пусто - правило действует на весь сайт.
        // Влияет только на проверку по странам; детекторы и кастомный обработчик работают всегда
        $arUrls = self::getOptionList('URL_FILTER');
        $urlMatch = true;
        if (!empty($arUrls)) {
            $urlMatch = false;
            foreach ($arUrls as $urlPart) {
                if (self::maskMatch($requestUri, $urlPart, 'contains')) {
                    $urlMatch = true;
                    break;
                }
            }
        }

        // Режим работы по странам: all - банить все страны, allow - белый список, deny - черный список
        $countryMode = trim((string)Option::get(self::MODULE_ID, 'COUNTRY_MODE', 'allow', ""));
        if (!in_array($countryMode, array('all', 'allow', 'deny'), true)) {
            $countryMode = 'allow';
        }

        // Списки кодов стран через запятую
        $allowListCountries = array_map('strtoupper', self::getOptionList('COUNTRY_ALLOW_LIST', 'RU,BY,KZ,N0'));
        $denyListCountries = array_map('strtoupper', self::getOptionList('COUNTRY_DENY_LIST'));

        // Белый список IP (ваш IP, IP сервера, чтобы случайно не заблокировать себя)
        $whiteListIps = self::getOptionList('IP_WHITE_LIST', '127.0.0.1');

        // Пути файлов логов (только абсолютные пути вне web-root; пусто - не вести лог)
        $banFile = trim((string)Option::get(self::MODULE_ID, 'LOG_FILE_BAN', '', ""));
        $banBots = trim((string)Option::get(self::MODULE_ID, 'LOG_FILE_BOTS', '', ""));
        $banFileCountry = trim((string)Option::get(self::MODULE_ID, 'LOG_FILE_COUNTRY', '', ""));
        $docRoot = str_replace('\\', '/', rtrim((string)realpath($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\'));
        foreach (array('banFile' => &$banFile, 'banBots' => &$banBots, 'banFileCountry' => &$banFileCountry) as $key => &$logPath) {
            if ($logPath === '') { continue; }
            // любой относительный путь разрешается от DOCUMENT_ROOT, т.е. попадает в web-root — запрещаем
            if ($logPath[0] !== '/' && !preg_match('#^[a-zA-Z]:[\\\\/]#', $logPath)) {
                $logPath = '';
                continue;
            }
            // нормализуем путь без обращения к несуществующему каталогу
            $normalized = str_replace('\\', '/', $logPath);
            if ($docRoot !== '' && strpos($normalized, $docRoot.'/') === 0) {
                // внутри web-root писать нельзя даже в несуществующий каталог
                $logPath = '';
                continue;
            }
            $logDir = dirname($logPath);
            if (self::isAllowedLogFile($logPath) && $logDir && !is_dir($logDir)) {
                @mkdir($logDir, defined('BX_DIR_PERMISSIONS') ? BX_DIR_PERMISSIONS : 0755, true);
            }
        }
        unset($logPath);

        $banBotsValues = self::getOptionList('BOT_BLACK_LIST', 'SERankingBacklinksBot,SemrushBot,Baiduspider,meta-externalagent,applebot');
        $whiteListBots = self::getOptionList('BOT_WHITE_LIST', 'google,yandex');

        // 1. Получаем реальный IP посетителя
        $ip = class_exists(Manager::class) ? Manager::getRealIp() : ($_SERVER['REMOTE_ADDR'] ?? '');

        // 1.5 Определяем страну принадлежности IP (до всех проверок, чтобы писать её в баны)
        $countryCode = '';
        if (\Bitrix\Main\Loader::includeModule('statistic')) {
            $obCity = new \CCity();
            $arCity = $obCity->GetFullInfo();
            if($arCity && is_array($arCity)) {
                $countryCode = $arCity['COUNTRY_CODE']['VALUE'] ?? null;
            }
        }

        $countryCode = strtoupper(trim((string)$countryCode));

        if((!$countryCode || $countryCode=='N0') && \Bitrix\Main\Loader::includeModule('awz.iptown')){
            $townRes = \Awz\IpTown\Geo::getData();
            if($townRes->isSuccess()){
                $countryCode = $townRes->getCountryIso();
            }
        }

        // неопределенная страна - псевдокод N0, чтобы её можно было разрешать/банировать в списках
        $countryCode = ($countryCode === '') ? 'N0' : $countryCode;

        // 0. Проверка наличия активной блокировки в таблице (сперва таблица, потом правила)
        if ($ip !== '') {
            try {
                $banDays = (int)Option::get(self::MODULE_ID, 'BAN_DAYS', '0', "");
                $arFilter = array('=IP' => $ip, '=ACTIVE' => 'Y');
                if ($banDays > 0) {
                    $arFilter['>=DATE_BAN'] = \Bitrix\Main\Type\DateTime::createFromTimestamp(time() - $banDays * 86400);
                }
                $dbBan = Tables\BanTable::getList(array(
                    'filter' => $arFilter,
                    'select' => array('ID'),
                    'limit' => 1
                ));
                if ($dbBan->fetch()) {
                    self::incrementBlockedRequests();
                    header('HTTP/1.1 403 Forbidden');
                    die('Access denied. Ban');
                }
            } catch (\Exception $e) {
            } catch (\Throwable $e) {
            }
        }

        // Проверка белого списка IP (поддержка масок со *, без учета регистра)
        if (in_array(mb_strtolower($ip), array_map('mb_strtolower', $whiteListIps), true)) {
            return;
        }
        foreach ($whiteListIps as $whiteIp) {
            if (strpos($whiteIp, '*') !== false && self::maskMatch($ip, $whiteIp, 'exact')) {
                return;
            }
        }

        // 2. Пропускаем поисковых роботов (Google ходит с US-адресов и без этого попадет в бан)
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        foreach($whiteListBots as $whiteWord){
            if(self::maskMatch($userAgent, $whiteWord, 'contains')){
                return;
            }
        }
        foreach($banBotsValues as $banWord){
            if(self::maskMatch($userAgent, $banWord, 'contains')){
                self::appendLog($banBots, $ip);
                self::addBanRecord($ip, $countryCode, $userAgent);
                self::incrementBlockedRequests();
                header('HTTP/1.1 403 Forbidden');
                die('Access denied. '.$banWord);
            }
        }

        // Опция: бан пустого/отсутствующего User-Agent
        if (Option::get(self::MODULE_ID, 'BAN_EMPTY_UA', 'N', "") === 'Y') {
            if (trim($userAgent) === '') {
                self::addBanRecord($ip, $countryCode, $userAgent);
                self::incrementBlockedRequests();
                header('HTTP/1.1 403 Forbidden');
                die('Access denied. Empty UA');
            }
        }

        // Опция: популярные пути сканеров и взломщиков
        if (Option::get(self::MODULE_ID, 'BAN_SCANNER_PATHS', 'N', "") === 'Y') {
            $scannerPaths = self::getOptionList('SCANNER_PATHS',
                'wp-admin,wp-login.php,xmlrpc.php,.env,.git,phpmyadmin,adminer,.well-known/security.txt'
            );
            foreach ($scannerPaths as $scannerPath) {
                if (self::maskMatch($requestUri, $scannerPath, 'contains')) {
                    self::addBanRecord($ip, $countryCode, $userAgent);
                    self::incrementBlockedRequests();
                    header('HTTP/1.1 403 Forbidden');
                    die('Access denied. Scanner');
                }
            }
        }

        // Опция: подозрительные query-параметры (SQL-инъекции, обход директорий, probes)
        if (Option::get(self::MODULE_ID, 'BAN_SUSPECT_QUERY', 'N', "") === 'Y') {
            $queryString = $_SERVER['QUERY_STRING'] ?? '';
            if ($queryString !== '') {
                $queryString = urldecode($queryString);
                $suspectPatterns = self::getOptionList('SUSPECT_QUERY_PATTERNS',
                    'union select,select * from,information_schema,sleep(,benchmark(,load_file,../,..\\,/etc/passwd,<?php,eval(,base64_decode'
                );
                foreach ($suspectPatterns as $pattern) {
                    if ($pattern !== '' && self::maskMatch($queryString, $pattern, 'contains')) {
                        self::addBanRecord($ip, $countryCode, $userAgent);
                        self::incrementBlockedRequests();
                        header('HTTP/1.1 403 Forbidden');
                        die('Access denied. Suspect query');
                    }
                }
            }
        }

        $requestUri = $_SERVER['REQUEST_URI'] ?? '';

        // 3.5 Событие для разработчиков: возможность принудительного бана по своим правилам
        try {
            $event = new \Bitrix\Main\Event(
                self::MODULE_ID,
                'onCheckBan',
                array(
                    'ip' => $ip,
                    'user_agent' => $userAgent,
                    'page' => $requestUri,
                    'country' => $countryCode
                )
            );
            $event->send();
            if ($event->getResults()) {
                foreach ($event->getResults() as $eventResult) {
                    if ($eventResult->getType() == \Bitrix\Main\EventResult::SUCCESS) {
                        $r = $eventResult->getParameters();
                        if (isset($r['ban']) && $r['ban']) {
                            self::appendLog($banFile, $ip);
                            self::appendLog($banFileCountry, $countryCode.':'.$ip.' | UA: '.$userAgent);
                            self::addBanRecord($ip, $countryCode, $userAgent);
                            self::incrementBlockedRequests();
                            header('HTTP/1.1 403 Forbidden');
                            die('Access denied. Event ban');
                        }
                    }
                }
            }
        } catch (\Exception $e) {
        } catch (\Throwable $e) {
        }

        // 4. Проверка по режиму стран (только здесь действует ограничение URL_FILTER)
        $needBan = false;
        if ($urlMatch) {
            if ($countryMode === 'all') {
                // бан всех стран, кроме неопределенной (N0)
                $needBan = ($countryCode && $countryCode !== 'N0');
            } elseif ($countryMode === 'allow') {
                // бан, если страна НЕ входит в белый список (N0 - не определена)
                $needBan = !in_array($countryCode, $allowListCountries, true);
            } elseif ($countryMode === 'deny') {
                // бан, если страна входит в черный список
                $needBan = in_array($countryCode, $denyListCountries, true);
            }
        }

        if ($needBan) {

            self::appendLog($banFile, $ip);
            self::appendLog($banFileCountry, $countryCode.':'.$ip.' | UA: '.$userAgent);
            self::addBanRecord($ip, $countryCode, $userAgent);
            self::incrementBlockedRequests();

            // Немедленно обрываем текущий запрос
            header('HTTP/1.1 403 Forbidden');
            die('Access denied. '.$countryCode);
        }
    }

    protected static function addBanRecord($ip, $countryCode, $userAgent)
    {
        $page = $_SERVER['REQUEST_URI'] ?? '';
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        try {
            // уникальный IP за все время
            if ($ip !== '') {
                $exists = Tables\BanTable::getList(array(
                    'filter' => array('=IP' => $ip),
                    'select' => array('ID'),
                    'limit' => 1
                ))->fetch();
                if (!$exists) {
                    self::incrementOptionCounter('COUNTER_BANNED_IPS');
                }
            }
            Tables\BanTable::add(array(
                'DATE_BAN' => new \Bitrix\Main\Type\DateTime(),
                'IP' => mb_substr(self::sanitizeLogValue($ip), 0, 50),
                'COUNTRY' => mb_substr(self::sanitizeLogValue($countryCode), 0, 10),
                'USER_AGENT' => mb_substr(self::sanitizeLogValue($userAgent), 0, 500),
                'PAGE' => mb_substr(self::sanitizeLogValue($page), 0, 500),
                'REFERER' => mb_substr(self::sanitizeLogValue($referer), 0, 500),
                'ACTIVE' => 'Y'
            ));
        } catch (\Exception $e) {
        } catch (\Throwable $e) {
        }
    }

    public static function cleanBanTableAgent()
    {
        try {
            $banDays = (int)Option::get(self::MODULE_ID, 'BAN_DAYS', '0', "");
            if ($banDays <= 0) {
                $banDays = 90;
            }
            $dateLimit = \Bitrix\Main\Type\DateTime::createFromTimestamp(time() - $banDays * 86400);
            Tables\BanTable::deleteByFilter(array('<DATE_BAN' => $dateLimit));
        } catch (\Exception $e) {
        }
        return '\Awz\Banobots\Handlers::cleanBanTableAgent();';
    }
}
