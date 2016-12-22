<?php

namespace BS\Utility;

use BS\Traits\LoggerAwareTrait;
use Crisu83\Conversion\Quantity\Length;
use Crisu83\Conversion\Quantity\Mass;
use Crisu83\Conversion\Quantity\Volume;
use Psr\Log\LoggerAwareInterface;
use Zend\ServiceManager\ServiceLocatorAwareTrait;


class Measure implements LoggerAwareInterface
{
    use ServiceLocatorAwareTrait, LoggerAwareTrait;

    const SYS_LENGTH_UNIT = 'MILLIMETER';
    const SYS_WEIGHT_UNIT = 'GRAM';
    const SYS_VOLUME_UNIT = 'CUBIC_MILLIMETER';

    const CONVERT_ROUND = 4;

    const CACHE_TAG = 'USER_MEASURE_CONFIG';

    public $lengthColumnNames;
    public $weightColumnNames;
    public $volumeColumnNames;
    public $userLengthUnit;
    public $userWeightUnit;
    public $userVolumeUnit;

    /**
     * Map the system unit name to the one used in crisu83\php-conversion
     *
     * @var array
     */
    static $unitNameMap = [
        // Length
        'MILLIMETER'       => Length\Unit::MILLIMETRE,
        'CENTIMETER'       => Length\Unit::CENTIMETRE,
        'METER'            => Length\Unit::METRE,
        'INCH'             => Length\Unit::INCH,
        'FEET'             => Length\Unit::FOOT,

        // Volume
        'CUBIC_MILLIMETER' => Volume\Unit::CUBIC_MILLIMETRE,
        'CUBIC_CENTIMETER' => Volume\Unit::CUBIC_CENTIMETRE,
        'CUBIC_METER'      => Volume\Unit::CUBIC_METRE,
        'CUBIC_INCH'       => Volume\Unit::CUBIC_INCH,
        'CUBIC_FEET'       => Volume\Unit::CUBIC_FOOT,

        // Weight
        'GRAM'             => Mass\Unit::GRAM,
        'KILOGRAM'         => Mass\Unit::KILOGRAM,
        'POUND'            => Mass\Unit::POUND,
    ];

    static $LENGTH = 'LENGTH';
    static $VOLUME = 'VOLUME';
    static $WEIGHT = 'WEIGHT';

    protected $is_convert = false;

    /**
     * @param array $config
     *
     * @return $this
     */
    public function init($config = [])
    {
//        $this->log('User Measure Unit', $config);
        if (!isset($config[self::$LENGTH])) {
            $config[self::$LENGTH] = 'MILLIMETER';
        }
        if (!isset($config[self::$WEIGHT])) {
            $config[self::$WEIGHT] = 'GRAM';
        }
        if (!isset($config[self::$VOLUME])) {
            $config[self::$VOLUME] = 'CUBIC_MILLIMETER';
        }

        $this->userLengthUnit = $config[self::$LENGTH];
        $this->userWeightUnit = $config[self::$WEIGHT];
        $this->userVolumeUnit = $config[self::$VOLUME];

        // Check if we need to convert
        if ($this->userLengthUnit != self::SYS_LENGTH_UNIT
            || $this->userWeightUnit != self::SYS_WEIGHT_UNIT
            || $this->userVolumeUnit != self::SYS_VOLUME_UNIT
        ) {
            $this->is_convert = true;
        }

        return $this;
    }

    public function getConvertList($listArray, $listUnit = false)
    {
        if ($this->is_convert === true) {
            foreach ($listArray as $key => $single) {
                // Do measurement converter
                if (count($this->lengthColumnNames) > 0) {
                    foreach ($this->lengthColumnNames as $length_column_name) {
                        if (isset($single[$length_column_name])) {
                            $listArray[$key][$length_column_name] =
                                $this->convertUserLength($single[$length_column_name],
                                    $listUnit);
                        } elseif ($key === $length_column_name) {
                            $listArray[$key] = $this->convertUserLength($single, $listUnit);
                        }
                    }
                }

                if (count($this->weightColumnNames) > 0) {
                    foreach ($this->weightColumnNames as $weight_column_name) {
                        if (isset($single[$weight_column_name])) {
                            $listArray[$key][$weight_column_name] =
                                $this->convertUserWeight($single[$weight_column_name],
                                    $listUnit);
                        } elseif ($key === $weight_column_name) {
                            $listArray[$key] = $this->convertUserWeight($single, $listUnit);
                        }
                    }
                }

                if (count($this->volumeColumnNames) > 0) {
                    foreach ($this->volumeColumnNames as $volume_column_name) {
                        if (isset($single[$volume_column_name])) {
                            $listArray[$key][$volume_column_name] =
                                $this->convertUserVolume($single[$volume_column_name],
                                    $listUnit);
                        } elseif ($key === $volume_column_name) {
                            $listArray[$key] = $this->convertUserVolume($single, $listUnit);
                        }
                    }
                }
            }
        }

        return $listArray;
    }

