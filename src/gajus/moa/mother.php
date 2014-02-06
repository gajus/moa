<?php
namespace gajus\moa;

/**
 * @link https://github.com/gajus/moa for the canonical source repository
 * @license https://github.com/gajus/moa/blob/master/LICENSE BSD 3-Clause
 */
abstract class Mother implements \ArrayAccess {
	protected
		$db,
		$data = [];
	
	/**
	 * @param PDO $db
	 * @param int|array $primary_key Initialises the object with data fetched from the database using the primary key. If $primary_key is array the object is initialised using the data in the array.
	 */
	public function __construct (\PDO $db, $data = null) {
		$this->db = $db;
		
		if (is_array($data)) {
			// @todo There should be some sort of sanity check.
			$this->data = $data;
		} else if ($data) {
			if ($this->data === []) {
				$this->data = $this->getByPrimaryKey($data);
				
				if (!$this->data) {
					throw new \gajus\moa\exception\Data_Exception('Object not found.');
				}
			}
		} else {
			$this->data = [];

			foreach (static::$properties['columns'] as $name => $column) {
				$this->data[$name] = null;
			}
		}
	}
	
	#public function getDatabaseHandle () {
	#	return $this->db;
	#}
	
	/**
	 * @param integer|string $primary_key
	 * @return mixed
	 */
	private function getByPrimaryKey ($primary_key) {
		$data = $this->db
			->prepare("SELECT " . static::$properties['select_statement'] . " FROM `" . static::$properties['table_name'] . "` WHERE `" . static::$properties['table_name'] . "`.`" . static::$properties['primary_key_name'] . "` = ?")
			->execute([$primary_key])
			->fetch(PDO::FETCH_ASSOC);
		
		return $data;
	}
	
	/**
	 * @param mixed $offset
	 * @return boolean
	 */
	public function offsetExists ($offset) {
		return isset($this->data[$offset]);
	}
	
	/**
	 * @param mixed $offset
	 * @return mixed
	 */
	public function offsetGet ($offset) {
		return $this->data[$offset];
	}
	
	/**
	 * @param mixed $offset
	 * @return void
	 */
	public function offsetSet ($offset, $value) {
		$this->set($offset, $value);
	}
	
	/**
	 * @param mixed $offset
	 * @return void
	 */
	public function offsetUnset ($offset) {
		unset($this->data[$offset]);
	}
	
	//protected function validateInput ($name, $value) {}
	
	/**
	 * @param array $data
	 * @return gajus\moa\Mother
	 */
	public function populate (array $data) {
		foreach ($data as $name => $value) {
			$this->set($name, $value);
		}

		return $this;
	} 

	/**
	 * @param string $name
	 * @param mixed $value
	 * @return boolean True if set resulted in a change.
	 */
	final public function set ($name, $value = null) {
		if ($name === static::$properties['primary_key_name']) {
			throw new \gajus\moa\Logic_Exception('Object primary key value cannot be overwritten.');
		} else if (!isset(static::$properties['columns'][$name])) {
			throw new \gajus\moa\Logic_Exception('Trying to set non-object property.');
		}
		
		if (static::$properties['columns'][$name]['character_maximum_length'] !== null && static::$properties['columns'][$name]['character_maximum_length'] < mb_strlen($value)) {
			// This implementation assumes unicode at all time.
			
			throw new \gajus\moa\Logic_Exception('Property "' . $name . '" length limit is ' . static::$properties['columns'][$name]['character_maximum_length'] . ' characters.');
		}
		
		//$this->validateInput($data, $value);
		
		if (isset($this->data[$name]) && $this->data[$name] === $value) {
			return false;
		}
		
		$this->data[$name] = $value;
		
		return true;
	}
	
	final public function get ($name) {
		if (!array_key_exists($name, $this->data)) {
			throw new \gajus\moa\Logic_Exception('Trying to get non-object property.');
		}
		
		return $this->data[$name];
	}
	
	final public function flatten () {
		return $this->data;
	}
	
