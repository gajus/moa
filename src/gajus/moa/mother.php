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

	static private
		$parameter_type_map = [
			'int' => \PDO::PARAM_INT,
			'bigint' => \PDO::PARAM_INT,
			'smallint' => \PDO::PARAM_INT,
			'tinyint' => \PDO::PARAM_INT,
			'mediumint' => \PDO::PARAM_INT
		];
	
	/**
	 *
	 *
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
	}

	#static function blowFromId
	#static function blowFromData

	/**
	 * Use the primary key to update object instance with the data from the database.
	 * 
	 * @return void
	 */
	final private function synchronise () {
		if (!isset($this->data[static::PRIMARY_KEY_NAME])) {
			throw new \gajus\moa\exception\Logic_Exception('Primary key is not set.');
		}

		$sth = $this->db
			->prepare("SELECT * FROM `" . static::TABLE_NAME . "` WHERE `" . static::PRIMARY_KEY_NAME . "` = ?");
		$sth->execute([$this->data[static::PRIMARY_KEY_NAME]]);
		$this->data = $sth->fetch(\PDO::FETCH_ASSOC);

		if (!$this->data) {
			throw new \gajus\moa\exception\Record_Not_Found_Exception('Primary key value does not refer to an existing record.');
		}

		foreach (static::$columns as $name => $column) {
			if (in_array($column['data_type'], ['datetime', 'timestamp'])) {
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
		return $this->get($offset);
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
			throw new \gajus\moa\exception\Logic_Exception('Cannot set primary key value.');
		} else if (!isset(static::$columns[$name])) {
			throw new \gajus\moa\exception\Undefined_Property_Exception('Trying to set non-object property "' . $name . '".');
		}

		switch (static::$columns[$name]['data_type']) {
			case 'datetime':
			case 'timestamp':
				// @todo Accept DateTime
				if (!is_int($value) && !ctype_digit($value)) {
					throw new \gajus\moa\exception\Invalid_Argument_Exception('Propery must be a decimal digit.');
				}
				
				break;

			case 'tinyint':
			case 'smallint':
			case 'mediumint':
			case 'int':
			case 'bigint':
				if (!is_int($value) && !ctype_digit($value)) {
					throw new \gajus\moa\exception\Invalid_Argument_Exception('Propery must be a decimal digit.');
				}

				// @todo check range

				break;

			default:
				if (!is_null(static::$columns[$name]['character_maximum_length']) && static::$columns[$name]['character_maximum_length'] < mb_strlen($value)) {
					throw new \gajus\moa\exception\Invalid_Argument_Exception('Property does not conform to the column\'s maxiumum character length limit.');
				}
				break;
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
			throw new \gajus\moa\exception\Undefined_Property_Exception('Trying to get non-object property "' . $name . '".');
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
			$property_set = array_key_exists($name, $this->data);

			if ($property_set && is_null($this->data[$name])) {
				unset($this->data[$name]);

				$property_set = false;
			}

			if (!$column['is_nullable'] && !$property_set && $column['extra'] !== 'auto_increment') {
				throw new \gajus\moa\exception\Undefined_Property_Exception('Object initialised without required property: "' . $name . '".');
			}
		}

		// If updating, then primary key must not be part of the placeholder string.
		// If inserting, then primary key must be part of the placeholder string and must be equal to null.
		// The latter is required in case all other properties are nullable.
		$id = null;

		if (isset($this->data[static::PRIMARY_KEY_NAME])) {
			$id = $this->data[static::PRIMARY_KEY_NAME];

			unset($this->data[static::PRIMARY_KEY_NAME]);
		} else {
			$this->data[static::PRIMARY_KEY_NAME] = null;
		}

		$placeholders = [];

		foreach (array_keys($this->data) as $property_name) {
			if (in_array(static::$columns[$property_name]['data_type'], ['datetime', 'timestamp'])) {
				$placeholders[] = '`' . $property_name . '` = FROM_UNIXTIME(:' . $property_name . ')';
			} else {
				$placeholders[] = '`' . $property_name . '` = :' . $property_name;
			}
		}

		$placeholders = implode(', ', $placeholders);

		#$this->db->beginTransaction();

		try {
			if ($id) {
				$sth = $this->db
					->prepare("UPDATE `" . static::TABLE_NAME . "` SET {$placeholders} WHERE `" . static::PRIMARY_KEY_NAME . "` = :" . static::PRIMARY_KEY_NAME);
			} else {
				$sth = $this->db
					->prepare("INSERT INTO `" . static::TABLE_NAME . "` SET {$placeholders}");

				$sth->bindValue(static::PRIMARY_KEY_NAME, null, \PDO::PARAM_NULL);
			}
			
			foreach ($this->data as $k => $v) {
				$sth->bindValue($k, $v, isset(self::$parameter_type_map[static::$columns[$k]['data_type']]) ? self::$parameter_type_map[static::$columns[$k]['data_type']] : \PDO::PARAM_STR);
			}
			
			$sth->execute();

			if ($id) {
				$this->data[static::PRIMARY_KEY_NAME] = $id;
			} else {
				$this->data[static::PRIMARY_KEY_NAME] = $this->db->lastInsertId();
			}
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

		// @todo Synchronise only if table has columns that have on update trigger.
		$this->synchronise();
		
		if (isset($this->data[static::PRIMARY_KEY_NAME])) {
			$this->afterUpdate();
		} else {		
			$this->afterInsert();
		}
		
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