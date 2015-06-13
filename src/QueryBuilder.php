<?php

namespace Vovanmix\WebOrm;

class QueryBuilder{


    /**
     * @param $table
     * @param $data
     * @return string
     */
    public static function buildSaveQuery($table, $data){
        $q = 'INSERT INTO `' . $table .'`';

        $fields = array();
        $values = array();
        foreach ($data as $field => $value) {
            $fields[] = $field;
            $values[] = self::prepare($value);
        }

        $q .= ' (' . implode(',', $fields) . ')';
        $q .= ' VALUES (' . implode(',', $values) . ')';

        return $q;
    }



    public static function queryIsChangingData($sql){
        return (strpos($sql, 'UPDATE ') !== false || strpos($sql, 'INSERT INTO ') !== false || strpos($sql, 'DELETE ') !== false);
    }



    public static function buildRemoveQuery($table, $conditions){
        $q = 'DELETE FROM `' . $table . '`';

        if (!empty($conditions)) {
            $q .= self::buildConditions($conditions);
        }

        return $q;
    }



    /**
     * @param mixed $value
     * @return string
     */
    public static function prepare($value)
    {

        if( is_string($value) ) {
            $value = str_replace("'", '`', $value);
            $value = addslashes(trim($value));
        }
        if(is_array($value)) {
            $value = reset($value);
        }

        $value = !empty($value) ? "'" . $value . "'" : (($value === 0 || $value === '0') ? '0' : ($value === '' ? '""' : ($value === false ? 0 : 'NULL')));

        return $value;
    }

    /**
     * @param $conditions
     * @param bool $is_sub_condition
     * @param string $operation
     * @return string
     */
    public static function buildConditions($conditions, $is_sub_condition = false, $operation = 'AND')
    {
        $q = '';
        $condition_sets = array();

        foreach ($conditions as $condition_key => $condition_set) {

            if ($condition_key === 'OR') {
                $sub_operation = 'OR';
            } else {
                $sub_operation = 'AND';
            }

            if (self::conditionHasSubset($condition_set)) {
                $condition_sets[] = ' (' . self::buildConditions($condition_set, true, $sub_operation) . ') ';
            } else {
                $condition_sets[] = self::buildConditionStatement($condition_set);
            }
        }
        if (!empty($condition_sets)) {
            if (!$is_sub_condition) {
                $q .= ' WHERE ';
            }
            $q .= implode(' ' . $operation . ' ', $condition_sets);
        }

        return $q;
    }

    public static function conditionHasSubset($condition_set){
        return ((isset($condition_set[0]) && is_array($condition_set[0])) || (!isset($condition_set[0])));
    }

    public static function buildConditionArray($condition_set){
        $condition_arr = array();
        foreach ($condition_set[2] as $c) {
            $condition_arr[] = (!empty($c) || $c === 0 || $c === '0') ? "'" . $c . "'" : 'NULL';
        }
        if($condition_set[1] == '=') {
            $condition_set[1] = 'IN';
        } elseif($condition_set[1] == '!=' || $condition_set[1] == '<>') {
            $condition_set[1] = 'NOT IN';
        }
        if(empty($condition_arr)) {
            $condition_arr = array('NULL');
        }
        $result = $condition_set[0] . " " . $condition_set[1] . " (" . implode(',', $condition_arr) . ") ";

        return $result;
    }

    public static function buildConditionEmpty($condition_set){
        if ($condition_set[1] == 'NOT' || $condition_set[1] == '!=' || $condition_set[1] == '<>') {
            $result = "(" . $condition_set[0] . " IS NOT NULL OR " . $condition_set[0] . " != '' OR " . $condition_set[0] . " != 0" . ")";
        } elseif ($condition_set[1] == '=') {
            $result = "(" . $condition_set[0] . " IS NULL OR " . $condition_set[0] . " = '' OR " . $condition_set[0] . " = 0" . ")";
        } else{
            if($condition_set[2] === '') {
                $condition_set[2] = "''";
            }
            $result = "(" . $condition_set[0] . " " . $condition_set[1] . " " . $condition_set[2] . ")";
        }

        return $result;
    }

    public static function buildConditionSimple($condition_set){
        if (substr($condition_set[1], 0, 1) == '.') {
            $condition_set[1] = substr($condition_set[1], 1, strlen($condition_set[1] - 1));
        } elseif( !is_numeric($condition_set[2]) ) {
            $condition_set[2] = "'" . $condition_set[2] . "'";
        }
        $result = $condition_set[0] . " " . $condition_set[1] . " " . $condition_set[2] . " ";

        return $result;
    }

    /**
     * @param array $condition_set
     * @return string
     */
    public static function buildConditionStatement($condition_set) {
        if (is_array($condition_set[2])) {
            $result = self::buildConditionArray($condition_set);
        } elseif (empty($condition_set[2])) {
            $result = self::buildConditionEmpty($condition_set);
        } else {
            $result = self::buildConditionSimple($condition_set);
        }
        return $result;
    }

