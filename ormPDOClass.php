<?php

namespace vovanmix\web_orm;

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

	public function __construct($config)
	{
		$this->config = $config;

		if (empty($this->config['charset']))
			$this->config['charset'] = 'utf8';

		if (empty($this->config['host']))
			$this->config['host'] = 'localhost';

		$this->connect();
		$this->execute("SET NAMES '" . $this->config['charset'] . "'");
	}

	private function connect()
	{
		try {
			$connectionString = 'mysql:';
			if (!empty($this->config['socket'])){
				$connectionString .= 'unix_socket=' . $this->config['socket'] . ';';
			}
			$connectionString .= 'dbname=' . $this->config['base'] . ';host=' . $this->config['host'] . ';charset=' . $this->config['charset'];
			$dbConnection = new PDO($connectionString, $this->config['user'], $this->config['password']);
			$dbConnection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
			$dbConnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		} catch (PDOException $e) {
			die('Connection failed: ' . $e->getMessage());
		}

		$this->connection = & $dbConnection;
	}

	public static function CONDITION_EXACT($condition){
		return '.'.$condition;
	}

	/**
	 * @param $value
	 * @return mixed|string
	 */
	private function prepare($value)
	{

		if( is_string($value) ){
			$value = str_replace("'", '`', $value);
			$value = addslashes(trim($value));
		}
		if(is_array($value)){
			$value = (string)$value;
		}

		$value = !empty($value) ? "'" . $value . "'" : (($value === 0 || $value === '0') ? '0' : ($value === '' ? '""' : 'NULL'));

		return $value;
	}

	/**
	 * @param $conditions
	 * @param bool $is_sub_condition
	 * @param string $operation
	 * @return string
	 */
	private function build_conditions($conditions, $is_sub_condition = false, $operation = 'AND')
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
				$condition_sets[] = ' (' . $this->build_conditions($condition_set, true, $sub_operation) . ') ';
			} else {
				$condition_sets[] = $this->process_conditions($condition_set);
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

	/**
	 * @param $condition_set
	 * @return string
	 */
	private function process_conditions($condition_set){
		if (is_array($condition_set[2])) {
			$condition_arr = array();
			foreach ($condition_set[2] as $c) {
				$condition_arr[] = (!empty($c) || $c === 0 || $c === '0') ? "'" . $c . "'" : 'NULL';
			}
			if($condition_set[1] == '='){
				$condition_set[1] = 'IN';
			}
			elseif($condition_set[1] == '!=' || $condition_set[1] == '<>'){
				$condition_set[1] = 'NOT IN';
			}
			if(empty($condition_arr)) $condition_arr = array('NULL');
			$result = $condition_set[0] . " " . $condition_set[1] . " (" . implode(',', $condition_arr) . ") ";
		} elseif (empty($condition_set[2])) {
			if ($condition_set[1] == 'NOT' || $condition_set[1] == '!=' || $condition_set[1] == '<>')
				$result = "(" . $condition_set[0] . " IS NOT NULL OR " . $condition_set[0] . " != '' OR " . $condition_set[0] . " != 0" . ")";
			elseif ($condition_set[1] == '=')
				$result = "(" . $condition_set[0] . " IS NULL OR " . $condition_set[0] . " = '' OR " . $condition_set[0] . " = 0" . ")";
			else{
				if($condition_set[2] === '') $condition_set[2] = "''";
				$result = "(" . $condition_set[0] . " " . $condition_set[1] . " " . $condition_set[2] . ")";
			}
		} else {
			if (substr($condition_set[1], 0, 1) == '.') {
				$condition_set[1] = substr($condition_set[1], 1, strlen($condition_set[1] - 1));
				$result = $condition_set[0] . " " . $condition_set[1] . " " . $condition_set[2] . " ";
			} elseif( is_numeric($condition_set[2]) ){
				$result = $condition_set[0] . " " . $condition_set[1] . " " . $condition_set[2] . " ";
			}
			else{
				$result = $condition_set[0] . " " . $condition_set[1] . " '" . $condition_set[2] . "' ";
			}
		}
		return $result;
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
	 * @return array
	 */
	public function find($type, $table, $settings = array())
	{
		$affectedTables = [];

		$q = 'SELECT ';

		$fields = array();

		if (!empty($settings['fields'])) {
			foreach ($settings['fields'] as $field_key => $field_name) {
				if ((int)$field_key !== $field_key) {
					$fields[] = $field_key . ' as ' . $field_name;
				} else {
					$fields[] = $field_name;
				}
			}
			$q .= implode(',', $fields);
		} else {
			$q .= ' * ';
		}

		$q .= ' FROM `' . $table . '`';
		$affectedTables[] = $table;

		if (!empty($settings['joins'])) {
			$join_sets = array();
			foreach ($settings['joins'] as $join_set) {
				if(is_array($join_set[0])){
					$tableName = '`'.$join_set[0][0] .'` as '.$join_set[0][1];
					$affectedTables[] = $join_set[0][0];
				} else {
					$tableName = "`$join_set[0]`";
					$affectedTables[] = $join_set[0];
				}
				$join_statement = ' LEFT JOIN ' . $tableName;
				$on_sets = array();
				foreach ($join_set[1] as $on_set) {
					$on_sets[] = $this->process_conditions($on_set);
				}

				if (!empty($on_sets)) {
					$join_statement .= ' ON ' . implode(' AND ', $on_sets);
				}

				$join_sets[] = $join_statement;
			}

			if (!empty($join_sets)) {
				$q .= ' ' . implode(' ', $join_sets);
			}
		}


		if (!empty($settings['conditions'])) {

			$q .= $this->build_conditions($settings['conditions']);

		}


		if (!empty($settings['group'])) {
			$q .= ' GROUP BY ' . $settings['group'];
		}


		if (!empty($settings['having'])) {

			$having_sets = array();
			foreach ($settings['having'] as $having_set) {

				if (substr($having_set[1], 0, 1) == '.') {
					$having_set[1] = substr($having_set[1], 1, strlen($having_set[1] - 1));
					$having_sets[] = $having_set[0] . " " . $having_set[1] . " " . $having_set[2] . " ";
				} else
					$having_sets[] = $having_set[0] . " " . $having_set[1] . " '" . $having_set[2] . "' ";
			}

			$q .= ' HAVING ' . implode(', ', $having_sets);
		}

		if (!empty($settings['order'])) {

			$order_sets = array();
			foreach ($settings['order'] as $order_set_k => $order_set_v) {

				if (!is_int($order_set_k)) {
					$order_sets[] = $order_set_k . ' ' . $order_set_v;
				} else {
					$order_sets[] = $order_set_v;
				}
			}

			$q .= ' ORDER BY ' . implode(', ', $order_sets);
		}


		if (!empty($settings['limit'])) {
			$q .= ' LIMIT ' . $settings['limit'];
		}


		switch ($type) {
			case 'first':

				$q .= ' LIMIT 1';
				$res = $this->execute($q, 'select', $affectedTables);

				if (!empty($res)) {
					if (!empty($settings['table_arrays'])) {
						$row = $res->fetch(PDO::FETCH_NUM);
						$map = $this->resultMap($res);
						$result = [];
						foreach($row as $fieldNum => $fieldValue){
							$mapForField = $map[$fieldNum];
							$result[ $mapForField['table'] ][ $mapForField['name'] ] = $fieldValue;
						}
					} else{
						$row = $res->fetch(PDO::FETCH_ASSOC);
					}

					return $row;
				}
				break;
			case 'all':
				$res = $this->execute($q, 'select', $affectedTables);

				if (!empty($res)) {
					$data = array();

					if (!empty($settings['table_arrays'])) {
						$tempData = $res->fetchAll(PDO::FETCH_NUM);
						$map = $this->resultMap($res);
						$data = [];
						foreach($tempData as $row) {
							$result = [];
							foreach($row as $fieldNum => $fieldValue){
								$mapForField = $map[$fieldNum];
								$result[ $mapForField['table'] ][ $mapForField['name'] ] = $fieldValue;
							}

							//as_key must have a structure like ['table' => table, 'field' => field]
							if (isset($settings['as_key']) && is_array($settings['as_key'])) {
								$data[ $result[$settings['as_key'][0]][$settings['as_key'][1]] ] = $result;
							} else {
								$data[] = $result;
							}
						}
					}
					else {
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
				break;
			case 'list':
				$res = $this->execute($q, 'select', $affectedTables);

				if (!empty($res)) {
					$data = array();

					while (($row = $res->fetch(PDO::FETCH_ASSOC)) !== false) {
						$data[$row[$settings['fields'][0]]] = $row[$settings['fields'][1]];
					}

					return $data;
				}
				break;
		}

		#if nothing were returned
		return false;

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
		}else{
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

		if(!empty($withMap)){
			if( !empty($res) ) {
				$map = $this->resultMap($res);
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
	private function resultMap($res) {
		$columns = [];

		for ($i = 0; $i < $res->columnCount(); $i++) {
			$col = $res->getColumnMeta($i);
			$columns[] = $col;
		}

		return $columns;
	}


	/**
	 *
	 * @param string $table
	 * @param array $data
	 * @return mixed
	 */
	public function save($table, $data)
	{

		$q = 'INSERT INTO `' . $table .'`';

		$fields = array();
		$values = array();
		foreach ($data as $field => $value) {
			$fields[] = $field;
			$values[] = $this->prepare($value);
		}

		$q .= ' (' . implode(',', $fields) . ')';
		$q .= ' VALUES (' . implode(',', $values) . ')';

		if ($this->execute($q, 'insert', $table)) {
			if (!$this->fictive) {
				$last_id = $this->lastInsertId();
				if(!empty($last_id)){
					return $last_id;
				}
				else{
					$av = array_values($data);
					return $av[0];
				}
			}
			else{
				return uniqid();
			}
		} else {
			return false;
		}
	}

	public function lastInsertId(){
		return $this->connection->lastInsertId();
	}

	/**
	 *
	 * @param string $table
	 * @param array $data
	 * @param array $conditions
	 * @return bool
	 */
	public function update($table, $data, $conditions = array())
	{
		$q = 'UPDATE `' . $table . '` SET ';

		$fields = array();
		foreach ($data as $field => $value) {
			if(substr($field, -2) == '=='){
				$field = substr($field, 0, -2);
				$fields[] = $field . ' = ' . $value;
			}
			else{
				$fields[] = $field . ' = ' . $this->prepare($value);
			}
		}

		$q .= implode(',', $fields);

		if (!empty($conditions)) {

			$q .= $this->build_conditions($conditions);

		}

//		debug($q); return;

		return $this->execute($q, 'update', $table)->rowCount();
	}


	/**
	 *
	 * @param string $table
	 * @param array $conditions
	 * @return bool
	 */
	public function remove($table, $conditions = array())
	{
		$q = 'DELETE FROM `' . $table . '`';

		if (!empty($conditions)) {

			$q .= $this->build_conditions($conditions);

		}

		return $this->execute($q, 'delete', $table)->rowCount();
	}


	/**
	 *
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
	 *
	 * @param string $table
	 * @param array $conditions
	 * @return mixed
	 */
	public function count($table, $conditions = array())
	{
		$res = $this->find('first', $table, array('conditions' => $conditions, 'fields' => array('count(*) as cnt')));

		if (!empty($res)) {
			return $res['cnt'];
		}

		#if nothing were returned
		return false;
	}

	/**
	 * @param string $sql
	 * @param string $operation
	 * @param array $tables
	 * @param array $params
	 * @return PDOStatement
	 */
	private function execute($sql, $operation=null, $tables=null, $params = array())
	{

		if ($this->debug) {
			print '<hr/>';
			print $sql . ";\r\n";
		}

		if ($this->fictive) {
			if (strpos($sql, 'UPDATE ') !== false)
				return true;
			if (strpos($sql, 'INSERT INTO ') !== false)
				return true;
			if (strpos($sql, 'DELETE ') !== false)
				return true;
		}

		try {
			$sth = $this->connection->prepare($sql);
		} catch (PDOException $e) {
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
			return false;
		}

		try {
			$sth->execute($params);
		} catch (PDOException $e) {
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
			return false;
		}

		return $sth;

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
	protected function camelize($lowerCaseAndUnderscoredWord)
	{
		if (!($result = self::_cache(__FUNCTION__, $lowerCaseAndUnderscoredWord))) {
			$result = str_replace(' ', '', self::humanize($lowerCaseAndUnderscoredWord));
			self::_cache(__FUNCTION__, $lowerCaseAndUnderscoredWord, $result);
		}
		return $result;
	}


	protected static function humanize($lowerCaseAndUnderscoredWord)
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
	protected function underscore($camelCasedWord)
	{
		if (!($result = self::_cache(__FUNCTION__, $camelCasedWord))) {
			$result = strtolower(preg_replace('/(?<=\\w)([A-Z])/', '_\\1', $camelCasedWord));
			self::_cache(__FUNCTION__, $camelCasedWord, $result);
		}
		return $result;
	}

}