	/**
	 * Save the object to the database. Depending on whether object's primary key is set
	 * this method will either attempt to insert the object to the database or update
	 * existing entry.
	 *
	 * @return gajus\moa\Mother
	 */
	final public function save () {
		foreach (static::$properties['columns'] as $name => $column) {
			if ($column['is_nullable'] === 'NO' && is_null($this->data[$name])) {
				unset($this->data[$name]);
			}

			if ($column['is_nullable'] === 'NO' && is_null($column['column_default']) && !isset($this->data[$name])) {
				throw new \gajus\moa\Logic_Exception('Object initialised without the required properties.');
			}
		}

		bump($this->data);
		
		$parameters = $this->data;
		
		unset($parameters[static::$properties['primary_key_name']]);
		
		$placeholders = implode(', ', array_map(function ($name, $value) {
			$column = static::$properties['columns'][$name];

			if ($value !== null && in_array($column['column_type'], ['datetime', 'timestamp'])) {
				if (!is_int($value)) {
					throw new \gajus\moa\Invalid_Argument_Exception('Timestamp or datetime must be defined as a unix timestamp. "' . $value . '" (' . gettype($value) . ') is given instead.');
				}
				
				return '`' . $name . '` = FROM_UNIXTIME(:' . $name . ')';
			} else {
				return '`' . $name . '` = :' . $name;
			}
		}, array_keys($parameters), array_values($parameters)));
		
		try {
			if (isset($this->data[static::$properties['primary_key_name']])) {
				$sth = $this->db
					->prepare("UPDATE `" . static::$properties['table_name'] . "` `" . static::$properties['table_name'] . "` SET {$placeholders} WHERE `" . static::$properties['table_name'] . "`.`" . static::$properties['primary_key_name'] . "` = :" . static::$properties['primary_key_name']);
			} else {
				bump($placeholders, $this->data);

				$sth = $this->db
					->prepare("INSERT INTO `" . static::$properties['table_name'] . "` SET {$placeholders};");
			}
			
			foreach ($this->data as $k => $v) {
				$sth->bindValue($k, $v, static::$properties['columns'][$k]['parameter_type']);
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
					throw new \gajus\moa\Logic_Exception('"' . implode(', ', $columns) . '" column combination must have a unique value.', 0, $e);
				} else {
					throw new \gajus\moa\Logic_Exception('"' . $columns[0] . '" column must have a unique value.', 0, $e);
				}
				
	
			}
			
			throw $e;
		}
		
		
		if (isset($this->data[static::$properties['primary_key_name']])) {
			$this->afterUpdate();
		} else {
			$this->data[static::$properties['primary_key_name']] = $this->db->lastInsertId();
		
			$this->afterInsert();
		}
		
		$this->data = $this->db
			->prepare("SELECT " . static::$properties['select_statement'] . " FROM `" . static::$properties['table_name'] . "` WHERE `" . static::$properties['table_name'] . "`.`" . static::$properties['primary_key_name'] . "` = ?")
			->execute([$this->data[static::$properties['primary_key_name']]])
			->fetch(\PDO::FETCH_ASSOC);
		
		return $this;
	}
	
	/**
	 * Delete object from the database. Deleted object retains its data except for the primary key value.
	 * Saving deleted object will cause the object to be created with a new primary key value.
	 * 
	 * @return gajus\moa\Mother
	 */
	final public function delete () {
		if (!isset($this->data[static::$properties['primary_key_name']])) {
			throw new \gajus\moa\exception\Logic_Exception('Object\'s primary key value is unset.');
		}
		
		$this->db->beginTransaction();
		
		$this->db
			->prepare("DELETE FROM `" . static::$properties['table_name'] . "` WHERE `" . static::$properties['primary_key_name'] . "` = ?")
			->execute([$this->data[static::$properties['primary_key_name']]]);
		
		$this->afterDelete();
		
		$this->db->commit();
		
		unset($this->data[static::$properties['primary_key_name']]);

		return $this;
	}
	
	/**
	 * Method called after object is insterted to the database.
	 * 
	 * @return void
	 */
	protected function afterInsert () {}

	/**
	 * Method called after object is updated in the database.
	 * 
	 * @return void
	 */
	protected function afterUpdate () {}

	/**
	 * Method called after object is deleted from the database.
	 * 
	 * @return void
	 */
	protected function afterDelete () {}
}