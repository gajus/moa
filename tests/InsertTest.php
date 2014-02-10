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
        $this->db->exec("TRUNCATE TABLE `greedy_timestamp`");
        $this->db->exec("TRUNCATE TABLE `number`");
        $this->db->exec("TRUNCATE TABLE `string`");
    }

    public function testInsertWithAllDefaultValues () {
        $string = new \Sandbox\Model\String($this->db);
        $string->save();
    }

    public function testNotNullableButDefault () {
        $greedy_timestamp = new \Sandbox\Model\GreedyTimestamp($this->db);
        $greedy_timestamp->save();
    }

    public function testInsert () {
        $string = new \Sandbox\Model\String($this->db);
        $string['name'] = 'Foo';
        $string->save();

        $this->assertSame('Foo', $string['name']);
    }

    /**
     * @expectedException Gajus\MOA\Exception\UndefinedPropertyException
     * @expectedExceptionMessage Object initialised without required property: "name".
     */
    public function testInsertWithuotRequiredProperties () {
        $greedy = new \Sandbox\Model\Greedy($this->db);
        $greedy->save();
    }

    /**
     * @expectedException Gajus\MOA\Exception\LogicException
     * @expectedExceptionMessage "name" column must have a unique value.
     */
    public function testInsertDuplicateValue () {
        $string = new \Sandbox\Model\Duplicate($this->db);
        $string['name'] = 'Foo';
        $string->save();

        $string = new \Sandbox\Model\Duplicate($this->db);
        $string['name'] = 'Foo';
        $string->save();
    }

    /**
     * @expectedException Gajus\MOA\Exception\LogicException
     * @expectedExceptionMessage "foo, bar" column combination must have a unique value.
     */
    public function testInsertDuplicateValueCombination () {
        $string = new \Sandbox\Model\Duplicate($this->db);
        $string['foo'] = 'foo';
        $string['bar'] = 'bar';
        $string->save();

        $string = new \Sandbox\Model\Duplicate($this->db);
        $string['foo'] = 'foo';
        $string['bar'] = 'bar';
        $string->save();
    }
}