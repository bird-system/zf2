<?php

namespace BS\PHPExcel\Cell;

use PHPExcel_RichText;

/**
 * PHPExcel_Cell_ValueBinder
 *
 * @category   PHPExcel
 * @package    PHPExcel_Cell
 */
class PHPExcel_Cell_ValueBinder implements \PHPExcel_Cell_IValueBinder
{
    /**
     * Bind value to a cell
     *
     * @param \PHPExcel_Cell $cell  Cell to bind value to
     * @param mixed          $value Value to bind in cell
     *
     * @return boolean
     */
    public function bindValue(\PHPExcel_Cell $cell, $value = null)
    {
        // sanitize UTF-8 strings
        if (is_string($value)) {
            $value = \PHPExcel_Shared_String::SanitizeUTF8($value);
        }

        // Set value explicit
        $cell->setValueExplicit($value, self::dataTypeForValue($value));

        // Done!
        return true;
    }

    /**
     * DataType for value, Mark all numeric as STRING
     *
     * @param    mixed $pValue
     *
     * @return    int
     */
    public static function dataTypeForValue($pValue = null)
    {
        // Match the value against a few data types
        if (is_null($pValue)) {
            return \PHPExcel_Cell_DataType::TYPE_NULL;

        } elseif ($pValue === '') {
            return \PHPExcel_Cell_DataType::TYPE_STRING;

        } elseif ($pValue instanceof PHPExcel_RichText) {
            return \PHPExcel_Cell_DataType::TYPE_INLINE;

        } elseif ($pValue{0} === '=' && strlen($pValue) > 1) {
            return \PHPExcel_Cell_DataType::TYPE_FORMULA;

        } elseif (is_string($pValue) && array_key_exists($pValue, \PHPExcel_Cell_DataType::getErrorCodes())) {
            return \PHPExcel_Cell_DataType::TYPE_ERROR;

        } else {
            return \PHPExcel_Cell_DataType::TYPE_STRING;

        }
    }
}
