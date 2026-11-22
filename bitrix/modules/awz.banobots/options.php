<?php
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");
CModule::IncludeModule('main');

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Main\Application;
use Bitrix\Main\UI\Extension;
use Awz\Banobots\Access\AccessController;

Loc::loadMessages(__FILE__);
global $APPLICATION;
$module_id = "awz.banobots";
if(!Loader::includeModule($module_id)) return;
Extension::load('ui.sidepanel-content');
$request = Application::getInstance()->getContext()->getRequest();
$APPLICATION->SetTitle(Loc::getMessage('AWZ_BANOBOTS_OPT_TITLE'));

if($request->get('IFRAME_TYPE')==='SIDE_SLIDER'){
    require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");
    require_once('lib/access/include/moduleright.php');
    CMain::finalActions();
    die();
}

if(!AccessController::isViewSettings())
    $APPLICATION->AuthForm(Loc::getMessage("ACCESS_DENIED"));
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");

$aTabs = array();

$aTabs[] = array(
    "DIV" => "edit1",
    "TAB" => Loc::getMessage('AWZ_BANOBOTS_OPT_TAB_MAIN'),
    "ICON" => "vote_settings",
    "TITLE" => Loc::getMessage('AWZ_BANOBOTS_OPT_TAB_MAIN')
);

$aTabs[] = array(
    "DIV" => "edit2",
    "TAB" => Loc::getMessage('AWZ_BANOBOTS_OPT_TAB_ADDITIONAL'),
    "ICON" => "vote_settings",
    "TITLE" => Loc::getMessage('AWZ_BANOBOTS_OPT_TAB_ADDITIONAL')
);

$aTabs[] = array(
    "DIV" => "edit3",
    "TAB" => Loc::getMessage('AWZ_BANOBOTS_OPT_TAB_LOG'),
    "ICON" => "vote_settings",
    "TITLE" => Loc::getMessage('AWZ_BANOBOTS_OPT_TAB_LOG')
);

$saveUrl = $APPLICATION->GetCurPage(false).'?mid='.htmlspecialcharsbx($module_id).'&lang='.LANGUAGE_ID.'&mid_menu=1';
$tabControl = new CAdminTabControl("tabControl", $aTabs);

if ($request->getRequestMethod()==='POST' && AccessController::isEditSettings() && $request->get('ClearBans'))
{
    try {
        \Awz\Banobots\Tables\BanTable::deleteByFilter(array('!ID' => 0));
        Option::set($module_id, 'BANS_CLEARED', 'Y', "");
    } catch (\Exception $e) {
    }
    LocalRedirect($saveUrl.'&bans_cleared=Y');
}

if ($request->getRequestMethod()==='POST' && AccessController::isEditSettings() && $request->get('Update'))
{
    $optionNames = array(
        'BAN_ENABLED', 'COUNTRY_MODE', 'COUNTRY_ALLOW_LIST', 'COUNTRY_DENY_LIST',
        'IP_WHITE_LIST', 'BOT_BLACK_LIST', 'BOT_WHITE_LIST', 'URL_FILTER',
        'LOG_FILE_BAN', 'LOG_FILE_BOTS', 'LOG_FILE_COUNTRY', 'BAN_UNTIL_DATE', 'LOG_MAX_SIZE_MB', 'BAN_DAYS',
        'BAN_EMPTY_UA', 'BAN_SCANNER_PATHS', 'SCANNER_PATHS', 'BAN_SUSPECT_QUERY', 'SUSPECT_QUERY_PATTERNS'
    );
    foreach($optionNames as $optName){
        $val = (string)$request->get($optName);
        Option::set($module_id, $optName, $val, "");
    }
    LocalRedirect($saveUrl.'&updated=Y');
}

