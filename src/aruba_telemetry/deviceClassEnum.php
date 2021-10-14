<?php
/**
 * Generated by Protobuf protoc plugin.
 *
 * File descriptor : aruba-iot-types.proto
 */


namespace aruba_telemetry;

/**
 * Protobuf enum : aruba_telemetry.deviceClassEnum
 */
class deviceClassEnum extends \Protobuf\Enum
{

    /**
     * unclassified = 0
     */
    const unclassified_VALUE = 0;

    /**
     * arubaBeacon = 1
     */
    const arubaBeacon_VALUE = 1;

    /**
     * arubaTag = 2
     */
    const arubaTag_VALUE = 2;

    /**
     * zfTag = 3
     */
    const zfTag_VALUE = 3;

    /**
     * stanleyTag = 4
     */
    const stanleyTag_VALUE = 4;

    /**
     * virginBeacon = 5
     */
    const virginBeacon_VALUE = 5;

    /**
     * enoceanSensor = 6
     */
    const enoceanSensor_VALUE = 6;

    /**
     * enoceanSwitch = 7
     */
    const enoceanSwitch_VALUE = 7;

    /**
     * iBeacon = 8
     */
    const iBeacon_VALUE = 8;

    /**
     * allBleData = 9
     */
    const allBleData_VALUE = 9;

    /**
     * RawBleData = 10
     */
    const RawBleData_VALUE = 10;

    /**
     * eddystone = 11
     */
    const eddystone_VALUE = 11;

    /**
     * assaAbloy = 12
     */
    const assaAbloy_VALUE = 12;

    /**
     * arubaSensor = 13
     */
    const arubaSensor_VALUE = 13;

    /**
     * abbSensor = 14
     */
    const abbSensor_VALUE = 14;

    /**
     * wifiTag = 15
     */
    const wifiTag_VALUE = 15;

    /**
     * wifiAssocSta = 16
     */
    const wifiAssocSta_VALUE = 16;

    /**
     * wifiUnassocSta = 17
     */
    const wifiUnassocSta_VALUE = 17;

    /**
     * mysphera = 18
     */
    const mysphera_VALUE = 18;

    /**
     * sBeacon = 19
     */
    const sBeacon_VALUE = 19;

    /**
     * wiliot = 20
     */
    const wiliot_VALUE = 20;

    /**
     * ZSD = 21
     */
    const ZSD_VALUE = 21;

    /**
     * serialdata = 22
     */
    const serialdata_VALUE = 22;

    /**
     * exposureNotification = 23
     */
    const exposureNotification_VALUE = 23;

    /**
     * onity = 24
     */
    const onity_VALUE = 24;

    /**
     * minew = 25
     */
    const minew_VALUE = 25;

    /**
     * google = 26
     */
    const google_VALUE = 26;

    /**
     * polestar = 27
     */
    const polestar_VALUE = 27;

    /**
     * blyott = 28
     */
    const blyott_VALUE = 28;

    /**
     * diract = 29
     */
    const diract_VALUE = 29;

    /**
     * gwahygiene = 30
     */
    const gwahygiene_VALUE = 30;

    /**
     * @var \aruba_telemetry\deviceClassEnum
     */
    protected static $unclassified = null;

    /**
     * @var \aruba_telemetry\deviceClassEnum
     */
    protected static $arubaBeacon = null;

    /**
     * @var \aruba_telemetry\deviceClassEnum
     */
    protected static $arubaTag = null;

    /**
     * @var \aruba_telemetry\deviceClassEnum
     */
    protected static $zfTag = null;

    /**
     * @var \aruba_telemetry\deviceClassEnum
     */
    protected static $stanleyTag = null;

    /**
     * @var \aruba_telemetry\deviceClassEnum
     */
    protected static $virginBeacon = null;

    /**
     * @var \aruba_telemetry\deviceClassEnum
     */
    protected static $enoceanSensor = null;

    /**
     * @var \aruba_telemetry\deviceClassEnum
     */
    protected static $enoceanSwitch = null;

    /**
     * @var \aruba_telemetry\deviceClassEnum
     */
    protected static $iBeacon = null;

