<?php

namespace BS\Utility;


/*
 * check sensitive words
 */
use BS\Controller\Exception\AppException;

class SensitiveWordCensor
{
    private static $instance;

    /*
     * @var Singleton reference to singleton instance
     */
    private $sensitive_words = null;


    /*
     * not allowed to call from outside: private!
     */

    private function __construct()
    {
    }

    /*
     * gets the instance via lazy initialization (created on first usage)
     *
     * @return self
     */
    public static function getInstance()
    {

        if (null === static::$instance) {
            static::$instance = new static;
        }

        return static::$instance;
    }

    public function isInit()
    {
        return $this->sensitive_words !== null;
    }

    public function init($words)
    {
        $this->sensitive_words = empty($words) ? [] : $words;
    }

    public function reload()
    {
        $this->sensitive_words = null;
    }

    /*
     * check contain sensitive words.
     *
     * @param string $str
     * @return false when $str don't has sensitive word, else a sensitive words array $str contain.
     */
    public function check($str)
    {
        if (empty($str) || empty($this->sensitive_words)) {
            return false;
        }

        $words_found = [];
        foreach ($this->sensitive_words as $word) {
            if (empty($word)) {
                continue;
            }
            if (preg_match('/^[a-zA-Z]+$/', $word) > 0) {
                // english sensitive words
                if (preg_match('/\b' . $word . '\b/', $str) > 0) {
                    $words_found[] = $word;
                }
            } else {
                // chinese sensitive words
                if (strpos($str, $word) !== false) {
                    $words_found[] = $word;
                }
            }
        }

        if (count($words_found) > 0) {
            return $words_found;
        } else {
            return false;
        }
    }

    /*
     * hightlight the sesitive words in $str
     * @param string $str
     * @param function $replace_fn
     * @return string
     */
    public function hightlight($str, $replace_fn)
    {
        if (!is_callable($replace_fn)) {
            throw new AppException('argument $replace_fn must be a function.');
        }
        if (empty($this->sensitive_words)) {
            return $str;
        }

        foreach ($this->sensitive_words as $word) {
            if (empty($word)) {
                continue;
            }
            if (preg_match('/^[a-zA-Z]+$/', $word) > 0) {
                // english sensitive words
                $replace_to = call_user_func($replace_fn, $word);
                $str        = preg_replace('/\b' . $word . '\b/', $replace_to, $str);
            } else {
                // chinese sensitive words
                $replace_to = call_user_func($replace_fn, $word);
                $str        = str_replace($word, $replace_to, $str);
            }
        }

        return $str;
    }

}