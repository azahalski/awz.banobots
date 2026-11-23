<?php

namespace Awz\Banobots\Tables\AdminPages;

use Bitrix\Main\Localization\Loc;
use Awz\Admin\IList;
use Awz\Admin\IParams;
use Awz\Admin\Helper;
use Awz\Banobots\Access\AccessController;
use Awz\Banobots\Access\Custom\ActionDictionary;

Loc::loadMessages(__FILE__);

class BanList extends IList implements IParams {

    public function __construct($params){
        parent::__construct($params);
        if(!AccessController::isViewSettings()){
            $this->getAdminList()->arGroupErrors[] = ["Нет прав на просмотр списка блокировок"];
        }
        if(AccessController::isEditSettings()){
            global $POST_RIGHT;
            $POST_RIGHT = 'W';
        }
    }

    public function trigerGetRowListAdmin($row){
    }

    public function trigerInitFilter(){
        if(!AccessController::isEditSettings()){
            $this->filter['<ID'] = 0;
        }
    }

    public function trigerGetRowListActions(array $actions): array
    {
        return $actions;
    }

    public static function getTitle(): string
    {
        return Loc::getMessage('AWZ_BANOBOTS_S_BAN_LIST_TITLE');
    }

    public static function getParams(): array
    {
        $arParams = array(
            "ENTITY" => "\\Awz\\Banobots\\Tables\\BanTable",
            "BUTTON_CONTEXTS"=>array(),
            "ADD_GROUP_ACTIONS"=>array("edit","delete"),
            "ADD_LIST_ACTIONS"=>array("delete"),
            "FIND"=>array(),
            "FIND_FROM_ENTITY"=>array(
                'IP'=>[],
                'COUNTRY'=>[],
                'USER_AGENT'=>[],
                'PAGE'=>[],
                'REFERER'=>[],
                'DATE_BAN'=>[],
                'ACTIVE'=>[],
            ),
        );
        return $arParams;
    }
}