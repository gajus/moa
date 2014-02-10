<?php
namespace Gajus\MOA\Exception;

/**
 * Thrown when trying to access property that does not belong to the model.
 * Thrown when trying to set property that does not belong to the model.
 * Thrown when trying to save an object without all the required properties.
 *
 * @link https://github.com/gajus/moa for the canonical source repository
 * @license https://github.com/gajus/moa/blob/master/LICENSE BSD 3-Clause
 */
class UndefinedPropertyException extends MOAException {}