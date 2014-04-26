<?php
class UpdateTest extends PHPUnit_Framework_TestCase {
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

    public function testUpdateProperty () {
        // Insert
        $foo = new \Sandbox\Model\String($this->db);
        $foo['name'] = 'foo';
        $foo->save();

        $original_id = $foo['id'];

        // Inflate, update
        $foo = new \Sandbox\Model\String($this->db, $foo['id']);
        $foo['name'] = 'bar';
        $foo->save();
        
        $this->assertSame($original_id, $foo['id']);

        // Inflate
        $foo = new \Sandbox\Model\String($this->db, $foo['id']);

        $this->assertSame($original_id, $foo['id']);
        $this->assertSame('bar', $foo['name']);
    }

    public function testDoNotUpdateIfValueHasNotChanged () {
        $foo = new \Sandbox\Model\String($this->db);
        $foo['name'] = 'foo';
        $foo->save();

        $synchronisation_count = $foo->getSynchronisationCount();

        $foo->save();

        $this->assertSame($synchronisation_count, $foo->getSynchronisationCount());
    }
}