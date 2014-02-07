<?php
namespace gajus\moa\exception;

/**
 * Thrown when record is not found using the primary key. This exception should not
 * be thrown when attempted to return multiple records using an arbitrary condition.
 * 
 * @link https://github.com/gajus/moa for the canonical source repository
 * @license https://github.com/gajus/moa/blob/master/LICENSE BSD 3-Clause
 */
class Record_Not_Found_Exception extends Moa_Exception {}