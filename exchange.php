<?php

namespace Classes\Media;

class Exchange 
{
    const IBLOCK_CATALOG = 39;
    const COMPATIBILITY_CODE = 'SOVMESTIMOST_BRAND';

    public static function OnCompleteCatalogImport1C() {
        file_put_contents(__DIR__ . '/OnCompleteCatalogImport1C.txt', 'events call!!!');
        \Bitrix\Main\Diag\Debug::writeToFile(array('event start'), "", "1c_exange.log");
        
        self::ChangePropCompatibility();
    }

    public static function ChangePropCompatibility ()
    {
        return self::GetItemsCatalog();
    }

    public static function GetCatalogTable()
    {
        $codeTable = NULL;
        $codeTable = \Bitrix\Iblock\Iblock::wakeUp(self::IBLOCK_CATALOG)->getEntityDataClass();

        return $codeTable;
    }


    public static function GetItemsCatalog ()
    {
        $catalogTable = self::GetCatalogTable();
        
        if (!empty ($catalogTable)) {
            $elements = $catalogTable::getList([
                    'select' => ['ID', 'NAME', 'SOVMESTIMOST_' => 'SOVMESTIMOST'],
                    'filter' => ['=ACTIVE' => 'Y', '!SOVMESTIMOST_VALUE' => ''],
            ])->fetchAll();

            foreach ($elements as $element) {
                if ($element['SOVMESTIMOST_VALUE']) {
                    $arString = explode(',' , $element['SOVMESTIMOST_VALUE']);	
                    self::CheckPropCompatibilityValues($arString, $element['ID']);
                }

            }

        }
    }

    //получение ID свойства дублера совместимости
    public function GetPropCompatibilityId($elementId = false)
    {
        $propertyID = NULL;

        $propertyID = \Bitrix\Iblock\PropertyTable::getList([
            'filter' => [
                '=IBLOCK_ID' => self::IBLOCK_CATALOG, 
                'ACTIVE' => 'Y',
                '=PROPERTY_TYPE' => \Bitrix\Iblock\PropertyTable::TYPE_LIST,
                '=CODE' =>self::COMPATIBILITY_CODE,
            ],
            'select' => ['ID'],
        ])->fetch()['ID'];

        return $propertyID;
    }

    //получение значения свойства дублера совместимости
    public static function GetPropCompatibilityValues ()
    {
        $idProperty = self::GetPropCompatibilityId();
        $arCompatibility = [];

        if ($idProperty) {
            $dbEnums = \Bitrix\Iblock\PropertyEnumerationTable::getList([
                'order' => ['VALUE' => 'asc'],
                'select' => ['*'],
                'filter' => ['PROPERTY_ID' => $idProperty],
            ]);

            while($arEnum = $dbEnums->fetch()) {
                $arCompatibility[$arEnum['ID']] = $arEnum;
            }
        }

        return $arCompatibility;
    }

    //добавление значения свойства дублера совместимости 
    public static function AddPropCompatibilityValues ($name)
    {
        $idProperty = self::GetPropCompatibilityId();
        $arCompatibilityValues = self::GetPropCompatibilityValues();

        if ($idProperty) {
            $arTranslitParams = array("replace_space" => "-", "replace_other" => "-");
            $xmlId = \Cutil::translit($name, 'ru', $arTranslitParams);


                $ibpenum = new \CIBlockPropertyEnum();
                $valueId = $ibpenum->Add([
                    'PROPERTY_ID' => $idProperty,
                    'VALUE' => $name,
                    'XML_ID' => $xmlId,
                ]);

                if ((int) $valueId < 0) {
                    echo 'error add property';
                }

            return $valueId;
        }
    }

    //проставление значений для свойства дублера
    public static function UpdadePropCompatibilityElm ($elementId, $propId)
    {
        $codeTable = self::GetCatalogTable();
        $elementProperty = [];
        $propRes = \Bitrix\Iblock\ElementPropertyTable::getList(array(
            "select" => array("ID", "*"),
            "filter" => array("IBLOCK_ELEMENT_ID" => $elementId, "IBLOCK_PROPERTY_ID" => self::GetPropCompatibilityId()),
            "order"  => array("ID" => "ASC")
        ));
        while($prop = $propRes->Fetch())
        {
            $elementProperty[] = $prop['VALUE'];
        }
        $diffPropValue = \array_diff($propId, $elementProperty) !== \array_diff($elementProperty, $propId);
        if ($diffPropValue) {
            \CIBlockElement::SetPropertyValuesEx($elementId, false, array(self::COMPATIBILITY_CODE => $propId));
        }
    }

    
    //сравнение значения свойства и свойства дублера
    protected static function CheckPropCompatibilityValues ($arValues, $elementId)
    {
        $arCompatibilityValues = self::GetPropCompatibilityValues();
        $arCompatibilityName = [];
        $arAllCompatibilityElms = [];
        $codeForSelectInElement = [];
        
        foreach ($arValues as $key => $value) {
            $curValue = trim($value, ' .');
            $arAllCompatibilityElms[] = $curValue;
        }

        $arAllCompatibilityElms = array_unique($arAllCompatibilityElms);

        foreach ($arCompatibilityValues as $key => $arCompatibility) {
            $arCompatibilityName[$arCompatibility['ID']] = $arCompatibility['VALUE']; 
            $arCompatibilityNameUpper[$arCompatibility['ID']] = \mb_strtoupper($arCompatibility['VALUE']);
        }

        foreach ($arAllCompatibilityElms as $nameBrand) {
            $idNewValue = false;
            $upperNameBrand = \mb_strtoupper($nameBrand);

            if (!\in_array($upperNameBrand, $arCompatibilityNameUpper)) {
                $idNewValue = self::AddPropCompatibilityValues($nameBrand);
                \array_push($codeForSelectInElement, $idNewValue);
            } else {
                $idProp = array_search($upperNameBrand, $arCompatibilityNameUpper);
                if (!empty ($idProp)) {
                    \array_push($codeForSelectInElement, $idProp);
                }
            }
        }

        if (!empty($codeForSelectInElement)) {
            self::UpdadePropCompatibilityElm($elementId, $codeForSelectInElement);
        }
        
    }
}