<?php
namespace Gajus\MOA;

/**
 * @link https://github.com/gajus/moa for the canonical source repository
 * @license https://github.com/gajus/moa/blob/master/LICENSE BSD 3-Clause
 */
abstract class Mother implements \ArrayAccess, \Psr\Log\LoggerAwareInterface {
	protected
		/**
		 * @var PDO
		 */
		$db,
		/**
		 * Used to instantiate new object.
		 * 
		 * @var array
		 */
		$data = [];

	private
		/**
		 * @var int Number of times that object data has been synchronised with the database.
		 */
		$synchronisation_count = 0,
		/**
		 * Used to update only the changed properties.
		 *
		 * @var array
		 */
		$updated_columns = [];

	protected
		/**
		 * @var Psr\Log\LoggerInterface
		 */
		$logger;

	static private
		/**
		 * @var array Used to map MySQL column type to PDO parameter type.
		 */
		$parameter_type_map = [
			'int' => \PDO::PARAM_INT,
			'bigint' => \PDO::PARAM_INT,
			'smallint' => \PDO::PARAM_INT,
			'tinyint' => \PDO::PARAM_INT,
			'mediumint' => \PDO::PARAM_INT
		];
	
	/**
	 * @param PDO $db
	 * @param mixed $data
	 */
	public function __construct (\PDO $db, $data = null) {
		$this->db = $db;
		$this->logger = new \Psr\Log\NullLogger();

		if (is_int($data) || ctype_digit($data)) {
			$this->data[static::PRIMARY_KEY_NAME] = $data;
			$this->synchronise();
		} else if (is_array($data)) {
			if (isset($data[static::PRIMARY_KEY_NAME])) {
				if ($diff = array_keys(array_diff_key(static::$columns, $data))) {
					throw new Exception\UndefinedPropertyException('Cannot inflate existing object without all properties. Missing "' . implode(', ', $diff) . '".');
				}

				$this->data[static::PRIMARY_KEY_NAME] = $data[static::PRIMARY_KEY_NAME];

				unset($data[static::PRIMARY_KEY_NAME]);
			}

			$this->populate($data);

			$this->updated_columns = [];
		} else if (is_null($data)) {
			$this->data = [];
		} else {
			throw new Exception\InvalidArgumentException('Invalid argument type.');
		}
	}

	/**
	 * Get object data as associative array.
	 *
	 * @return array
	 */
	public function getData () {
        return $this->data;
    }

	/**
	 * Use the primary key to update object instance with the data from the database.
	 * 
	 * @return void
	 */
	final private function synchronise () {
		$this->logger->debug('Synchronising object.', ['method' => __METHOD__, 'object' => static::TABLE_NAME]);

		if (!isset($this->data[static::PRIMARY_KEY_NAME])) {
			throw new Exception\Logic_Exception('Primary key is not set.');
		}

		$this->synchronisation_count++;

		$sth = $this->db
			->prepare("SELECT * FROM `" . static::TABLE_NAME . "` WHERE `" . static::PRIMARY_KEY_NAME . "` = ?");
		$sth->execute([$this->data[static::PRIMARY_KEY_NAME]]);
		$this->data = $sth->fetch(\PDO::FETCH_ASSOC);

		if (!$this->data) {
			throw new Exception\RecordNotFoundException('Primary key value does not refer to an existing record.');
		}

		foreach (static::$columns as $name => $column) {
			if (!array_key_exists($name, $this->data)) {
				throw new Exception\LogicException('Model does not reflect table.');
			}

			// @todo Add as a test condition.
			if (in_array($column['data_type'], ['datetime', 'timestamp']) && !is_null($this->data[$name])) {
				$this->data[$name] = strtotime($this->data[$name]);
			}
		}

		$this->updated_columns = [];
	}
	
	/**
	 * Get database handler used to instantiate the object.
	 * 
	 * @return PDO
	 */
	public function getDatabaseHandle () {
		return $this->db;
	}
	
