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
		$updated_properties = [];

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

		// If $data is an integer, then it is assumed by the primary key
		// of an existing record in the database.
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

			$this->updated_properties = [];
		} else if (is_null($data)) {
			$this->data = [];
		} else {
			throw new Exception\InvalidArgumentException('Invalid argument type.');
		}
	}

	/**
	 * Get object data in an associative array.
	 *
	 * @return array
	 */
	public function getData () {
        return $this->data;
    }

	/**
	 * Use the primary key to update object instance with the data from the database.
	 * This will overwrite the existing state of the object.
	 * 
	 * @return void
	 */
	private function synchronise () {
		$this->logger->debug('Synchronising object.', ['method' => __METHOD__, 'object' => static::TABLE_NAME]);

		if (!isset($this->data[static::PRIMARY_KEY_NAME])) {
			throw new Exception\LogicException('Primary key is not set.');
		}

		if ($this->updated_properties) {
			die(var_dump( $this->updated_properties ));

			throw new Exception\LogicException('Obeject state has not been saved prior synchronisation.');
		}

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

		$this->synchronisation_count++;
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
	 * Set object property.
	 * 
	 * @param string $name Property name.
	 * @param mixed $value
	 * @return boolean Returns true if object state has been affected.
	 */
	public function set ($name, $value = null) {
		$this->logger->debug('Setting property value.', ['method' => __METHOD__, 'object' => static::TABLE_NAME, 'property' => ['name' => $name, 'value' => $value]]);

		if (!is_string($name)) {
			throw new Exception\InvalidArgumentException('Name is not a string.');
		} else if ($name === static::PRIMARY_KEY_NAME) {
			throw new Exception\LogicException('Primary key value cannot be changed.');
		}

		if (!isset(static::$columns[$name])) {
			throw new Exception\UndefinedPropertyException('Cannot set property that is not in the object definition.');
		}

		// This value is used to determine whether object state has been affected.
		$value_before_set = $this->get($name);

		if (is_null($value)) {
			$required_property_names = array_keys($this->getRequiredProperties());

			if (in_array($name, $required_property_names)) {
				throw new Exception\InvalidArgumentException('Value canont be null.');
			}
		} else if (!is_scalar($value)) {
			throw new Exception\InvalidArgumentException('Value is not scalar.');
		}

		// If property is nullable and value is null, then none of the validation or normalisation is relevant.
		if (!static::$columns[$name]['is_nullable'] || !is_null($value)) {
			switch (static::$columns[$name]['data_type']) {
				case 'datetime':
				case 'timestamp':
					if (is_int($value) || ctype_digit($value)) {
						$value = (int) $value;
					} else if (is_string($value)) {
						// MySQL timestamp
						$datetime = \DateTime::createFromFormat('Y-m-d H:i:s', $value);

						if (!$datetime) {
							throw new Exception\InvalidArgumentException('Invalid datetime format.');
						}
						$value = $datetime->getTimestamp();
					} else {
						throw new Exception\InvalidArgumentException('Datetime must be either decimal UNIX timestamp or MySQL datetime string.');
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

			// @todo Use the validate method.

			// If an existing object property is a string, then new value will be casted to string
			// regardless of its existing type.
			if (is_string($value_before_set)) {
				$value = (string) $value;
			}
		}

		if ($value === $value_before_set) {
			$this->logger->debug('Property value has not changed.', ['method' => __METHOD__, 'object' => static::TABLE_NAME, 'property' => ['name' => $name, 'value' => $value]]);

			return false;
		}

		$this->updated_properties[$name] = $value;

		// @todo Support for extended input validation.
		# $this->validateInput($data, $value);

		$this->data[$name] = $value;
		
		return true;
	}
	
	/**
	 * Get object property.
	 *
	 * @param string $name Property name.
	 * @return mixed
	 */
	public function get ($name) {
		if (!isset(static::$columns[$name])) {
			throw new Exception\UndefinedPropertyException('Cannot get property that is not in the object definition.');
		}
		
		return isset($this->data[$name]) ? $this->data[$name] : null;
	}

	/**
	 * Properties that are not nullable and do not have a default value.
	 * 
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
	 * @return $this
	 */
	public function save () {
		$this->logger->debug('Saving object.', ['method' => __METHOD__, 'object' => static::TABLE_NAME]);

		// Make sure that all required object properties have a value.
		$required_property_names = array_keys($this->getRequiredProperties());

		foreach ($required_property_names as $required_property_name) {
			if (!isset($this->data[$required_property_name])) {
				throw new Exception\LogicException('Cannot initialise object without all required properties.');
			}
		}

		// Object state backup to recover in case of an Exception in the "after" event.
		$data_before_save = $this->data;
		
		$is_insert = false;

		if (!isset($this->data[static::PRIMARY_KEY_NAME])) {
			$this->logger->debug('Preparing to insert new object.', ['method' => __METHOD__, 'object' => static::TABLE_NAME]);

			$data = $this->data;
			$data[static::PRIMARY_KEY_NAME] = null;

			$is_insert = true;
		} else {
			if ($this->updated_properties) {
				// Update only columns that were changed.
				$data = $this->updated_properties;
			} else {
				// @see https://github.com/gajus/moa/issues/1
				$this->afterUpdate();

				return $this;
			}
		}

		// Prepare placeholders for the PDOStatement.
		// "datetime" and "timestamp" object properties are in their integer (UNIX timestamp) representation.
		$placeholders = [];

		foreach (array_keys($data) as $property_name) {
			if (in_array(static::$columns[$property_name]['data_type'], ['datetime', 'timestamp'])) {
				$placeholders[] = '`' . $property_name . '` = FROM_UNIXTIME(:' . $property_name . ')';
			} else {
				$placeholders[] = '`' . $property_name . '` = :' . $property_name;
			}
		}

		$placeholders = implode(', ', $placeholders);

		try {
			$this->db->beginTransaction();

			if ($is_insert) {
				$sth = $this->db
					->prepare("INSERT INTO `" . static::TABLE_NAME . "` SET " . $placeholders);
			} else {
				$sth = $this->db
					->prepare("UPDATE `" . static::TABLE_NAME . "` SET " . $placeholders . " WHERE `" . static::PRIMARY_KEY_NAME . "` = :" . static::PRIMARY_KEY_NAME);

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

		$this->updated_properties = [];

		// Get fresh object state from the database.
		// This carries across properties that have default value in MySQL schema definition.
		$this->synchronise();
		
		try {
			if ($is_insert) {
				$this->afterInsert();
			} else {		
				$this->afterUpdate();
			}
		} catch (\Exception $e) {
			// An exception thrown in "afterInsert" or "afterUpdate" method will revert the change.
			// "afterInsert" and "afterUpdate" method must not close the save transaction.

			if (!$this->db->inTransaction()) {
				throw new Exception\LogicException('Transaction was commited before the time.');
			}

			$this->db->rollBack();

			$this->data = $data_before_save;
			$this->synchronisation_count--;

			throw $e;
		}

		if (!$this->db->inTransaction()) {
			throw new Exception\LogicException('Transaction was commited before the time.');
		}

		$this->db->commit();
		
		return $this;
	}
	
	/**
	 * Delete object from the database. Deleted object retains its data except for the primary key value.
	 * 
	 * @return $this
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
	 * Method called at the time of setting property value.
	 * @todo How to deal with full object validation and partial object validation.
	 * @param array $data
	 */
	protected function validate (array $data) {}
	
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
	 * Synchronisation count is used for unit testing only.
	 * This method must not be used in the application code.
	 *
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