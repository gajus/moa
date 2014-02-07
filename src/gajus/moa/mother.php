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
	 * @param int|array|null $primary_key Initialises the object with data fetched from the database using the primary key. If $primary_key is array the object is initialised using the data in the array.
	 */
	public function __construct (\PDO $db, $data = null) {
		$this->db = $db;
		
		if (is_int($data)) {
			$this->data[static::PRIMARY_KEY_NAME] = $data;

			$this->synchronise();
		} else if (is_array($data)) {
			if (isset($data[static::PRIMARY_KEY_NAME])) {
				$this->data[static::PRIMARY_KEY_NAME] = $data[static::PRIMARY_KEY_NAME];

				unset($data[static::PRIMARY_KEY_NAME]);
			}

			$this->populate($data);
		} else if (is_null($data)) {
			$this->data = [];
		} else {
			throw new \gajus\moa\exception\Invalid_Argument_Exception('Invalid argument type.');
		}

		#foreach (static::$properties['columns'] as $name => $column) {
		#	$this->data[$name] = null;
		#}
	}

	/**
	 * Use the primary key to update object instance with the data from the database.
	 * 
	 * @return void
	 */
	final private function synchronise () {
		if (!isset($data[static::PRIMARY_KEY_NAME])) {
			throw new \gajus\moa\exception\Logic_Exception('Primary key is not set.');
		}

		$this->data = $this->db
			->prepare("SELECT * FROM `" . static::TABLE_NAME . "` WHERE `" . static::PRIMARY_KEY_NAME . "` = ?")
			->execute([$data[static::PRIMARY_KEY_NAME]])
			->fetch(\PDO::FETCH_ASSOC);

		if (!$this->data) {
			throw new \gajus\moa\exception\Record_Not_Found('Primary key value does not refer to an existing record.');
		}

		foreach (static::$columns as $name => $column) {
			if ($column['column_type'] === 'datetime' || $column['column_type'] === 'timestamp') {
				$this->data[$name] = strtotime($this->data[$name]);
			}
		}
	}
	
	#public function getDatabaseHandle () {
	#	return $this->db;
	#}
	
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
	 * Shorthand method to pass each array key, value pair to the setter.
	 *
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
	 * @param string $name Property name.
	 * @param mixed $value
	 * @return boolean True if setter affected the result set.
	 */
	public function set ($name, $value = null) {
		if ($name === static::PRIMARY_KEY_NAME) {
			throw new \gajus\moa\exception\Logic_Exception('Object primary key value cannot be overwritten.');
		} else if (!isset(static::$columns[$name])) {
			throw new \gajus\moa\exception\Logic_Exception('Trying to set non-object property.');
		}

		// @todo Detect encoding type.
		if (static::$columns[$name]['character_maximum_length'] !== null && static::$columns[$name]['character_maximum_length'] < mb_strlen($value)) {
			throw new \gajus\moa\exception\Invalid_Argument_Exception('Property does not conform to column\'s maxiumum character length.');
		}

		// @todo Accept DateTime
		if ((static::$columns[$name]['column_type'] === 'datetime' || static::$columns[$name]['column_type'] === 'timestamp') && !is_int($value)) {
			throw new \gajus\moa\exception\Invalid_Argument_Exception('Property must be an integer UNIX timestamp reprensation.');
		}
		
		//$this->validateInput($data, $value);
		
		if (isset($this->data[$name]) && $this->data[$name] === $value) {
			return false;
		}
		
		$this->data[$name] = $value;
		
		return true;
	}
	
	/**
	 * @param string $name Property name.
	 * @return mixed
	 */
	public function get ($name) {
		if (!isset(static::$columns[$name])) {
			throw new \gajus\moa\exception\Undefined_Property_Exception('Trying to get non-object property.');
		}
		
		return isset($this->data[$name]) ? $this->data[$name] : null;
	}
	
	#final public function flatten () {
	#	return $this->data;
	#}
	
	/**
	 * Save the object to the database. Depending on whether object's primary key is set
	 * this method will either attempt to insert the object to the database or update an
	 * existing entry.
	 *
	 * @return gajus\moa\Mother
	 */
	final public function save () {
		foreach (static::$columns as $name => $column) {
			if ($column['is_nullable'] === 'NO' && is_null($this->data[$name])) {
				unset($this->data[$name]);
			}

			if ($column['is_nullable'] === 'NO' && is_null($column['column_default']) && !isset($this->data[$name])) {
				throw new \gajus\moa\exception\Logic_Exception('Object initialised without the required properties.');
			}
		}

		bump($this->data);
		
		$parameters = $this->data;
		
		unset($parameters[static::PRIMARY_KEY_NAME]);
		
		$placeholders = implode(', ', array_map(function ($name, $value) {
			if ($value !== null && in_array(static::$columns[$name]['column_type'], ['datetime', 'timestamp'])) {
				if (!is_int($value)) {
					throw new \gajus\moa\exception\Invalid_Argument_Exception('Timestamp or datetime must be defined as a unix timestamp. "' . $value . '" (' . gettype($value) . ') is given instead.');
				}
				
				return '`' . $name . '` = FROM_UNIXTIME(:' . $name . ')';
			} else {
				return '`' . $name . '` = :' . $name;
			}
		}, array_keys($parameters), array_values($parameters)));
		
		try {
			if (isset($this->data[static::PRIMARY_KEY_NAME])) {
				$sth = $this->db
					->prepare("UPDATE `" . static::TABLE_NAME . "` SET {$placeholders} WHERE `" . static::PRIMARY_KEY_NAME . "` = :" . static::PRIMARY_KEY_NAME);
			} else {
				$sth = $this->db
					->prepare("INSERT INTO `" . static::TABLE_NAME . "` SET {$placeholders}");
			}
			
			foreach ($this->data as $k => $v) {
				$sth->bindValue($k, $v, static::$columns[$k]['parameter_type']);
			}
			
			$sth->execute();
		} catch (\PDOException $e) {
			if ($e->getCode() === '23000') {
				// http://stackoverflow.com/questions/20077309/in-case-of-integrity-constraint-violation-how-to-tell-the-columns-that-are-caus
				
				preg_match('/(?<=\')[^\']*(?=\'[^\']*$)/', $e->getMessage(), $match);
				
				$indexes = $this->db
					->prepare("SHOW INDEXES FROM `" . static::TABLE_NAME . "` WHERE `Key_name` = :key_name;")
					->execute(['key_name' => $match[0]])
					->fetchAll(\PDO::FETCH_ASSOC);
				
				$columns = array_map(function ($e) { return $e['Column_name']; }, $indexes);
				
				if (count($columns) > 1) {
					throw new \gajus\moa\exception\Logic_Exception('"' . implode(', ', $columns) . '" column combination must have a unique value.', 0, $e);
				} else {
					throw new \gajus\moa\exception\Logic_Exception('"' . $columns[0] . '" column must have a unique value.', 0, $e);
				}
			}
			
			throw $e;
		}
		
		if (isset($this->data[static::PRIMARY_KEY_NAME])) {
			$this->afterUpdate();
		} else {
			$this->data[static::PRIMARY_KEY_NAME] = $this->db->lastInsertId();
		
			$this->afterInsert();
		}
		
		// @todo Synchronise only if table has columns that have on update trigger.
		$this->synchronise();
		
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