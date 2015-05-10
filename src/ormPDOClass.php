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

        $this->stringHelper = new StringHelper();

        $this->config = self::fillDefaultConfig($config);

		$this->connect();
		$this->execute("SET NAMES '" . $this->config['charset'] . "'");
	}

    public static function fillDefaultConfig($config){

        if(empty($config)){
            $config = [];
        }

        $defaultConfig = [
            'charset' => 'utf8',
            'host' => 'localhost',
            'user' => 'root',
            'password' => 'root',
        ];

        return array_replace($defaultConfig, $config);
    }

    private function checkConnectionData(){
        if(empty($this->config['base'])){
            throw new \Exception('Base name is not specified');
        }
        if(empty($this->config['host'])){
            throw new \Exception('Host name is not specified');
        }
        if(empty($this->config['user'])){
            throw new \Exception('User is not specified');
        }
    }

    public static function buildConnectionString($config){
        $connectionString = 'mysql:';
        if (!empty($config['socket'])) {
            $connectionString .= 'unix_socket=' . $config['socket'] . ';';
        }
        $connectionString .= 'dbname=' . $config['base'] . ';host=' . $config['host'] . ';charset=' . $config['charset'];

        return $connectionString;
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
            $this->checkConnectionData();

            $connectionString = self::buildConnectionString($this->config);

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

    private function setMappedRow(&$data, $row, $map, $settings){
        $result = self::mapResultRow($row, $map);

        //as_key must have a structure like ['table' => table, 'field' => field]
        if (isset($settings['as_key']) && is_array($settings['as_key'])) {
            $data[$result[$settings['as_key'][0]][$settings['as_key'][1]]] = $result;
        } else {
            $data[] = $result;
        }
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
                $this->setMappedRow($data, $row, $map, $settings);
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

        $q = QueryBuilder::buildSearchQuery($table, $settings);

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

    private function staticQueryFirst($q, $fetchMethod, $withMap){
        $q .= ' LIMIT 1';

        return $this->staticQueryAll($q, $fetchMethod, $withMap);
    }

    private function staticQueryAll($q, $fetchMethod, $withMap){
        $ret = false;
        $res = $this->execute($q);

        if (!empty($res)) {
            $ret = $res->fetchAll($fetchMethod);
        }

        $ret = $this->staticWithMap($ret, $res, $withMap);

        return $ret;
    }
    
    private function staticQueryGetMethod($withMap){
        if(empty($withMap)) {
            $fetchMethod = PDO::FETCH_ASSOC;
        } else{
            $fetchMethod = PDO::FETCH_NUM;
        }
        return $fetchMethod;
    }

    private function staticWithMap($ret, $res, $withMap){
        if(!empty($withMap) && !empty($res) ) {
            $map = self::resultMap($res);
            $ret = [
                'data' => $res,
                'map' => $map
            ];
        }
        return $ret;
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

        $fetchMethod = $this->staticQueryGetMethod($withMap);

		switch ($type) {
			case 'first':
                $ret = $this->staticQueryFirst($q, $fetchMethod, $withMap);
				break;
			case 'all':
				$ret = $this->staticQueryAll($q, $fetchMethod, $withMap);
				break;
			case 'execute':
                $ret = $this->execute($q);
				break;
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
	 * @param string $table
	 * @param array $data
	 * @return mixed
	 */
	public function save($table, $data)
	{

		$q = QueryBuilder::buildSaveQuery($table, $data);

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
		$q = QueryBuilder::buildUpdateQuery($table, $data, $conditions);

        return $this->executeQueryAndReturnRowCount($q);
	}

	/**
	 * @param string $table
	 * @param array $conditions
	 * @return bool
	 */
	public function remove($table, $conditions = array())
	{
		$q = QueryBuilder::buildRemoveQuery($table, $conditions);

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

    private function debug($text){
        if ($this->debug) {
            print '<hr/>';
            print $text . ";\r\n";
        }
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

        $this->debug($sql);

		if ($this->fictive) {
			if (QueryBuilder::queryIsChangingData($sql)) {
                return NULL;
            }
		}

		try {
			$sth = $this->connection->prepare($sql);
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
			$var = $this->stringHelper->underscore(substr($method, 5));
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
			$var = $this->stringHelper->underscore(substr($method, 6));
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

}
