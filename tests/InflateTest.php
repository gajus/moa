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
        $string = new \Sandbox\Model\String($this->db);

        $string->save();

        $properties = $string->getProperties();

        $string = new \Sandbox\Model\String($this->db, $string['id']);

        $this->assertSame($properties, $string->getProperties());
    }

    /**
     * @expectedException Gajus\MOA\Exception\RecordNotFoundException
     * @expectedExceptionMessage Primary key value does not refer to an existing record.
     */
    public function testInflateUsingNotExistingPrimaryKey () {
        new \Sandbox\Model\String($this->db, -1);
    }

    /**
     * @expectedException Gajus\MOA\Exception\InvalidArgumentException
     * @expectedExceptionMessage Invalid argument type.
     */
    public function testInflateUsingInvalidData () {
        new \Sandbox\Model\String($this->db, 'foobar');
    }

    public function testInflateUsingAllProperties () {
        $string = new \Sandbox\Model\String($this->db);
        $string->save();

        $properties = $string->getProperties();

        $string = new \Sandbox\Model\String($this->db, $properties);

        $this->assertSame($properties, $string->getProperties());
    }

    /**
     * @expectedException Gajus\MOA\Exception\UndefinedPropertyException
     * @expectedExceptionMessage Cannot inflate existing object without all properties. Missing "name".
     */
    public function testInflateUsingSomeProperties () {
        $string = new \Sandbox\Model\String($this->db);
        $string->save();

        $properties = $string->getProperties();

        unset($properties['name']);

        $string = new \Sandbox\Model\String($this->db, $properties);
    }
}