	/**
	 * Shorthand method to pass each array key, value pair to the setter.
	 *
	 * @param array $data
	 * @return gajus\MOA\Mother
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
		$this->logger->debug('Setting property value.', ['method' => __METHOD__, 'object' => static::TABLE_NAME, 'property' => ['name' => $name, 'value' => $value]]);

		if (!is_string($name)) {
			throw new Exception\InvalidArgumentException('Name is not a string.');
		} else if ($name === static::PRIMARY_KEY_NAME) {
			throw new Exception\LogicException('Primary key value cannot be changed.');
		}

		// Trigger an exception easy in case property is not in the object definition.
		$value_before_set = $this->get($name);

		if (is_null($value)) {
			$required_property_names = array_keys($this->getRequiredProperties());

			if (in_array($name, $required_property_names)) {
				throw new Exception\InvalidArgumentException('Value canont be null.');
			}
		} else if (!is_scalar($value)) {
			throw new Exception\InvalidArgumentException('Value is not scalar.');
		}

		switch (static::$columns[$name]['data_type']) {
			case 'datetime':
			case 'timestamp':
				if (is_string($value)) {
					$datetime = \DateTime::createFromFormat('Y-m-d H:i:s', $value);

					if ($datetime) {
						$value = $datetime->getTimestamp();
					}
				}

				if (!is_int($value) && !ctype_digit($value)) {
					throw new Exception\InvalidArgumentException('Propery must be either decimal UNIX timestamp or MySQL datetime string.');
				}
				
				break;

			case 'tinyint':
			case 'smallint':
			case 'mediumint':
			case 'int':
			case 'bigint':
				if (!is_int($value) && !ctype_digit($value)) {
					throw new Exception\InvalidArgumentException('Propery must be a decimal digit.');
				}

				break;

			default:
				if (!is_null(static::$columns[$name]['character_maximum_length']) && static::$columns[$name]['character_maximum_length'] < mb_strlen($value)) {
					throw new Exception\InvalidArgumentException('Property does not conform to the column\'s maxiumum character length limit.');
				}
				break;
		}


		




		

		



		if (is_null($value) && !in_array($name, array_keys())) {
			// @todo Document the purpose of the inverse condition.
		} else {
			
		}

		if (array_key_exists($name, $this->data) && $this->data[$name] === $value) {
			$this->logger->debug('Property value has not changed.', ['method' => __METHOD__, 'object' => static::TABLE_NAME, 'property' => ['name' => $name, 'value' => $value]]);

			return false;
		}

		$this->updated_columns[$name] = $value;

		// @todo Support for extended input validation.
		# $this->validateInput($data, $value);

		$this->data[$name] = $value;
		
		return true;
	}
	
	/**
	 * @param string $name Property name.
	 * @return mixed
	 */
	public function get ($name) {
		if (!isset(static::$columns[$name])) {
			throw new Exception\UndefinedPropertyException('Property is not in the object definition.');
		}
		
		return isset($this->data[$name]) ? $this->data[$name] : null;
	}

	/**
	 * @return array
	 */
	public function getRequiredProperties () {
		$required_properties = [];

		foreach (static::$columns as $name => $column) {
			if (!$column['is_nullable'] && empty($column['column_default']) && $column['extra'] !== 'auto_increment') {
				$required_properties[$name] = $column;
			}
		}

		return $required_properties;
	}
	
