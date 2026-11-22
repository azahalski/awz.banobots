<?php

namespace Awz\Banobots;

use Bitrix\Main\Service\GeoIp\Manager;

class Handlers
{
    public static function autoBanByCountryAllowList()
    {
        // Не срабатываем в админке и при запуске скриптов из консоли (cron)
        if ((defined("ADMIN_SECTION") && ADMIN_SECTION === true) || php_sapi_name() === 'cli') {
            return;
        }

        // 2. Проверяем, что запрос идет в раздел /catalog/
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($requestUri, '/catalog/') === false) {
            //return;
        }

        if(strtotime("25.11.2026")<time()) return;

        // БЕЛЫЙ СПИСОК разрешенных стран (все остальные будут заблокированы)
        $allowListCountries = ['BY', 'RU', 'N0'];

        // Белый список IP (ваш IP, IP сервера, чтобы случайно не заблокировать себя)
        $whiteListIps = ['127.0.0.1', '80.74.26.150'];

        $banFile = '/home/ip-ban-4.txt';
        $banBots = '/home/ip-ban-bots.txt';
        $banBotsValues = [
            'SERankingBacklinksBot','SemrushBot','Baiduspider','meta-externalagent', 'applebot'
        ];
        $whiteListBots = ['google','yandex'];
        $banFileCountry = '/home/ip-ban-4-country.txt';

        // 1. Получаем реальный IP посетителя
        $ip = class_exists(Manager::class) ? Manager::getRealIp() : ($_SERVER['REMOTE_ADDR'] ?? '');

        // Проверка белого списка IP
        if (in_array($ip, $whiteListIps, true)) {
            return;
        }

        // 2. Пропускаем поисковых роботов (Google ходит с US-адресов и без этого попадет в бан)
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        foreach($whiteListBots as $whiteWord){
            if(stripos($userAgent, $whiteWord) !== false){
                return;
            }
        }
        foreach($banBotsValues as $banWord){
            if(stripos($userAgent, $banWord) !== false){
                file_put_contents($banBots, $ip . PHP_EOL, FILE_APPEND | LOCK_EX);
                header('HTTP/1.1 403 Forbidden');
                die('Access denied. '.$banWord);
            }
        }
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

        // 4. Если страна НЕ входит в белый список (или не определилась) — записываем в файл и баним
        if ($countryCode && !in_array($countryCode, $allowListCountries, true)) {

            file_put_contents($banFile, $ip . PHP_EOL, FILE_APPEND | LOCK_EX);
            file_put_contents($banFileCountry, $countryCode.':'.$ip.' | UA: '.$userAgent . PHP_EOL, FILE_APPEND | LOCK_EX);

            // Немедленно обрываем текущий запрос
            header('HTTP/1.1 403 Forbidden');
            die('Access denied. '.$countryCode);
        }
    }
}
