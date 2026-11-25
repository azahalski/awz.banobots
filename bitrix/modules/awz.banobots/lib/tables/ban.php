<?php

namespace Awz\Banobots\Tables;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Data\Internal\DeleteByFilterTrait;
use Bitrix\Main\ORM\Fields\BooleanField;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\Type\DateTime;

class BanTable extends DataManager
{
    use DeleteByFilterTrait;

    public static function getTableName()
    {
        return 'awz_banobots_ban';
    }

    public static function getMap()
    {
        return array(
            (new IntegerField('ID'))
                ->configurePrimary(true)
                ->configureAutocomplete(true),
            (new DatetimeField('DATE_BAN'))
                ->configureDefaultValue(function(){
                    return new DateTime();
                }),
            (new StringField('IP'))
                ->configureSize(50),
            (new StringField('COUNTRY'))
                ->configureSize(10),
            (new StringField('USER_AGENT'))
                ->configureSize(500),
            (new StringField('PAGE'))
                ->configureSize(500),
            (new StringField('REFERER'))
                ->configureSize(500),
            (new BooleanField('ACTIVE'))
                ->configureValues('N', 'Y')
                ->configureDefaultValue(true),
        );
    }
}
