<?php
namespace Gajus\MOA\Exception;

/**
 * Thrown when record is not found using the primary key. This exception should not
 * be thrown when an attempt is made to return multiple records using an arbitrary condition.
 * 
 * @link https://github.com/gajus/moa for the canonical source repository
 * @license https://github.com/gajus/moa/blob/master/LICENSE BSD 3-Clause
 */
class RecordNotFoundException extends MOAException {
    
}