<?php
class InflateTest extends PHPUnit_Framework_TestCase {
    private
        $db;

    public function setUp () {
        $this->db = new \PDO('mysql:dbname=moa', 'travis');
        $this->db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->db->exec("TRUNCATE TABLE `datetime`");
        $this->db->exec("TRUNCATE TABLE `duplicate`");
        $this->db->exec("TRUNCATE TABLE `greedy`");
        $this->db->exec("TRUNCATE TABLE `number`");
        $this->db->exec("TRUNCATE TABLE `string`");
    }

    public function testInflateUsingPrimaryKey () {
        $string = new \sandbox\model\String($this->db);

        $string->save();

        $properties = $string->getProperties();

        $string = new \sandbox\model\String($this->db, $string['id']);

        $this->assertSame($properties, $string->getProperties());
    }

    /**
     * @expectedException gajus\moa\exception\Record_Not_Found_Exception
     * @expectedExceptionMessage Primary key value does not refer to an existing record.
     */
    public function testInflateUsingNotExistingPrimaryKey () {
        new \sandbox\model\String($this->db, -1);
    }

    /**
     * @expectedException gajus\moa\exception\Invalid_Argument_Exception
     * @expectedExceptionMessage Invalid argument type.
     */
    public function testInflateUsingInvalidData () {
        new \sandbox\model\String($this->db, 'foobar');
    }

    public function testInflateUsingAllProperties () {
        $string = new \sandbox\model\String($this->db);
        $string->save();

        $properties = $string->getProperties();

        $string = new \sandbox\model\String($this->db, $properties);

        $this->assertSame($properties, $string->getProperties());
    }

    /**
     * @expectedException gajus\moa\exception\Undefined_Property_Exception
     * @expectedExceptionMessage Cannot inflate existing object without all properties. Missing "name".
     */
    public function testInflateUsingSomeProperties () {
        $string = new \sandbox\model\String($this->db);
        $string->save();

        $properties = $string->getProperties();

        unset($properties['name']);

        $string = new \sandbox\model\String($this->db, $properties);
    }
}