    public function saveConvertArray($array)
    {
        if ($this->is_convert === true) {
            // Do measurement converter
            if (count($this->lengthColumnNames) > 0) {
                foreach ($this->lengthColumnNames as $length_column_name) {
                    if (isset($array[$length_column_name]) && !empty($array[$length_column_name])) {
                        $array[$length_column_name] = $this->convertSysLength($array[$length_column_name]);
                    }

                }
            }

            if (count($this->weightColumnNames) > 0) {
                foreach ($this->weightColumnNames as $weight_column_name) {
                    if (isset($array[$weight_column_name]) && !empty($array[$weight_column_name])) {
                        $array[$weight_column_name] = $this->convertSysWeight($array[$weight_column_name]);
                    }
                }
            }

            if (count($this->volumeColumnNames) > 0) {
                foreach ($this->volumeColumnNames as $volume_column_name) {
                    if (isset($array[$volume_column_name]) && !empty($array[$volume_column_name])) {
                        $array[$volume_column_name] = $this->convertSysVolume($array[$volume_column_name]);
                    }
                }
            }
        }

        return $array;
    }

    private function convertSysWeight($value)
    {

        $weight = new Mass\Mass($value, $this->mapUnitName($this->userWeightUnit));

        $weight->to($this->mapUnitName(self::SYS_WEIGHT_UNIT));

        $convertWeight = $weight->getValue();

        return $convertWeight;
    }

    private function convertSysLength($value)
    {
        $length = new Length\Length($value, $this->mapUnitName($this->userLengthUnit));

        $length->to($this->mapUnitName(self::SYS_LENGTH_UNIT));

        $convertLength = $length->getValue();

        return $convertLength;
    }

    private function convertSysVolume($value)
    {
        $volume = new Volume\Volume($value, $this->mapUnitName($this->userVolumeUnit));

        $volume->to($this->mapUnitName(self::SYS_VOLUME_UNIT));

        $convertVolume = $volume->getValue();

        return $convertVolume;
    }

    /*
     * These functions can convert value to user units,
     */
    public function convertUserWeight($value, $listUnit = false)
    {
        $weight = new Mass\Mass($value, $this->mapUnitName(self::SYS_WEIGHT_UNIT));

        $convertWeight = $weight->to($this->mapUnitName($this->userWeightUnit));

        return $listUnit ? $convertWeight->out(self::CONVERT_ROUND)
            : floatval(str_replace(',', '', $convertWeight->format(self::CONVERT_ROUND)));
    }

    public function convertUserLength($value, $listUnit = false)
    {
        $length = new Length\Length($value, $this->mapUnitName(self::SYS_LENGTH_UNIT));

        $convertLength = $length->to($this->mapUnitName($this->userLengthUnit));

        return $listUnit ? $convertLength->out(self::CONVERT_ROUND)
            : floatval(str_replace(',', '', $convertLength->format(self::CONVERT_ROUND)));
    }

    public function convertUserVolume($value, $listUnit = false)
    {
        $volume = new Volume\Volume($value, $this->mapUnitName(self::SYS_VOLUME_UNIT));

        $convertVolume = $volume->to($this->mapUnitName($this->userVolumeUnit));

        return $listUnit ? $convertVolume->out(self::CONVERT_ROUND)
            : floatval(str_replace(',', '', $convertVolume->format(self::CONVERT_ROUND)));
    }

    /**
     * @param float  $value
     * @param string $toType
     * @param int    $decimalLength
     * @param string $fromType
     *
     * @return float
     */
    static public function convertWeight(
        $value,
        $toType,
        $decimalLength = self::CONVERT_ROUND,
        $fromType = self::SYS_WEIGHT_UNIT
    ) {
        $MeasureWeight = new Mass\Mass($value, self::mapUnitName($fromType));

        return $MeasureWeight->to(self::mapUnitName($toType))->format($decimalLength);
    }

    static public function convertLength(
        $value,
        $toType,
        $decimalLength = self::CONVERT_ROUND,
        $fromType = self::SYS_LENGTH_UNIT
    ) {
        $MeasureLength = new Length\Length($value, self::mapUnitName($fromType));

        return $MeasureLength->to(self::mapUnitName($toType))->format($decimalLength);
    }

    static public function convertVolume(
        $value,
        $toType,
        $decimalLength = self::CONVERT_ROUND,
        $fromType = self::SYS_VOLUME_UNIT
    ) {
        $MeasureVolume = new Volume\Volume($value, self::mapUnitName($fromType));

        return $MeasureVolume->to(self::mapUnitName($toType))->format($decimalLength);
    }

    /*
     * These functions return human unit like m,g,kg
     * */
    public function getHumanLengthUnit()
    {
        $Measure = new Length\Length(1, self::mapUnitName($this->userLengthUnit));

        return $Measure->getUnit();
    }

    public function getHumanWeightUnit()
    {
        $Measure = new Mass\Mass(1, self::mapUnitName($this->userWeightUnit));

        return $Measure->getUnit();
    }

    public function getHumanVolumeUnit()
    {
        $Measure = new Volume\Volume(1, self::mapUnitName($this->userVolumeUnit));

        return $Measure->getUnit();
    }

    public static function mapUnitName($unit)
    {
        if (!array_key_exists($unit, self::$unitNameMap)) {
            throw new \InvalidArgumentException();
        }

        return static::$unitNameMap[$unit];
    }
}