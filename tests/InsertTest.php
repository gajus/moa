<?php
class InsertTest extends PHPUnit_Framework_TestCase {
    private
        $db;

    public function setUp () {
        $this->db = new \PDO('mysql:dbname=moa', 'travis');
        $this->db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    public function testInsertWithAllDefaultValues () {
        $string = new \sandbox\model\String($this->db);
        $string->save();
    }

    public function testInsert () {
        $string = new \sandbox\model\String($this->db);
        $string['name'] = 'Foo';
        $string->save();

        $this->assertSame('Foo', $string['name']);
    }

    /**
     * @expectedException gajus\moa\exception\Undefined_Property_Exception
     * @expectedExceptionMessage Object initialised without required property: "name".
     */
    public function testInsertWithuotRequiredProperties () {
        $greedy = new \sandbox\model\Greedy($this->db);
        $greedy->save();
    }
}