    /**
     * @var \aruba_telemetry\deviceClassEnum
     */
    protected static $allBleData = null;

    /**
     * @var \aruba_telemetry\deviceClassEnum
     */
    protected static $RawBleData = null;

    /**
     * @var \aruba_telemetry\deviceClassEnum
     */
    protected static $eddystone = null;

    /**
     * @var \aruba_telemetry\deviceClassEnum
     */
    protected static $assaAbloy = null;

    /**
     * @var \aruba_telemetry\deviceClassEnum
     */
    protected static $arubaSensor = null;

    /**
     * @var \aruba_telemetry\deviceClassEnum
     */
    protected static $abbSensor = null;

    /**
     * @var \aruba_telemetry\deviceClassEnum
     */
    protected static $wifiTag = null;

    /**
     * @var \aruba_telemetry\deviceClassEnum
     */
    protected static $wifiAssocSta = null;

    /**
     * @var \aruba_telemetry\deviceClassEnum
     */
    protected static $wifiUnassocSta = null;

    /**
     * @var \aruba_telemetry\deviceClassEnum
     */
    protected static $mysphera = null;

    /**
     * @var \aruba_telemetry\deviceClassEnum
     */
    protected static $sBeacon = null;

    /**
     * @var \aruba_telemetry\deviceClassEnum
     */
    protected static $wiliot = null;

    /**
     * @var \aruba_telemetry\deviceClassEnum
     */
    protected static $ZSD = null;

    /**
     * @var \aruba_telemetry\deviceClassEnum
     */
    protected static $serialdata = null;

    /**
     * @var \aruba_telemetry\deviceClassEnum
     */
    protected static $exposureNotification = null;

    /**
     * @var \aruba_telemetry\deviceClassEnum
     */
    protected static $onity = null;

    /**
     * @var \aruba_telemetry\deviceClassEnum
     */
    protected static $minew = null;

    /**
     * @var \aruba_telemetry\deviceClassEnum
     */
    protected static $google = null;

    /**
     * @var \aruba_telemetry\deviceClassEnum
     */
    protected static $polestar = null;

    /**
     * @var \aruba_telemetry\deviceClassEnum
     */
    protected static $blyott = null;

    /**
     * @var \aruba_telemetry\deviceClassEnum
     */
    protected static $diract = null;

    /**
     * @var \aruba_telemetry\deviceClassEnum
     */
    protected static $gwahygiene = null;

    /**
     * @return \aruba_telemetry\deviceClassEnum
     */
    public static function unclassified()
    {
        if (self::$unclassified !== null) {
            return self::$unclassified;
        }

        return self::$unclassified = new self('unclassified', self::unclassified_VALUE);
    }

    /**
     * @return \aruba_telemetry\deviceClassEnum
     */
    public static function arubaBeacon()
    {
        if (self::$arubaBeacon !== null) {
            return self::$arubaBeacon;
        }

        return self::$arubaBeacon = new self('arubaBeacon', self::arubaBeacon_VALUE);
    }

    /**
     * @return \aruba_telemetry\deviceClassEnum
     */
    public static function arubaTag()
    {
        if (self::$arubaTag !== null) {
            return self::$arubaTag;
        }

        return self::$arubaTag = new self('arubaTag', self::arubaTag_VALUE);
    }

    /**
     * @return \aruba_telemetry\deviceClassEnum
     */
    public static function zfTag()
    {
        if (self::$zfTag !== null) {
            return self::$zfTag;
        }

        return self::$zfTag = new self('zfTag', self::zfTag_VALUE);
    }

    /**
     * @return \aruba_telemetry\deviceClassEnum
     */
    public static function stanleyTag()
    {
        if (self::$stanleyTag !== null) {
            return self::$stanleyTag;
        }

        return self::$stanleyTag = new self('stanleyTag', self::stanleyTag_VALUE);
    }

    /**
     * @return \aruba_telemetry\deviceClassEnum
     */
    public static function virginBeacon()
    {
        if (self::$virginBeacon !== null) {
            return self::$virginBeacon;
        }

        return self::$virginBeacon = new self('virginBeacon', self::virginBeacon_VALUE);
    }

