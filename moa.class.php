<?php
namespace ay\moa;

use PDO;

abstract class Moa implements \ArrayAccess {
	protected
		$db,
		$data = [],
		$redis,
		$foreign_instances = [];
	
	/**
	 * @param int $primary_key Initialises the object by attempting to fetch data from the database using the primary key.
	 * @param array $primary_key Initialises the object using data in the array.
	 */
	public function __construct (\PDO $db, $primary_key = null) {
		$this->db = $db;
		$this->redis = $redis;
		
		if (is_array($primary_key)) {
			$this->data = $primary_key;
		} else if ($primary_key) {
			if ($this->data === []) {
				$this->data = $this->getByPrimaryKey($primary_key);
				
				if (!$this->data) {
					throw new Data_Exception('Object not found.');
				}
				
				if ($redis) {
					$redis->setex($key, $ttl, $this->data);
				}
			}
		}
	}
	
	public function getDatabaseInstance () {
		return $this->db;
	}
	
	protected function getByPrimaryKey ($primary_key) {
		$data = $this->db
			->prepare("SELECT " . static::$select_statement . " FROM `" . static::$table_name . "` `" . static::$table_alias . "` WHERE `" . static::$table_alias . "`.`" . static::$primary_key_name . "` = ?;")
			->execute([$primary_key])
			->fetch(PDO::FETCH_ASSOC);
		
		return $data;
	}
	
	public function offsetExists ($offset) {
		return isset($this->data[$offset]);
	}
	
	public function offsetGet ($offset) {
		return $this->data[$offset];
	}
	
	public function offsetSet ($offset, $value) {
		return $this->set($offset, $value);
	}
	
	public function offsetUnset ($offset) {
		unset($this->data[$offset]);
	}
	
	protected function validateInput ($name, $value) {}
	
	/**
	 * @param string $data Name of the object property.
	 * @param array $data
	 * @param string $value
	 */
	final public function set ($data, $value = null) {
		if (is_array($data)) {
			foreach ($data as $name => $value) {
				$this->set($name, $value);
			}
			
			return $this;
		}
	
		if ($data === static::$primary_key_name) {
			throw new Data_Exception('Object primary key value cannot be overwritten.');
		} else if (!array_key_exists($data, static::$table_columns)) {
			throw new Data_Exception('Object has no property "' . $data . '".');
		}
		
		if (static::$table_columns[$data]['character_maximum_length'] !== null && static::$table_columns[$data]['character_maximum_length'] < mb_strlen($value)) {
			// This implementation assumes unicode at all time.
			
			throw new Data_Exception('Property "' . $data . '" length limit is ' . static::$table_columns[$data]['character_maximum_length'] . ' characters.');
		}
		
		$this->validateInput($data, $value);
		
		if (isset($this->data[$data]) && $this->data[$data] === $value) {
			return false;
		}
		
		$this->data[$data] = $value;
		
		return true;
	}
	
	final public function get ($name) {
		if (!array_key_exists($name, $this->data)) {
			throw new Data_Exception('Unlisted object "' . static::$table_name . '" property "' . $name . '".');
		}
		
		return $this->data[$name];
	}
	
	final public function flatten () {
		return $this->data;
	}
	
	final public function save () {
		$required_columns = array_filter(static::$table_columns, function ($e) {
			return $e['column_key'] !== 'PRI' && $e['is_nullable'] === 'NO' && !isset($e['column_default']);
		});
	
		$missing_data = array_diff_key($required_columns, array_filter($this->data, function ($e) { return !is_null($e); }));
		
		if ($missing_data) {
			throw new Data_Exception('Attempt to initialise object (' . get_called_class() . ') without the required parameters (' . implode(', ', array_keys($missing_data)) . ').');
		}
		
		$parameters = $this->data;
		
		unset($parameters[static::$primary_key_name]);
		
		$placeholders = implode(', ', array_map(function ($name, $value) {
			$column = static::$table_columns[$name];
			
			if ($value !== null && in_array($column['column_type'], ['datetime', 'timestamp'])) {
				if (!is_int($value)) {
					throw new Data_Exception('Timestamp or datetime must be defined as a unix timestamp. "' . $value . '" (' . gettype($value) . ') is given instead.');
				}
				
				return '`' . $name . '` = FROM_UNIXTIME(:' . $name . ')';
			} else {
				return '`' . $name . '` = :' . $name;
			}
		}, array_keys($parameters), array_values($parameters)));
		
		try {
			if (isset($this->data[static::$primary_key_name])) {
				$sth = $this->db
					->prepare("UPDATE `" . static::$table_name . "` `" . static::$table_alias . "` SET {$placeholders} WHERE `" . static::$table_alias . "`.`" . static::$primary_key_name . "` = :" . static::$primary_key_name . ";");
			} else {
				$sth = $this->db
					->prepare("INSERT INTO `" . static::$table_name . "` SET {$placeholders};");
			}
			
			foreach ($this->data as $k => $v) {
				$sth->bindValue($k, $v, static::$table_columns[$k]['parameter_type']);
			}
			
			$sth->execute();
		} catch (\PDOException $e) {
			if ($e->getCode() === '23000') {
				// http://stackoverflow.com/questions/20077309/in-case-of-integrity-constraint-violation-how-to-tell-the-columns-that-are-caus
				
				preg_match('/(?<=\')[^\']*(?=\'[^\']*$)/', $e->getMessage(), $match);
				
				$indexes = $this->db
					->prepare("SHOW INDEXES FROM `" . static::$table_name . "` WHERE `Key_name` = :key_name;")
					->execute(['key_name' => $match[0]])
					->fetchAll(\PDO::FETCH_ASSOC);
				
				$columns = array_map(function ($e) { return $e['Column_name']; }, $indexes);
				
				if (count($columns) > 1) {
					throw new Data_Exception('"' . implode(', ', $columns) . '" column combination must have a unique value.', 0, $e);
				} else {
					throw new Data_Exception('"' . $columns[0] . '" column must have a unique value.', 0, $e);
				}
				
	
			}
			
			throw $e;
		}
		
		
		if (isset($this->data[static::$primary_key_name])) {
			$this->afterUpdate();
		} else {
			$this->data[static::$primary_key_name] = $this->db->lastInsertId();
		
			$this->afterInsert();
		}
		
		$this->data = $this->db
			->prepare("SELECT " . static::$select_statement . " FROM `" . static::$table_name . "` `" . static::$table_alias . "` WHERE `" . static::$table_alias . "`.`" . static::$primary_key_name . "` = ?;")
			->execute([$this->data[static::$primary_key_name]])
			->fetch(PDO::FETCH_ASSOC);
		
		return $this;
	}
	
	final public function delete () {
		if (!isset($this->data[static::$primary_key_name])) {
			throw new Data_Exception('Object does not have primary key value. Uninitialised object.');
		}
		
		$this->db->beginTransaction();
		
		$result = $this->db
			->prepare("DELETE FROM `" . static::$table_name . "` WHERE `" . static::$primary_key_name . "` = ?;")
			->execute([$this->data[static::$primary_key_name]]);
		
		$this->afterDelete();
		
		$this->db->commit();
		
		unset($this->data[static::$primary_key_name]);
		
		return $result;
	}
	
	public function afterInsert () {}
	public function afterUpdate () {}
	public function afterDelete () {}
}