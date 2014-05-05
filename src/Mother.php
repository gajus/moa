<?php
namespace Gajus\MOA;

use Gajus\MOA\Actions;

/**
 * @link https://github.com/gajus/moa for the canonical source repository
 * @license https://github.com/gajus/moa/blob/master/LICENSE BSD 3-Clause
 */
abstract class Mother implements \ArrayAccess, \Psr\Log\LoggerAwareInterface {

    //add action methods
    use Actions\Delete , Actions\Save, Actions\Accessor;

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
     * @param integer $id
     */
    public function __construct (\PDO $db, $id = null) {
        $this->db = $db;
        $this->logger = new \Psr\Log\NullLogger();

        // If $data is an integer, then it is assumed by the primary key
        // of an existing record in the database.
        if (is_int($id) || ctype_digit($id)) {
            $this->data[static::PRIMARY_KEY_NAME] = $id;
            $this->synchronise();
        } else if (is_null($id)) {
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
            throw new Exception\LogicException('Object state has not been saved prior synchronisation.');
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
     * Get database handle used to instantiate the object.
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
     * @return Gajus\MOA\Mother
     */
    public function populate (array $data) {
        foreach ($data as $name => $value) {
            $this->set($name, $value);
        }

        return $this;
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
     * Triggered when an attempt is made to change object property.
     * Returning an error message will discard the transaction and throw Gajus\MOA\Exception\ValidationException exception.
     * 
     * @param string $name
     * @param mixed $value
     * @return null|string
     */
    protected function validateSet ($name, $value) {}

    /**
     * Triggered when an attempt is made to save object state.
     * Returning an error message will discard the transaction and throw Gajus\MOA\Exception\ValidationException exception.
     * 
     * @return null|mixed
     */
    protected function validateSave () {}
    
    /**
     * Triggered after INSERT query but before the transaction is committed.
     * 
     * @return void
     */
    protected function afterInsert () {}

    /**
     * Triggered after UPDATE query but before the transaction is committed.
     * 
     * @return void
     */
    protected function afterUpdate () {}

    /**
     * Triggered after DELETE query but before the transaction is committed.
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