	/**
	 * Save the object to the database. Depending on whether object's primary key is set
	 * this method will either attempt to insert the object to the database or update an
	 * existing entry.
	 *
	 * @return gajus\MOA\Mother
	 */
	public function save () {
		$this->logger->debug('Saving object.', ['method' => __METHOD__, 'object' => static::TABLE_NAME]);

		$required_properties = array_keys($this->getRequiredProperties());

		foreach (static::$columns as $name => $column) {
			$property_set = array_key_exists($name, $this->data);

			if ($property_set && is_null($this->data[$name])) {
				unset($this->data[$name]);

				$property_set = false;
			}

			if (!$property_set && in_array($name, $required_properties)) {
				throw new Exception\UndefinedPropertyException('Object initialised without required property: "' . $name . '".');
			}
		}

		$before_data = $this->data; // Used to recover in case of Exception in "after" event.
		
		$is_insert = !isset($this->data[static::PRIMARY_KEY_NAME]);
		$data = $this->data;

		if ($is_insert) {
			$this->logger->debug('Preparing to insert new object.', ['method' => __METHOD__, 'object' => static::TABLE_NAME]);

			$data[static::PRIMARY_KEY_NAME] = null;

		// Update only columns that were changed.
		} else if ($this->updated_columns) {
			$data = $this->updated_columns;
		// Nothing to do.
		} else {
			$data = [];
		}

		$placeholders = [];

		foreach (array_keys($data) as $property_name) {
			if (in_array(static::$columns[$property_name]['data_type'], ['datetime', 'timestamp'])) {
				$placeholders[] = '`' . $property_name . '` = FROM_UNIXTIME(:' . $property_name . ')';
			} else {
				$placeholders[] = '`' . $property_name . '` = :' . $property_name;
			}
		}

		#$this->logger->debug('Preparing to ' . ($is_insert ? 'insert' : 'update') . ' object.', ['method' => __METHOD__, 'object' => static::TABLE_NAME, 'placeholders' => $placeholders]);

		// If update would not affect database.
		
		// @todo What about object an object with all properties having a default value?
		if ($is_insert && !$placeholders) {
			throw new Exception\LogicException('Cannot insert object without any values.');
		}

		if ($placeholders) {
			$placeholders = implode(', ', $placeholders);

			try {
				$this->db->beginTransaction();

				if ($is_insert) {
					$sth = $this->db
						->prepare("INSERT INTO `" . static::TABLE_NAME . "` SET {$placeholders}");
				} else {
					$sth = $this->db
						->prepare("UPDATE `" . static::TABLE_NAME . "` SET {$placeholders} WHERE `" . static::PRIMARY_KEY_NAME . "` = :" . static::PRIMARY_KEY_NAME);

					$data[static::PRIMARY_KEY_NAME] = $this->data[static::PRIMARY_KEY_NAME];
				}
				
				foreach ($data as $k => $v) {
					$sth->bindValue($k, $v, isset(self::$parameter_type_map[static::$columns[$k]['data_type']]) ? self::$parameter_type_map[static::$columns[$k]['data_type']] : \PDO::PARAM_STR);
				}
				
				$sth->execute();

				if ($is_insert) {
					$this->data[static::PRIMARY_KEY_NAME] = $this->db->lastInsertId();
				}
			} catch (\PDOException $e) {
				if ($this->db->inTransaction()) {
					$this->db->rollBack();
				}

				if ($e->getCode() === '23000') {
					throw new Exception\ConstraintViolationException($e->getMessage(), 0, $e);
				}

				throw $e;
			}

			$this->synchronise();
		}
		
		try {
			if ($is_insert) {
				$this->afterInsert();
			} else {		
				$this->afterUpdate();
			}
		} catch (\Exception $e) {
			if ($placeholders) {
				if ($this->db->inTransaction()) {
					$this->db->rollBack();
				}

				$this->data = $before_data;
				$this->synchronisation_count--;
			}

			throw $e;
		}

		if ($placeholders) {
			$this->db->commit();
		}

		return $this;
	}
	
	/**
	 * Delete object from the database. Deleted object retains its data except for the primary key value.
	 * 
	 * @return gajus\MOA\Mother
	 */
	public function delete () {
		if (!isset($this->data[static::PRIMARY_KEY_NAME])) {
			return $this;
		}
		
		$this->db
			->beginTransaction();
		
		$this->db
			->prepare("DELETE FROM `" . static::TABLE_NAME . "` WHERE `" . static::PRIMARY_KEY_NAME . "` = ?")
			->execute([$this->data[static::PRIMARY_KEY_NAME]]);
		
		try {
			$this->afterDelete();
		} catch (\Exception $e) {
			if ($this->db->inTransaction()) {
				$this->db->rollBack();
			}
			
			throw $e;
		}
		
		$this->db->commit();
		
		unset($this->data[static::PRIMARY_KEY_NAME]);

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

	/**
	 * Return raw object data as associative array.
	 *
	 * @return array
	 */
	public function getProperties () {
		return $this->data;
	}

	/**
	 * @return int
	 */
	public function getSynchronisationCount () {
		return $this->synchronisation_count;
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

	/**
     * Sets a logger instance on the object
     *
     * @param LoggerInterface $logger
     * @return null
     */
    public function setLogger (\Psr\Log\LoggerInterface $logger) {
        $this->logger = $logger;
    }
}