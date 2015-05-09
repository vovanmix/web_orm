<?php

namespace Vovanmix\WebOrm;

use PDO;
use PDOException;
use PDOStatement;

class ormPDOClass
{

	const SETTING_CONDITIONS = 'conditions';
	const SETTING_FIELDS = 'fields';
	const SETTING_GROUP = 'group';
	const SETTING_HAVING = 'having';
	const SETTING_ORDER = 'order';
	const SETTING_JOINS = 'joins';
	const SETTING_LIMIT = 'limit';

	const FIND_SETTING_AS_KEY = 'as_key';
	const FIND_SETTING_TABLE_ARRAYS = 'table_arrays';
	const FIND_SETTING_CONDITIONS = 'conditions';
	const FIND_SETTING_FIELDS = 'fields';
	const FIND_SETTING_GROUP = 'group';
	const FIND_SETTING_HAVING = 'having';
	const FIND_SETTING_ORDER = 'order';
	const FIND_SETTING_JOINS = 'joins';
	const FIND_SETTING_LIMIT = 'limit';

	const FIND_FIRST = 'first';
	const FIND_ALL = 'all';
	const FIND_LIST = 'list';

	const CONDITION_AND = 'AND';
	const CONDITION_OR = 'OR';
	const CONDITION_NOT = 'NOT';

	const OP_EQUAL = '=';
	const OP_NOT_EQUAL = '!=';

	public $config;
	/**
	 * @var PDO $connection
	 */
	public $connection;

	public $debug = false;
	public $print_errors = true;
	public $fictive = false;
    public $testing = false;

    /**
     * @param $config
     * @param bool $testing
     * @throws \Exception
     */
	public function __construct($config, $testing=false)
	{
		$this->config = $config;
        $this->testing = $testing;
        if($this->testing){
            $this->fictive = true;
        }

		if (empty($this->config['charset'])) {
            $this->config['charset'] = 'utf8';
        }
		if (empty($this->config['host'])) {
            $this->config['host'] = 'localhost';
        }
        if (empty($this->config['user'])) {
            $this->config['user'] = 'root';
        }
        if (!isset($this->config['password'])) {
            $this->config['password'] = 'root';
        }

		$this->connect();
		$this->execute("SET NAMES '" . $this->config['charset'] . "'");
	}

    /**
     * @throws \Exception
     */
	private function connect()
	{
        if($this->testing){
            return;
        }
		try {
            if(empty($this->config['base'])){
                throw new \Exception('Base name is not specified');
            }
            if(empty($this->config['host'])){
                throw new \Exception('Host name is not specified');
            }
            if(empty($this->config['user'])){
                throw new \Exception('User is not specified');
            }

			$connectionString = 'mysql:';
			if (!empty($this->config['socket'])) {
				$connectionString .= 'unix_socket=' . $this->config['socket'] . ';';
			}
			$connectionString .= 'dbname=' . $this->config['base'] . ';host=' . $this->config['host'] . ';charset=' . $this->config['charset'];
			$dbConnection = new PDO($connectionString, $this->config['user'], $this->config['password']);
			$dbConnection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
			$dbConnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		} catch (PDOException $e) {
            throw new PDOException('Connection failed: ' . $e->getMessage());
		}

		$this->connection = & $dbConnection;
	}

