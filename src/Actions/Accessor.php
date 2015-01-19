<?php
/**
 * Created by IntelliJ IDEA.
 * User: unknown
 * Date: 06.05.14
 * Time: 2:22
 */

namespace Gajus\MOA\Actions;
use Gajus\MOA\Exception;

trait Accessor {

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

        $error_message = $this->validateSet($name, $value);

        if ($error_message) {
            throw new Exception\ValidationException($error_message);
        }

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

} 