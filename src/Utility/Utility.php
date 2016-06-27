<?php

namespace BS\Utility;


use BS\Db\Model\AbstractModel;
use Zend\Db\Adapter\Driver\ResultInterface;
use Zend\Db\ResultSet\ResultSet;
use Zend\Db\Sql\Expression;
use Zend\Stdlib\ArrayObject;

class Utility
{
    public static function arrayDiff($array1, $array2)
    {
        $difference = [];
        foreach ($array1 as $key => $value) {
            if (is_array($value)) {
                if (!isset($array2[$key])) {
                    $difference[$key] = $value;
                } elseif (!is_array($array2[$key])) {
                    $difference[$key] = $value;
                } else {
                    $new_diff = self::arrayDiff($value, $array2[$key]);
                    if ($new_diff != false) {
                        $difference[$key] = $new_diff;
                    }
                }
            } else {
                if (!isset($array2[$key])) {
                    $difference[$key] = $value;
                } else {
                    $value1 = $value;
                    $value2 = $array2[$key];
                    if ($value1 instanceof Expression) {
                        $value1 = $value1->getExpressionData();
                    }
                    if ($value2 instanceof Expression) {
                        $value2 = $value2->getExpressionData();
                    }
                    if ($value2 !== $value1) {
                        $difference[$key] = $value;
                    }
                }
            }
        }

        return $difference;
    }

    public static function pdoResultToArray($result)
    {
        $retArray = [];
        foreach ($result as $record) {
            $retArray[] = $record;
        }

        return $retArray;
    }

    /**
     * @param $d AbstractModel|ArrayObject|ResultInterface|ResultSet|array
     *
     * @return array
     */
    public static function objectToArray($d)
    {
        if (is_a($d, AbstractModel::class)) {
            return $d->__toArray();
        } else {
            if (is_a($d, ArrayObject::class)) {
                return $d->getArrayCopy();
            } else {
                if (is_a($d, ResultInterface::class)) {
                    return self::pdoResultToArray($d);
                } else {
                    if (is_a($d, ResultSet::class)) {
                        return $d->toArray();
                    }
                }
            }
        };

        if (is_object($d)) {
            $d = get_object_vars($d);
        }

        if (is_array($d)) {
            return array_map('self::objectToArray', $d);
        } else {
            // Return array
            return $d;
        }
    }

    public static function arrayToObject($d)
    {
        if (is_array($d)) {
            return (object)array_map('self::arrayToObject', $d);
        } else {
            // Return object
            return $d;
        }
    }