$tabControl->Begin();
?>
    <style>.adm-workarea option:checked {background-color: rgb(206, 206, 206);}</style>
    <form method="POST" action="<?=$saveUrl?>" id="FORMACTION">
        <?
        $tabControl->BeginNextTab();
        Extension::load("ui.alerts");
        if($request->get('bans_cleared')==='Y'){
            ?>
            <tr><td colspan="2"><div class="ui-alert ui-alert-success"><span class="ui-alert-message"><?=Loc::getMessage('AWZ_BANOBOTS_OPT_BANS_CLEARED')?></span></div></td></tr>
            <?
        }
        if($request->get('updated')==='Y'){
            ?>
            <tr><td colspan="2"><div class="ui-alert ui-alert-success"><span class="ui-alert-message"><?=Loc::getMessage('AWZ_BANOBOTS_OPT_SAVED')?></span></div></td></tr>
            <?
        }
        ?>
        <tr>
            <td style="width:200px;"></td>
            <td>
                <div class="ui-alert ui-alert-primary">
                    <span class="ui-alert-message">
                        <?=Loc::getMessage('AWZ_BANOBOTS_OPT_SHOW_DESC')?>
                    </span>
                </div>
            </td>
        </tr>

        <?
        $bansCount = 0;
        $bansTotal = 0;
        $cntIps = (int)Option::get($module_id, 'COUNTER_BANNED_IPS', '0', "");
        $cntReq = (int)Option::get($module_id, 'COUNTER_BLOCKED_REQUESTS', '0', "");
        try {
            $bansCount = (int)\Awz\Banobots\Tables\BanTable::getCount(array('=ACTIVE' => 'Y'));
            $bansTotal = (int)\Awz\Banobots\Tables\BanTable::getCount(array());
        } catch (\Exception $e) {
        }
        ?>
        <tr>
            <td style="width:200px;"></td>
            <td>
                <style>
                    .awz-banobots-stats {display:flex;flex-wrap:wrap;gap:12px;margin:6px 0 14px;}
                    .awz-banobots-stats .stat-box {
                        flex:1 1 180px;max-width:220px;padding:14px 18px;border-radius:8px;
                        background:#eef4fb;border:1px solid #d5e3f1;
                    }
                    .awz-banobots-stats .stat-box.red {background:#fdeeee;border-color:#f1d3d3;}
                    .awz-banobots-stats .stat-box.green {background:#eef8ef;border-color:#d3ecd6;}
                    .awz-banobots-stats .stat-value {font-size:24px;font-weight:bold;color:#1d2939;line-height:1.2;}
                    .awz-banobots-stats .stat-label {font-size:12px;color:#667085;margin-top:4px;}
                </style>
                <div class="awz-banobots-stats">
                    <div class="stat-box red">
                        <div class="stat-value"><?=intval($bansCount)?></div>
                        <div class="stat-label"><?=Loc::getMessage('AWZ_BANOBOTS_OPT_BANS_ACTIVE')?></div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value"><?=intval($cntIps)?></div>
                        <div class="stat-label"><?=Loc::getMessage('AWZ_BANOBOTS_OPT_CNT_IPS')?></div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value"><?=intval($cntReq)?></div>
                        <div class="stat-label"><?=Loc::getMessage('AWZ_BANOBOTS_OPT_CNT_REQUESTS')?></div>
                    </div>
                    <div class="stat-box green">
                        <div class="stat-value"><?=intval($bansTotal)?></div>
                        <div class="stat-label"><?=Loc::getMessage('AWZ_BANOBOTS_OPT_BANS_TOTAL')?></div>
                    </div>
                </div>
            </td>
        </tr>

        <tr>
            <td style="width:200px;"><?=Loc::getMessage('AWZ_BANOBOTS_OPT_BAN_ENABLED')?></td>
            <td>
                <?$val = Option::get($module_id, "BAN_ENABLED", "N", "");?>
                <input type="checkbox" value="Y" name="BAN_ENABLED" <?if ($val=="Y") echo "checked";?>>
                <span style="margin-left:20px;"><?=Loc::getMessage('AWZ_BANOBOTS_OPT_BAN_UNTIL_DATE')?>:
                    <?$valDate = Option::get($module_id, "BAN_UNTIL_DATE", "", "");?>
                    <?=CAdminCalendar::CalendarDate('BAN_UNTIL_DATE', $valDate, 20, false)?>
                </span>
                <span style="margin-left:20px;"><?=Loc::getMessage('AWZ_BANOBOTS_OPT_BAN_DAYS')?>:
                    <?$valDays = Option::get($module_id, "BAN_DAYS", "0", "");?>
                    <input type="text" size="4" name="BAN_DAYS" value="<?=htmlspecialcharsbx($valDays)?>">
                </span>
            </td>
        </tr>

        <tr>
            <td><?=Loc::getMessage('AWZ_BANOBOTS_OPT_COUNTRY_MODE')?></td>
            <td>
                <?$val = Option::get($module_id, "COUNTRY_MODE", "allow", "");?>
                <select name="COUNTRY_MODE">
                    <option value="allow" <?if($val=='allow') echo 'selected';?>><?=Loc::getMessage('AWZ_BANOBOTS_OPT_COUNTRY_MODE_ALLOW')?></option>
                    <option value="deny" <?if($val=='deny') echo 'selected';?>><?=Loc::getMessage('AWZ_BANOBOTS_OPT_COUNTRY_MODE_DENY')?></option>
                    <option value="all" <?if($val=='all') echo 'selected';?>><?=Loc::getMessage('AWZ_BANOBOTS_OPT_COUNTRY_MODE_ALL')?></option>
                </select>
            </td>
        </tr>

        <tr>
            <td><?=Loc::getMessage('AWZ_BANOBOTS_OPT_COUNTRY_ALLOW_LIST')?></td>
            <td>
                <?$val = Option::get($module_id, "COUNTRY_ALLOW_LIST", "", "");?>
                <input type="text" size="60" name="COUNTRY_ALLOW_LIST" value="<?=htmlspecialcharsbx($val)?>">
                <div style="margin-top:5px;color:#666;"><?=Loc::getMessage('AWZ_BANOBOTS_OPT_COUNTRY_ALLOW_LIST_DESC')?></div>
            </td>
        </tr>

        <tr>
            <td><?=Loc::getMessage('AWZ_BANOBOTS_OPT_COUNTRY_DENY_LIST')?></td>
            <td>
                <?$val = Option::get($module_id, "COUNTRY_DENY_LIST", "", "");?>
                <input type="text" size="60" name="COUNTRY_DENY_LIST" value="<?=htmlspecialcharsbx($val)?>">
            </td>
        </tr>

        <tr>
            <td><?=Loc::getMessage('AWZ_BANOBOTS_OPT_IP_WHITE_LIST')?></td>
            <td>
                <?$val = Option::get($module_id, "IP_WHITE_LIST", "", "");?>
                <textarea name="IP_WHITE_LIST" rows="4" cols="60" style="width:100%;max-width:500px;display:block"><?=htmlspecialcharsbx($val)?></textarea>
                <div style="margin-top:5px;color:#666;">
                    <?=Loc::getMessage('AWZ_BANOBOTS_OPT_CURRENT_IP')?>:
                    <b><?
                        $currentIp = class_exists('\Bitrix\Main\Service\GeoIp\Manager')
                            ? \Bitrix\Main\Service\GeoIp\Manager::getRealIp()
                            : ($_SERVER['REMOTE_ADDR'] ?? '');
                        echo htmlspecialcharsbx((string)$currentIp);
                    ?></b>
                </div>
            </td>
        </tr>

        <tr>
            <td><?=Loc::getMessage('AWZ_BANOBOTS_OPT_BOT_BLACK_LIST')?></td>
            <td>
                <?$val = trim((string)Option::get($module_id, "BOT_BLACK_LIST", "", "")); if($val==='') $val = "SERankingBacklinksBot,SemrushBot,Baiduspider,meta-externalagent,applebot";?>
                <textarea name="BOT_BLACK_LIST" rows="5" cols="60" style="width:100%;max-width:500px;display:block"><?=htmlspecialcharsbx($val)?></textarea>
            </td>
        </tr>

        <tr>
            <td><?=Loc::getMessage('AWZ_BANOBOTS_OPT_BOT_WHITE_LIST')?></td>
            <td>
                <?$val = trim((string)Option::get($module_id, "BOT_WHITE_LIST", "", "")); if($val==='') $val = "google,yandex";?>
                <textarea name="BOT_WHITE_LIST" rows="5" cols="60" style="width:100%;max-width:500px;display:block"><?=htmlspecialcharsbx($val)?></textarea>
            </td>
        </tr>

        <tr>
            <td><?=Loc::getMessage('AWZ_BANOBOTS_OPT_URL_FILTER')?></td>
            <td>
                <?$val = Option::get($module_id, "URL_FILTER", "", "");?>
                <textarea name="URL_FILTER" rows="5" cols="60" style="width:100%;max-width:500px;display:block"><?=htmlspecialcharsbx($val)?></textarea>
            </td>
        </tr>

        <?$tabControl->BeginNextTab();?>

        <tr>
            <td style="width:200px;"></td>
            <td>
                <div class="ui-alert ui-alert-warning">
                    <span class="ui-alert-message">
                        <?=Loc::getMessage('AWZ_BANOBOTS_OPT_DETECTORS_DESC')?>
                    </span>
                </div>
            </td>
        </tr>

        <tr>
            <td><?=Loc::getMessage('AWZ_BANOBOTS_OPT_BAN_EMPTY_UA')?></td>
            <td>
                <?$val = Option::get($module_id, "BAN_EMPTY_UA", "N", "");?>
                <input type="checkbox" value="Y" name="BAN_EMPTY_UA" <?if ($val=="Y") echo "checked";?>>
            </td>
        </tr>

        <tr>
            <td><?=Loc::getMessage('AWZ_BANOBOTS_OPT_BAN_SCANNER_PATHS')?></td>
            <td>
                <?$val = Option::get($module_id, "BAN_SCANNER_PATHS", "N", "");?>
                <input type="checkbox" value="Y" name="BAN_SCANNER_PATHS" <?if ($val=="Y") echo "checked";?>>
            </td>
        </tr>

        <tr>
            <td><?=Loc::getMessage('AWZ_BANOBOTS_OPT_SCANNER_PATHS')?></td>
            <td>
                <?$val = trim((string)Option::get($module_id, "SCANNER_PATHS", "", "")); if($val==='') $val = "wp-admin,wp-login.php,xmlrpc.php,.env,.git,phpmyadmin,adminer,.well-known/security.txt";?>
                <textarea name="SCANNER_PATHS" rows="5" cols="60" style="width:100%;max-width:447px;display:block"><?=htmlspecialcharsbx($val)?></textarea>
            </td>
        </tr>

        <tr>
            <td><?=Loc::getMessage('AWZ_BANOBOTS_OPT_BAN_SUSPECT_QUERY')?></td>
            <td>
                <?$val = Option::get($module_id, "BAN_SUSPECT_QUERY", "N", "");?>
                <input type="checkbox" value="Y" name="BAN_SUSPECT_QUERY" <?if ($val=="Y") echo "checked";?>>
            </td>
        </tr>

        <tr>
            <td><?=Loc::getMessage('AWZ_BANOBOTS_OPT_SUSPECT_QUERY_PATTERNS')?></td>
            <td>
                <?$val = trim((string)Option::get($module_id, "SUSPECT_QUERY_PATTERNS", "", "")); if($val==='') $val = "union select,select * from,information_schema,sleep(,benchmark(,load_file,../,..\\,/etc/passwd,eval(,base64_decode";?>
                <textarea name="SUSPECT_QUERY_PATTERNS" rows="5" cols="60" style="width:100%;max-width:447px;display:block"><?=htmlspecialcharsbx($val)?></textarea>
            </td>
        </tr>

        <?$tabControl->BeginNextTab();?>

        <tr>
            <td><?=Loc::getMessage('AWZ_BANOBOTS_OPT_LOG_MAX_SIZE_MB')?></td>
            <td>
                <?$val = Option::get($module_id, "LOG_MAX_SIZE_MB", "10", "");?>
                <input type="text" size="10" name="LOG_MAX_SIZE_MB" value="<?=htmlspecialcharsbx($val)?>"> Mb
            </td>
        </tr>

        <tr>
            <td><?=Loc::getMessage('AWZ_BANOBOTS_OPT_LOG_FILE_BAN')?></td>
            <td>
                <?$val = Option::get($module_id, "LOG_FILE_BAN", "", "");?>
                <input type="text" size="60" name="LOG_FILE_BAN" value="<?=htmlspecialcharsbx($val)?>">
            </td>
        </tr>

        <tr>
            <td><?=Loc::getMessage('AWZ_BANOBOTS_OPT_LOG_FILE_BOTS')?></td>
            <td>
                <?$val = Option::get($module_id, "LOG_FILE_BOTS", "", "");?>
                <input type="text" size="60" name="LOG_FILE_BOTS" value="<?=htmlspecialcharsbx($val)?>">
            </td>
        </tr>

        <tr>
            <td><?=Loc::getMessage('AWZ_BANOBOTS_OPT_LOG_FILE_COUNTRY')?></td>
            <td>
                <?$val = Option::get($module_id, "LOG_FILE_COUNTRY", "", "");?>
                <input type="text" size="60" name="LOG_FILE_COUNTRY" value="<?=htmlspecialcharsbx($val)?>">
            </td>
        </tr>

        <?
        $tabControl->Buttons();
        ?>
        <input <?if (!AccessController::isEditSettings()) echo "disabled" ?> type="submit" class="adm-btn-green" name="Update" value="<?=Loc::getMessage('AWZ_BANOBOTS_OPT_L_BTN_SAVE')?>" />
        <input type="hidden" name="Update" value="Y" />
        <input <?if (!AccessController::isEditSettings()) echo "disabled" ?> type="submit" class="adm-btn" name="ClearBans" value="<?=Loc::getMessage('AWZ_BANOBOTS_OPT_CLEAR_BANS')?>" onclick="return confirm('<?=CUtil::JSEscape(Loc::getMessage('AWZ_BANOBOTS_OPT_CLEAR_BANS_CONFIRM'))?>');" />
        <input type="hidden" name="ClearBans" value="" />
        <?if(AccessController::isViewRight()){?>
            <button class="adm-header-btn adm-security-btn" onclick="BX.SidePanel.Instance.open('<?=$saveUrl?>');return false;">
                <?=Loc::getMessage('AWZ_BANOBOTS_OPT_SECT2')?>
            </button>
        <?}?>
        <?$tabControl->End();?>
    </form>
<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");
