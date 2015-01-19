<?php
/**
 * Created by IntelliJ IDEA.
 * User: unknown
 * Date: 06.05.14
 * Time: 2:19
 */

namespace Gajus\MOA\Actions;

use Gajus\MOA\Exception;

trait Save {

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

        $error_message = $this->validateSave();

        if ($error_message) {
            throw new Exception\ValidationException($error_message);
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

} 