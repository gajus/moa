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
        $foo = new \Sandbox\Model\String($this->db);
        $foo->save();
    }

    public function testNotNullableButDefault () {
        $foo = new \Sandbox\Model\GreedyTimestamp($this->db);
        $foo->save();
    }

    public function testInsert () {
        $foo = new \Sandbox\Model\String($this->db);
        $foo['name'] = 'Foo';
        $foo->save();

        $this->assertSame('Foo', $foo['name']);
    }

    /**
     * @expectedException Gajus\MOA\Exception\LogicException
     * @expectedExceptionMessage Cannot initialise object without all required properties.
     */
    public function testInsertWithuotRequiredProperties () {
        $foo = new \Sandbox\Model\Greedy($this->db);
        $foo->save();
    }
}