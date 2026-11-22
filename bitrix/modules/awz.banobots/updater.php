<?php
/**
 * @var CUpdater $updater
 */
$moduleId = 'awz.banobots';
global $DB;
// Проверяем, существует ли папка с js в самом обновлении
if(\Bitrix\Main\ModuleManager::isModuleInstalled($moduleId)){
    if (is_dir(__DIR__ . '/install/js'))
    {
        $updater->CopyFiles("install/js", "bitrix/js/".$moduleId);
    }
    if (is_dir(__DIR__ . '/install/css'))
    {
        $updater->CopyFiles("install/css", "bitrix/css/".$moduleId);
    }
    if (is_dir(__DIR__ . '/install/admin'))
    {
        $updater->CopyFiles("install/admin", "bitrix/admin");
    }

    // миграция таблицы банов: колонки PAGE и REFERER для версий 1.0.2
    if ($DB->TableExists('awz_banobots_ban')) {
        $arFields = $DB->GetTableFields('awz_banobots_ban');
        if ($arFields && !array_key_exists('PAGE', $arFields)) {
            if ($DB->type === 'pgsql') {
                $DB->Query("ALTER TABLE awz_banobots_ban ADD PAGE VARCHAR(500) DEFAULT ''");
            } else {
                $DB->Query("ALTER TABLE awz_banobots_ban ADD PAGE VARCHAR(500) NOT NULL DEFAULT ''");
            }
        }
        if ($arFields && !array_key_exists('REFERER', $arFields)) {
            if ($DB->type === 'pgsql') {
                $DB->Query("ALTER TABLE awz_banobots_ban ADD REFERER VARCHAR(500) DEFAULT ''");
            } else {
                $DB->Query("ALTER TABLE awz_banobots_ban ADD REFERER VARCHAR(500) NOT NULL DEFAULT ''");
            }
        }
    }

    // регистрация агента очистки таблицы банов для версий 1.0.2
    $agentName = '\Awz\Banobots\Handlers::cleanBanTableAgent();';
    $dbAgent = \CAgent::GetList(
        array('ID' => 'DESC'),
        array('MODULE_ID' => $moduleId, 'NAME' => $agentName)
    );
    if (!$dbAgent->Fetch()) {
        \CAgent::AddAgent(
            $agentName,
            $moduleId,
            'N',
            86400,
            '',
            'Y',
            ConvertTimeStamp(time()+60, 'FULL')
        );
    }
}