    public static function getClientIP()
    {
        $ip = 'unknown';
        if (isset($_SERVER)) {
            if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
            } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
                $ip = $_SERVER['HTTP_CLIENT_IP'];
            } elseif (isset($_SERVER['REMOTE_ADDR'])) {
                $ip = $_SERVER['REMOTE_ADDR'];
            }
        } else {
            if (getenv('HTTP_X_FORWARDED_FOR')) {
                $ip = getenv('HTTP_X_FORWARDED_FOR');
            } elseif (getenv('HTTP_CLIENT_ip')) {
                $ip = getenv('HTTP_CLIENT_ip');
            } elseif (getenv('REMOTE_ADDR')) {
                $ip = getenv('REMOTE_ADDR');
            }
        }
        if (trim($ip) == '::1') {
            $ip = '127.0.0.1';
        }

        return $ip;
    }

    public static function truncateStringLength($string, $len = 50)
    {
        $string = substr(trim(str_replace(["\n", "\r\n"], '', $string)), 0, $len);
        return $string ? $string : '';
    }

    public static function convertEUSpecialCharacter2EnglishLetter($string)
    {
        // @formatter:off
        $a =
            ['À', 'Á', 'Â', 'Ã', 'Ä', 'Å', 'Æ', 'Ç', 'È', 'É', 'Ê', 'Ë', 'Ì', 'Í', 'Î', 'Ï', 'Ð', 'Ñ', 'Ò', 'Ó',
              'Ô', 'Õ', 'Ö', 'Ø', 'Ù', 'Ú', 'Û', 'Ü', 'Ý', 'ß', 'à', 'á', 'â', 'ã', 'ä',
              'å', 'æ', 'ç', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î', 'ï', 'ñ', 'ò', 'ó', 'ô', 'õ', 'ö', 'ø', 'ù', 'ú',
              'û', 'ü', 'ý', 'ÿ', 'Ā', 'ā', 'Ă', 'ă', 'Ą', 'ą', 'Ć', 'ć', 'Ĉ', 'ĉ', 'Ċ',
              'ċ', 'Č', 'č', 'Ď', 'ď', 'Đ', 'đ', 'Ē', 'ē', 'Ĕ', 'ĕ', 'Ė', 'ė', 'Ę', 'ę', 'Ě', 'ě', 'Ĝ', 'ĝ', 'Ğ',
              'ğ', 'Ġ', 'ġ', 'Ģ', 'ģ', 'Ĥ', 'ĥ', 'Ħ', 'ħ', 'Ĩ', 'ĩ', 'Ī', 'ī', 'Ĭ', 'ĭ',
              'Į', 'į', 'İ', 'ı', 'Ĳ', 'ĳ', 'Ĵ', 'ĵ', 'Ķ', 'ķ', 'Ĺ', 'ĺ', 'Ļ', 'ļ', 'Ľ', 'ľ', 'Ŀ', 'ŀ', 'Ł', 'ł',
              'Ń', 'ń', 'Ņ', 'ņ', 'Ň', 'ň', 'ŉ', 'Ō', 'ō', 'Ŏ', 'ŏ', 'Ő', 'ő', 'Œ', 'œ',
              'Ŕ', 'ŕ', 'Ŗ', 'ŗ', 'Ř', 'ř', 'Ś', 'ś', 'Ŝ', 'ŝ', 'Ş', 'ş', 'Š', 'š', 'Ţ', 'ţ', 'Ť', 'ť', 'Ŧ', 'ŧ',
              'Ũ', 'ũ', 'Ū', 'ū', 'Ŭ', 'ŭ', 'Ů', 'ů', 'Ű', 'ű', 'Ų', 'ų', 'Ŵ', 'ŵ', 'Ŷ',
              'ŷ', 'Ÿ', 'Ź', 'ź', 'Ż', 'ż', 'Ž', 'ž', 'ſ', 'ƒ', 'Ơ', 'ơ', 'Ư', 'ư', 'Ǎ', 'ǎ', 'Ǐ', 'ǐ', 'Ǒ', 'ǒ',
              'Ǔ', 'ǔ', 'Ǖ', 'ǖ', 'Ǘ', 'ǘ', 'Ǚ', 'ǚ', 'Ǜ', 'ǜ', 'Ǻ', 'ǻ', 'Ǽ', 'ǽ', 'Ǿ',
              'ǿ'];
        $b =
            ['A', 'A', 'A', 'A', 'A', 'A', 'AE', 'C', 'E', 'E', 'E', 'E', 'I', 'I', 'I', 'I', 'D', 'N', 'O', 'O',
              'O', 'O', 'O', 'O', 'U', 'U', 'U', 'U', 'Y', 's', 'a', 'a', 'a', 'a', 'a',
              'a', 'ae', 'c', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'n', 'o', 'o', 'o', 'o', 'o', 'o', 'u', 'u',
              'u', 'u', 'y', 'y', 'A', 'a', 'A', 'a', 'A', 'a', 'C', 'c', 'C', 'c', 'C',
              'c', 'C', 'c', 'D', 'd', 'D', 'd', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'G', 'g', 'G',
              'g', 'G', 'g', 'G', 'g', 'H', 'h', 'H', 'h', 'I', 'i', 'I', 'i', 'I', 'i',
              'I', 'i', 'I', 'i', 'IJ', 'ij', 'J', 'j', 'K', 'k', 'L', 'l', 'L', 'l', 'L', 'l', 'L', 'l', 'l', 'l',
              'N', 'n', 'N', 'n', 'N', 'n', 'n', 'O', 'o', 'O', 'o', 'O', 'o', 'OE', 'oe',
              'R', 'r', 'R', 'r', 'R', 'r', 'S', 's', 'S', 's', 'S', 's', 'S', 's', 'T', 't', 'T', 't', 'T', 't',
              'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'W', 'w', 'Y',
              'y', 'Y', 'Z', 'z', 'Z', 'z', 'Z', 'z', 's', 'f', 'O', 'o', 'U', 'u', 'A', 'a', 'I', 'i', 'O', 'o',
              'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'A', 'a', 'AE', 'ae', 'O',
              'o'];
        // @formatter:on
        return str_replace($a, $b, $string);
    }

    public static function convertRussianCyrillic2Latin($string)
    {
        // @formatter:off
        $map = array(
            'А' => 'a', 'Б' => 'b', 'В' => 'v', 'Г' => 'g', 'Д' => 'd',
            'Е' => 'e', 'Ё' => 'yo', 'Ж' => 'zh', 'З' => 'z', 'И' => 'i',
            'Й' => 'j', 'К' => 'k', 'Л' => 'l', 'М' => 'm', 'Н' => 'n',
            'О' => 'o', 'П' => 'p', 'Р' => 'r', 'С' => 's', 'Т' => 't',
            'У' => 'u', 'Ф' => 'f', 'Х' => 'kh', 'Ц' => 'ts', 'Ч' => 'ch',
            'Ш' => 'sh', 'Щ' => 'sch', 'Ъ' => '', 'Ы' => 'y', 'Ь' => '',
            'Э' => 'e', 'Ю' => 'yu', 'Я' => 'ya', 'а' => 'a', 'б' => 'b',
            'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'yo',
            'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'j', 'к' => 'k',
            'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p',
            'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f',
            'х' => 'kh', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch',
            'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu',
            'я' => 'ya', ' ' => '-', '.' => '', ',' => '', '/' => '-',
            ':' => '', ';' => '', '—' => '', '–' => '-'
        );
        // @formatter:on
        return strtr($string, $map);
    }

    public static function isEurope($iso)
    {
        $europe = [
            'AD',
            'AL',
            'AT',
            'AX',
            'BA',
            'BE',
            'BG',
            'BY',
            'CH',
            'CZ',
            'DE',
            'DK',
            'EE',
            'ES',
            'FI',
            'FO',
            'FR',
            'GB',
            'GG',
            'GI',
            'GR',
            'HR',
            'HU',
            'IE',
            'IM',
            'IS',
            'IT',
            'JE',
            'LI',
            'LT',
            'LU',
            'LV',
            'MC',
            'MD',
            'ME',
            'MK',
            'MT',
            'NL',
            'NO',
            'PL',
            'PT',
            'RO',
            'RS',
            'RU',
            'SE',
            'SI',
            'SJ',
            'SK',
            'SM',
            'UA',
            'VA'
        ];

        return in_array($iso, $europe);
    }
}