	public static function CONDITION_EXACT($condition) {
		return '.'.$condition;
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
    public static function buildConditionSet($conditions, $is_sub_condition = false, $operation = 'AND')
	{
		$q = '';
		$condition_sets = array();

		foreach ($conditions as $condition_key => $condition_set) {

			if ($condition_key === 'OR') {
				$sub_operation = 'OR';
			} else {
				$sub_operation = 'AND';
			}

			#there is array of subsets
			if ((isset($condition_set[0]) && is_array($condition_set[0])) || (!isset($condition_set[0]))) {
				$condition_sets[] = ' (' . self::buildConditionSet($condition_set, true, $sub_operation) . ') ';
			} else {
				$condition_sets[] = self::buildCondition($condition_set);
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
    public static function buildCondition($condition_set) {
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
     * @param array $fields
     * @return string
     */
    public static function buildFields($fields){

        $q = '';
        $fieldsArray = array();
        
        if (!empty($fields)) {
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
            $on_sets[] = self::buildCondition($on_set);
        }

        if (!empty($on_sets)) {
            $join_statement .= ' ON ' . implode(' AND ', $on_sets);
        }

        return $join_statement;
    }

    /**
     * @param array $settings
     * @return string
     */
    public static function buildJoins($settings){
        $q= '';
        if(!empty($settings['joins'])) {
            $join_sets = array();
            foreach ($settings['joins'] as $join_set) {
                $join_sets[] = self::buildJoinStatement($join_set);
            }

            if (!empty($join_sets)) {
                $q .= ' ' . implode(' ', $join_sets);
            }
        }
        
        return $q;
    }

    public static function buildHavingStatement($havingSet){
        if (substr($havingSet[1], 0, 1) == '.') {
            $havingSet[1] = substr($havingSet[1], 1, strlen($havingSet[1] - 1));
            return $havingSet[0] . " " . $havingSet[1] . " " . $havingSet[2] . " ";
        } else {
            return $havingSet[0] . " " . $havingSet[1] . " '" . $havingSet[2] . "' ";
        }
    }

    /**
     * @param array $having
     * @return string
     */
    public static function buildHaving($having){
        $q = '';
        $havingSets = array();
        foreach ($having as $havingSet) {

            $havingSets[] = self::buildHavingStatement($havingSet);
        }

        $q .= ' HAVING ' . implode(', ', $havingSets);
        
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
     * @param array $order
     * @return string
     */
    public static function buildOrder($order){
        $q = '';
        $order_sets = array();
        foreach ($order as $order_set_k => $order_set_v) {
            $order_sets[] = self::buildOrderStatement($order_set_k, $order_set_v);
        }

        $q .= ' ORDER BY ' . implode(', ', $order_sets);

        return $q;
    }

    /**
     * @param string $table
     * @param array $settings
     * @return string
     */
    public static function buildSearchQuery($table, $settings){
        $q = 'SELECT ';

        $q .= self::buildFields(!empty($settings['fields']) ? $settings['fields'] : NULL);

        $q .= ' FROM `' . $table . '`';

        $q .= self::buildJoins($settings);

        if (!empty($settings['conditions'])) {
            $q .= self::buildConditionSet($settings['conditions']);
        }

        if (!empty($settings['group'])) {
            $q .= ' GROUP BY ' . $settings['group'];
        }

        if (!empty($settings['having'])) {
            $q .= self::buildHaving($settings['having']);
        }

        if (!empty($settings['order'])) {
            $q .= self::buildOrder($settings['order']);
        }

        if (!empty($settings['limit'])) {
            $q .= ' LIMIT ' . $settings['limit'];
        }

        return $q;
    }

    /**
     * @param PDOStatement $res
     * @param array $settings
     * @return array
     */
    private function fetchSearchFirst($res, $settings){
        if (!empty($settings['table_arrays'])) {
            $row = $res->fetch(PDO::FETCH_NUM);
            $map = self::resultMap($res);
            $row = self::mapResultRow($row, $map);
        } else {
            $row = $res->fetch(PDO::FETCH_ASSOC);
        }

        return $row;
    }

    /**
     * @param PDOStatement $res
     * @param array $settings
     * @return array
     */
    private function fetchSearchAll($res, $settings){
        $data = array();

        if (!empty($settings['table_arrays'])) {
            $tempData = $res->fetchAll(PDO::FETCH_NUM);
            $map = self::resultMap($res);
            foreach ($tempData as $row) {
                $result = self::mapResultRow($row, $map);

                //as_key must have a structure like ['table' => table, 'field' => field]
                if (isset($settings['as_key']) && is_array($settings['as_key'])) {
                    $data[$result[$settings['as_key'][0]][$settings['as_key'][1]]] = $result;
                } else {
                    $data[] = $result;
                }
            }
        } else {
            //as_key must be a string
            if (isset($settings['as_key'])) {
                while (($row = $res->fetch(PDO::FETCH_ASSOC)) !== false) {
                    $data[$row[$settings['as_key']]] = $row;
                }
            } else {
                $data = $res->fetchAll(PDO::FETCH_ASSOC);
            }
        }

        return $data;
    }

    /**
     * @param PDOStatement $res
     * @param array $settings
     * @return array
     */
    private function fetchSearchList($res, $settings){
        $data = array();

        while (($row = $res->fetch(PDO::FETCH_ASSOC)) !== false) {
            $data[$row[$settings['fields'][0]]] = $row[$settings['fields'][1]];
        }

        return $data;
    }

	/**
	 * @param string $type
	 * @param string $table
	 * @param array $settings
	 * - fields: array
	 * - conditions: array
	 * - joins: array[ [tableName, settings: array[conditions]] ]
	 * - group: string
	 * - having: array[conditions]
	 * - order: array[field: direction]
	 * - limit: string
	 * - table_arrays: bool
	 * - as_key: string | array[table, field]
	 * @return array|bool
	 */
	public function find($type, $table, $settings = array())
    {

        if ($type == 'first') {
            $settings['limit'] = 1;
        }

        $q = self::buildSearchQuery($table, $settings);

        $res = $this->execute($q);

        if (!empty($res)) {
            switch ($type) {
                case 'first':
                    return $this->fetchSearchFirst($res, $settings);
                case 'all':
                    return $this->fetchSearchAll($res, $settings);
                case 'list':
                    return $this->fetchSearchList($res, $settings);
            }
        }

		#if nothing were returned
		return false;

	}

    public static function mapResultRow($row, $map) {
        $result = [];
        foreach($row as $fieldNum => $fieldValue) {
            $mapForField = $map[$fieldNum];
            $result[ $mapForField['table'] ][ $mapForField['name'] ] = $fieldValue;
        }
        return $result;
    }

	/**
	 * @param string $q
	 * @param string $type
	 * @param bool $withMap
	 * @return array|bool|mixed
	 */
	public function query($q, $type='execute', $withMap=false)
	{
		$ret = false;

		if(empty($withMap)) {
			$fetchMethod = PDO::FETCH_ASSOC;
		} else{
			$fetchMethod = PDO::FETCH_NUM;
		}

		switch ($type) {
			case 'first':

				$q .= ' LIMIT 1';
				$res = $this->execute($q);

				if (!empty($res)) {
					$ret = $res->fetch($fetchMethod);
				}
				break;
			case 'all':
				$res = $this->execute($q);

				if (!empty($res)) {
					$ret = $res->fetchAll($fetchMethod);
				}
				break;
			case 'execute':
				$this->execute($q);
				break;
		}

		if(!empty($withMap)) {
			if( !empty($res) ) {
				$map = self::resultMap($res);
				$ret = [
					'data' => $ret,
					'map' => $map
				];
			}
		}

		return $ret;
	}

	/**
	 * @param PDOStatement $res
	 * @return array
	 */
    public static function resultMap($res) {
		$columns = [];

		for ($i = 0; $i < $res->columnCount(); $i++) {
			$col = $res->getColumnMeta($i);
			$columns[] = $col;
		}

		return $columns;
	}

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

	/**
	 * @param string $table
	 * @param array $data
	 * @return mixed
	 */
	public function save($table, $data)
	{

		$q = self::buildSaveQuery($table, $data);

		if ($this->execute($q)) {
			if (!$this->fictive) {
				$last_id = $this->lastInsertId();
				if(!empty($last_id)) {
					return $last_id;
				} else{
					$av = array_values($data);
					return $av[0];
				}
			} else{
				return uniqid();
			}
		} else {
			return false;
		}
	}

	public function lastInsertId() {
		return $this->connection->lastInsertId();
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
            $q .= self::buildConditionSet($conditions);
        }
        
        return $q;
    }

    private function executeQueryAndReturnRowCount($q){
        $result = $this->execute($q);

        if(!empty($result)) {
            return $result->rowCount();
        } else{
            return 0;
        }
    }

	/**
	 * @param string $table
	 * @param array $data
	 * @param array $conditions
	 * @return bool
	 */
	public function update($table, $data, $conditions = array())
	{
		$q = self::buildUpdateQuery($table, $data, $conditions);

        return $this->executeQueryAndReturnRowCount($q);
	}

    public static function buildRemoveQuery($table, $conditions){
        $q = 'DELETE FROM `' . $table . '`';

        if (!empty($conditions)) {
            $q .= self::buildConditionSet($conditions);
        }

        return $q;
    }

	/**
	 * @param string $table
	 * @param array $conditions
	 * @return bool
	 */
	public function remove($table, $conditions = array())
	{
		$q = self::buildRemoveQuery($table, $conditions);

        return $this->executeQueryAndReturnRowCount($q);
	}

	/**
	 * @param string $table
	 * @param array $conditions
	 * @param string $idField
	 * @return mixed
	 */
	public function exists($table, $conditions, $idField = null)
	{
		$res = $this->find('first', $table, array('conditions' => $conditions, 'fields' => array(!empty($idField) ? $idField : 'count(*) as cnt')));

		if (!empty($res)) {
			if (!empty($idField)) {
				return $res[$idField];
			} else {
				return $res['cnt'];
			}
		}

		#if nothing were returned
		return false;
	}

	/**
	 * @param string $table
	 * @param array $conditions
	 * @return integer
	 */
	public function count($table, $conditions = array())
	{
		$res = $this->find('first', $table, array('conditions' => $conditions, 'fields' => array('count(*) as cnt')));

		if (!empty($res)) {
			return $res['cnt'];
		}

		#if nothing were returned
		return 0;
	}

	/**
	 * @param string $sql
	 * @param array $params
	 * @return PDOStatement
	 */
	private function execute($sql, $params = array())
	{
        if($this->testing){
            return NULL;
        }

		if ($this->debug) {
			print '<hr/>';
			print $sql . ";\r\n";
		}

		if ($this->fictive) {
			if (strpos($sql, 'UPDATE ') !== false) {
                return NULL;
            }
			if (strpos($sql, 'INSERT INTO ') !== false) {
                return NULL;
            }
			if (strpos($sql, 'DELETE ') !== false) {
                return NULL;
            }
		}

		try {
			$sth = $this->connection->prepare($sql);
		} catch (PDOException $e) {
			$this->logExecutionError($sql, $e);
			return NULL;
		}

		try {
			$sth->execute($params);
		} catch (PDOException $e) {
            $this->logExecutionError($sql, $e);
			return NULL;
		}

		return $sth;

	}

    /**
     * @param $sql string
     * @param $e \Exception|PDOException
     */
    private function logExecutionError($sql, $e) {
        if ($this->debug) {
            print '<div>QUERY FAILED</div>."\r\n"';
            print '<div>' . $e->getMessage() . '</div>."\r\n"';
        } else {
            if ($this->print_errors) {
                print "" . $e->getMessage() . "\r\n" . $sql . "\r\n\r\n";
            } else {
                error_log($e->getMessage(), 0);
            }
        }
    }

	public function get($table, $settings = array())
	{
		return $this->find('first', $table, $settings);
	}

	public function __call($method, $params)
	{

		if (substr($method, 0, 5) == 'getBy') {
			$applicableMethod = 'get';
			$var = $this->underscore(substr($method, 5));
			$params = array(
				$params[0], //table
				array(
					'conditions' => array(
						array($var, '=', $params[1])
					)
				)
			);
		} elseif (substr($method, 0, 6) == 'findBy') {
			$applicableMethod = 'find';
			$var = $this->underscore(substr($method, 6));
			$params = array(
				'all',
				$params[0], //table
				array(
					'conditions' => array(
						array($var, '=', $params[1])
					)
				)
			);
		} else {
			return false;
		}

		return call_user_func_array(array($this, $applicableMethod), $params);
	}

    public static $_cache;

    /**
     * @param mixed $type
     * @param mixed $key
     * @param mixed $value
     * @return bool
     */
	protected static function _cache($type, $key, $value = false)
	{
		$key = '_' . $key;
		$type = '_' . $type;
		if ($value !== false) {
			self::$_cache[$type][$key] = $value;
			return $value;
		}
		if (!isset(self::$_cache[$type][$key])) {
			return false;
		}
		return self::$_cache[$type][$key];
	}

	/**
	 * Returns the given lower_case_and_underscored_word as a CamelCased word.
	 *
	 * @param string $lowerCaseAndUnderscoredWord Word to camelize
	 * @return string Camelized word. LikeThis.
	 */
	public static function camelize($lowerCaseAndUnderscoredWord)
	{
		if (!($result = self::_cache(__FUNCTION__, $lowerCaseAndUnderscoredWord))) {
            $result = str_replace(' ', '', self::humanize($lowerCaseAndUnderscoredWord));
            self::_cache(__FUNCTION__, $lowerCaseAndUnderscoredWord, $result);
		}
		return $result;
	}

    /**
     * @param $lowerCaseAndUnderscoredWord
     * @return bool|string
     */
    public static function humanize($lowerCaseAndUnderscoredWord)
	{
		if (!($result = self::_cache(__FUNCTION__, $lowerCaseAndUnderscoredWord))) {
			$result = ucwords(str_replace('_', ' ', $lowerCaseAndUnderscoredWord));
			self::_cache(__FUNCTION__, $lowerCaseAndUnderscoredWord, $result);
		}
		return $result;
	}

	/**
	 * Returns the given camelCasedWord as an underscored_word.
	 *
	 * @param string $camelCasedWord Camel-cased word to be "underscorized"
	 * @return string Underscore-syntaxed version of the $camelCasedWord
	 */
    public static function underscore($camelCasedWord)
	{
		if (!($result = self::_cache(__FUNCTION__, $camelCasedWord))) {
			$result = strtolower(preg_replace('/(?<=\\w)([A-Z])/', '_\\1', $camelCasedWord));
			self::_cache(__FUNCTION__, $camelCasedWord, $result);
		}
		return $result;
	}

}