    /**
     * @return \aruba_telemetry\deviceClassEnum
     */
    public static function enoceanSensor()
    {
        if (self::$enoceanSensor !== null) {
            return self::$enoceanSensor;
        }

        return self::$enoceanSensor = new self('enoceanSensor', self::enoceanSensor_VALUE);
    }

    /**
     * @return \aruba_telemetry\deviceClassEnum
     */
    public static function enoceanSwitch()
    {
        if (self::$enoceanSwitch !== null) {
            return self::$enoceanSwitch;
        }

        return self::$enoceanSwitch = new self('enoceanSwitch', self::enoceanSwitch_VALUE);
    }

    /**
     * @return \aruba_telemetry\deviceClassEnum
     */
    public static function iBeacon()
    {
        if (self::$iBeacon !== null) {
            return self::$iBeacon;
        }

        return self::$iBeacon = new self('iBeacon', self::iBeacon_VALUE);
    }

    /**
     * @return \aruba_telemetry\deviceClassEnum
     */
    public static function allBleData()
    {
        if (self::$allBleData !== null) {
            return self::$allBleData;
        }

        return self::$allBleData = new self('allBleData', self::allBleData_VALUE);
    }

    /**
     * @return \aruba_telemetry\deviceClassEnum
     */
    public static function RawBleData()
    {
        if (self::$RawBleData !== null) {
            return self::$RawBleData;
        }

        return self::$RawBleData = new self('RawBleData', self::RawBleData_VALUE);
    }

    /**
     * @return \aruba_telemetry\deviceClassEnum
     */
    public static function eddystone()
    {
        if (self::$eddystone !== null) {
            return self::$eddystone;
        }

        return self::$eddystone = new self('eddystone', self::eddystone_VALUE);
    }

    /**
     * @return \aruba_telemetry\deviceClassEnum
     */
    public static function assaAbloy()
    {
        if (self::$assaAbloy !== null) {
            return self::$assaAbloy;
        }

        return self::$assaAbloy = new self('assaAbloy', self::assaAbloy_VALUE);
    }

    /**
     * @return \aruba_telemetry\deviceClassEnum
     */
    public static function arubaSensor()
    {
        if (self::$arubaSensor !== null) {
            return self::$arubaSensor;
        }

        return self::$arubaSensor = new self('arubaSensor', self::arubaSensor_VALUE);
    }

    /**
     * @return \aruba_telemetry\deviceClassEnum
     */
    public static function abbSensor()
    {
        if (self::$abbSensor !== null) {
            return self::$abbSensor;
        }

        return self::$abbSensor = new self('abbSensor', self::abbSensor_VALUE);
    }

    /**
     * @return \aruba_telemetry\deviceClassEnum
     */
    public static function wifiTag()
    {
        if (self::$wifiTag !== null) {
            return self::$wifiTag;
        }

        return self::$wifiTag = new self('wifiTag', self::wifiTag_VALUE);
    }

    /**
     * @return \aruba_telemetry\deviceClassEnum
     */
    public static function wifiAssocSta()
    {
        if (self::$wifiAssocSta !== null) {
            return self::$wifiAssocSta;
        }

        return self::$wifiAssocSta = new self('wifiAssocSta', self::wifiAssocSta_VALUE);
    }

    /**
     * @return \aruba_telemetry\deviceClassEnum
     */
    public static function wifiUnassocSta()
    {
        if (self::$wifiUnassocSta !== null) {
            return self::$wifiUnassocSta;
        }

        return self::$wifiUnassocSta = new self('wifiUnassocSta', self::wifiUnassocSta_VALUE);
    }

    /**
     * @return \aruba_telemetry\deviceClassEnum
     */
    public static function mysphera()
    {
        if (self::$mysphera !== null) {
            return self::$mysphera;
        }

        return self::$mysphera = new self('mysphera', self::mysphera_VALUE);
    }

    /**
     * @return \aruba_telemetry\deviceClassEnum
     */
    public static function sBeacon()
    {
        if (self::$sBeacon !== null) {
            return self::$sBeacon;
        }

        return self::$sBeacon = new self('sBeacon', self::sBeacon_VALUE);
    }

