<?php
class InsertTest extends PHPUnit_Framework_TestCase {
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

    /**
     * @expectedException gajus\moa\exception\Logic_Exception
     * @expectedExceptionMessage "name" column must have a unique value.
     */
    public function testInsertDuplicateValue () {
        $string = new \sandbox\model\Duplicate($this->db);
        $string['name'] = 'Foo';
        $string->save();

        $string = new \sandbox\model\Duplicate($this->db);
        $string['name'] = 'Foo';
        $string->save();
    }

    /**
     * @expectedException gajus\moa\exception\Logic_Exception
     * @expectedExceptionMessage "foo, bar" column combination must have a unique value.
     */
    public function testInsertDuplicateValueCombination () {
        $string = new \sandbox\model\Duplicate($this->db);
        $string['foo'] = 'foo';
        $string['bar'] = 'bar';
        $string->save();

        $string = new \sandbox\model\Duplicate($this->db);
        $string['foo'] = 'foo';
        $string['bar'] = 'bar';
        $string->save();
    }
}