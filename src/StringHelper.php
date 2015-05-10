<?php

namespace Vovanmix\WebOrm;

class StringHelper{

    public $cache;

    /**
     * @param mixed $type
     * @param mixed $key
     * @param mixed $value
     * @return bool
     */
    protected function _cache($type, $key, $value = false)
    {
        $key = '_' . $key;
        $type = '_' . $type;
        if ($value !== false) {
            $this->cache[$type][$key] = $value;
            return $value;
        }
        if (!isset($this->cache[$type][$key])) {
            return false;
        }
        return $this->cache[$type][$key];
    }

    /**
     * Returns the given lower_case_and_underscored_word as a CamelCased word.
     *
     * @param string $lowerCaseAndUnderscoredWord Word to camelize
     * @return string Camelized word. LikeThis.
     */
    public function camelize($lowerCaseAndUnderscoredWord)
    {
        if (!($result = $this->_cache(__FUNCTION__, $lowerCaseAndUnderscoredWord))) {
            $result = str_replace(' ', '', self::humanize($lowerCaseAndUnderscoredWord));
            $this->_cache(__FUNCTION__, $lowerCaseAndUnderscoredWord, $result);
        }
        return $result;
    }

    /**
     * @param $lowerCaseAndUnderscoredWord
     * @return bool|string
     */
    public function humanize($lowerCaseAndUnderscoredWord)
    {
        if (!($result = $this->_cache(__FUNCTION__, $lowerCaseAndUnderscoredWord))) {
            $result = ucwords(str_replace('_', ' ', $lowerCaseAndUnderscoredWord));
            $this->_cache(__FUNCTION__, $lowerCaseAndUnderscoredWord, $result);
        }
        return $result;
    }

    /**
     * Returns the given camelCasedWord as an underscored_word.
     *
     * @param string $camelCasedWord Camel-cased word to be "underscorized"
     * @return string Underscore-syntaxed version of the $camelCasedWord
     */
    public function underscore($camelCasedWord)
    {
        if (!($result = self::_cache(__FUNCTION__, $camelCasedWord))) {
            $result = strtolower(preg_replace('/(?<=\\w)([A-Z])/', '_\\1', $camelCasedWord));
            self::_cache(__FUNCTION__, $camelCasedWord, $result);
        }
        return $result;
    }
}
