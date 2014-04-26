<?php
namespace Gajus\MOA\Exception;

/**
 * Cannot get property that is not in the object definition.
 * Cannot set property that is not in the object definition.
 * Cannot initialise object without all required properties.
 *
 * @link https://github.com/gajus/moa for the canonical source repository
 * @license https://github.com/gajus/moa/blob/master/LICENSE BSD 3-Clause
 */
class UndefinedPropertyException extends MOAException {}