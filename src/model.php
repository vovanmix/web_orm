<?php

namespace Vovanmix\WebOrm;

class Model extends ormPDOClass{

	protected $table = '';

	public function __construct($config, $table, $connection=false) {
		$this->table = $table;

		#pass connection by link
		if(!empty($connection)) {
			$this->connection = &$connection;
		} else{
			parent::__construct($config);
		}
	}

	public function find( $type, $settings, $set=null ) {
		if(!empty($set)) {
			$settings = $set;
		}
		return parent::find( $type, $this->table, $settings );
	}

	public function get($settings, $set=null) {
		if(!empty($set)) {
			$settings = $set;
		}
		return parent::get($this->table, $settings);
	}

	public function exists($conditions, $idfield=null) {
		return parent::exists($this->table, $conditions, $idfield);
	}

	public function remove($conditions) {
		return parent::remove($this->table, $conditions);
	}

	public function save($data) {
		return parent::save($this->table, $data);
	}

	public function update($data, $conditions) {
		return parent::update($this->table, $data, $conditions);
	}

	public function __call($method, $params)
	{

		if (substr($method, 0, 5) == 'getBy') {
			$applicableMethod = 'get';
			$var = $this->stringHelper->underscore(substr($method, 5));
			$params = array(
				array(
					'conditions' => array(
						array($var, '=', $params[0])
					)
				)
			);
		} elseif (substr($method, 0, 6) == 'findBy') {
			$applicableMethod = 'find';
			$var = $this->stringHelper->underscore(substr($method, 6));
			$params = array(
				'all',
				array(
					'conditions' => array(
						array($var, '=', $params[0])
					)
				)
			);
		} else {
			return false;
		}

		return call_user_func_array(array($this, $applicableMethod), $params);
	}

}