    public static function buildFieldStatement($field_key, $field_name){
        if ((int)$field_key !== $field_key) {
            return $field_key . ' as ' . $field_name;
        } else {
            return $field_name;
        }
    }

    /**
     * @param mixed $fields
     * @return string
     */
    public static function buildFields($fields){

        $q = '';
        $fieldsArray = array();

        if (!empty($fields) && is_array($fields)) {
            foreach ($fields as $field_key => $field_name) {
                $fieldsArray[] = self::buildFieldStatement($field_key, $field_name);
            }
            $q .= implode(',', $fieldsArray);
        } else {
            $q .= '*';
        }
        return $q;
    }

    public static function buildJoinStatement($join_set){
        if(is_array($join_set[0])) {
            $tableName = '`'.$join_set[0][0] .'` as '.$join_set[0][1];
        } else {
            $tableName = "`$join_set[0]`";
        }
        $join_statement = 'LEFT JOIN ' . $tableName;
        $on_sets = array();
        foreach ($join_set[1] as $on_set) {
            $on_sets[] = self::buildConditionStatement($on_set);
        }

        if (!empty($on_sets)) {
            $join_statement .= ' ON ' . implode(' AND ', $on_sets);
        }

        return $join_statement;
    }

    /**
     * @param mixed $joins
     * @return string
     */
    public static function buildJoins($joins){
        return self::buildQueryWithMultipleStatements($joins, '', 'buildJoinStatement', '');
    }

    /**
     * @param array $havingSet
     * @return string
     */
    public static function buildHavingStatement($havingSet){
        if (substr($havingSet[1], 0, 1) == '.') {
            $havingSet[1] = substr($havingSet[1], 1, strlen($havingSet[1] - 1));
            return $havingSet[0] . " " . $havingSet[1] . " " . $havingSet[2] . " ";
        } else {
            return $havingSet[0] . " " . $havingSet[1] . " '" . $havingSet[2] . "' ";
        }
    }

    /**
     * @param mixed $having
     * @return string
     */
    public static function buildHaving($having){
        return self::buildQueryWithMultipleStatements($having, 'HAVING', 'buildHavingStatement');
    }

    /**
     * @param mixed $settings
     * @param string $operator
     * @param callable $buildStatement
     * @param string $glue
     * @return string
     */
    public static function buildQueryWithMultipleStatements($settings, $operator, $buildStatement, $glue = ','){
        $q = '';
        if(!empty($settings) && is_array($settings)) {
            $statements = array();
            foreach ($settings as $settingSet) {

                $statements[] = self::$buildStatement($settingSet);
            }

            $q .= ' ' . $operator . ' ' . implode($glue.' ', $statements);
        }

        return $q;
    }

    public static function buildOrderStatement($order_set_k, $order_set_v){
        if (!is_int($order_set_k)) {
            return $order_set_k . ' ' . $order_set_v;
        } else {
            return $order_set_v;
        }
    }

    /**
     * @param mixed $order
     * @return string
     */
    public static function buildOrder($order){
        return self::buildQueryWithMultipleStatements($order, 'ORDER BY', 'buildOrderStatement');
    }

    public static function fillDefaultSettings($settings){

        $defaultSettings = [
            'fields' => [],
            'joins' => [],
            'conditions' => [],
            'group' => '',
            'having' => [],
            'order' => [],
            'limit' => '',
        ];

        return array_replace($defaultSettings, $settings);
    }

    /**
     * @param mixed $group
     * @return string
     */
    public static function buildGroup($group){
        $q = '';
        if(!empty($group)) {
            $q .= ' GROUP BY ' . (string)$group;
        }
        return $q;
    }

    /**
     * @param mixed $limit
     * @return string
     */
    public static function buildLimit($limit){
        $q = '';
        if(!empty($limit)) {
            $q .= ' LIMIT ' . $limit;
        }
        return $q;
    }

    /**
     * @param string $table
     * @param array $settings
     * @return string
     */
    public static function buildSearchQuery($table, $settings){

        $settings = self::fillDefaultSettings($settings);

        $q = 'SELECT ';

        $q .= self::buildFields($settings['fields']);

        $q .= ' FROM `' . $table . '`';

        $q .= self::buildJoins($settings['joins']);

        $q .= self::buildConditions($settings['conditions']);

        $q .= self::buildGroup($settings['group']);

        $q .= self::buildHaving($settings['having']);

        $q .= self::buildOrder($settings['order']);

        $q .= self::buildLimit($settings['limit']);

        return $q;
    }



    public static function buildUpdateQuery($table, $data, $conditions){
        $q = 'UPDATE `' . $table . '` SET ';

        $fields = array();
        foreach ($data as $field => $value) {
            if(substr($field, -2) == '==') {
                $field = substr($field, 0, -2);
                $fields[] = $field . ' = ' . $value;
            } else{
                $fields[] = $field . ' = ' . self::prepare($value);
            }
        }

        $q .= implode(',', $fields);

        if (!empty($conditions)) {
            $q .= self::buildConditions($conditions);
        }

        return $q;
    }
}