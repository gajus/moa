<?php
class InflateTest extends PHPUnit_Framework_TestCase {
    private
        $db;

    public function setUp () {
        $this->db = new \PDO('mysql:dbname=moa', 'travis');
        $this->db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    public function testInflateUsingPrimaryKey () {
        $string = new \sandbox\model\String($this->db);

        $string->save();

        $properties = $string->getProperties();

        $string = new \sandbox\model\String($this->db, $string['id']);

        $this->assertSame($properties, $string->getProperties());
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
     * @expectedExcetionMessage Cannot inflate existing object without all properties. Missing "name".
     */
    public function testInflateUsingSomeProperties () {
        $string = new \sandbox\model\String($this->db);
        $string->save();

        $properties = $string->getProperties();

        unset($properties['name']);

        $string = new \sandbox\model\String($this->db, $properties);
    }
}