    /**
     * @return \aruba_telemetry\deviceClassEnum
     */
    public static function wiliot()
    {
        if (self::$wiliot !== null) {
            return self::$wiliot;
        }

        return self::$wiliot = new self('wiliot', self::wiliot_VALUE);
    }

    /**
     * @return \aruba_telemetry\deviceClassEnum
     */
    public static function ZSD()
    {
        if (self::$ZSD !== null) {
            return self::$ZSD;
        }

        return self::$ZSD = new self('ZSD', self::ZSD_VALUE);
    }

    /**
     * @return \aruba_telemetry\deviceClassEnum
     */
    public static function serialdata()
    {
        if (self::$serialdata !== null) {
            return self::$serialdata;
        }

        return self::$serialdata = new self('serialdata', self::serialdata_VALUE);
    }

    /**
     * @return \aruba_telemetry\deviceClassEnum
     */
    public static function exposureNotification()
    {
        if (self::$exposureNotification !== null) {
            return self::$exposureNotification;
        }

        return self::$exposureNotification = new self('exposureNotification', self::exposureNotification_VALUE);
    }

    /**
     * @return \aruba_telemetry\deviceClassEnum
     */
    public static function onity()
    {
        if (self::$onity !== null) {
            return self::$onity;
        }

        return self::$onity = new self('onity', self::onity_VALUE);
    }

    /**
     * @return \aruba_telemetry\deviceClassEnum
     */
    public static function minew()
    {
        if (self::$minew !== null) {
            return self::$minew;
        }

        return self::$minew = new self('minew', self::minew_VALUE);
    }

    /**
     * @return \aruba_telemetry\deviceClassEnum
     */
    public static function google()
    {
        if (self::$google !== null) {
            return self::$google;
        }

        return self::$google = new self('google', self::google_VALUE);
    }

    /**
     * @return \aruba_telemetry\deviceClassEnum
     */
    public static function polestar()
    {
        if (self::$polestar !== null) {
            return self::$polestar;
        }

        return self::$polestar = new self('polestar', self::polestar_VALUE);
    }

    /**
     * @return \aruba_telemetry\deviceClassEnum
     */
    public static function blyott()
    {
        if (self::$blyott !== null) {
            return self::$blyott;
        }

        return self::$blyott = new self('blyott', self::blyott_VALUE);
    }

    /**
     * @return \aruba_telemetry\deviceClassEnum
     */
    public static function diract()
    {
        if (self::$diract !== null) {
            return self::$diract;
        }

        return self::$diract = new self('diract', self::diract_VALUE);
    }

    /**
     * @return \aruba_telemetry\deviceClassEnum
     */
    public static function gwahygiene()
    {
        if (self::$gwahygiene !== null) {
            return self::$gwahygiene;
        }

        return self::$gwahygiene = new self('gwahygiene', self::gwahygiene_VALUE);
    }

    /**
     * @param int $value
     * @return \aruba_telemetry\deviceClassEnum
     */
    public static function valueOf($value)
    {
        switch ($value) {
            case 0: return self::unclassified();
            case 1: return self::arubaBeacon();
            case 2: return self::arubaTag();
            case 3: return self::zfTag();
            case 4: return self::stanleyTag();
            case 5: return self::virginBeacon();
            case 6: return self::enoceanSensor();
            case 7: return self::enoceanSwitch();
            case 8: return self::iBeacon();
            case 9: return self::allBleData();
            case 10: return self::RawBleData();
            case 11: return self::eddystone();
            case 12: return self::assaAbloy();
            case 13: return self::arubaSensor();
            case 14: return self::abbSensor();
            case 15: return self::wifiTag();
            case 16: return self::wifiAssocSta();
            case 17: return self::wifiUnassocSta();
            case 18: return self::mysphera();
            case 19: return self::sBeacon();
            case 20: return self::wiliot();
            case 21: return self::ZSD();
            case 22: return self::serialdata();
            case 23: return self::exposureNotification();
            case 24: return self::onity();
            case 25: return self::minew();
            case 26: return self::google();
            case 27: return self::polestar();
            case 28: return self::blyott();
            case 29: return self::diract();
            case 30: return self::gwahygiene();
            default: return null;
        }
    }


}
