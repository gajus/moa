<?php
class DeleteTest extends PHPUnit_Framework_TestCase {
    private
        $db;

    public function setUp () {
        if (!$this->db) {
            $this->db = new \PDO('mysql:dbname=moa', 'travis');
            $this->db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        }

        $this->db->exec("TRUNCATE TABLE `datetime`");
        $this->db->exec("TRUNCATE TABLE `duplicate`");
        $this->db->exec("TRUNCATE TABLE `greedy`");
        $this->db->exec("TRUNCATE TABLE `number`");
        $this->db->exec("TRUNCATE TABLE `string`");        
    }

    public function testDeleteExistingObject () {
        $foo = new \Sandbox\Model\String($this->db);
        $foo['name'] = 'foo';
        $foo->save();

        $this->assertArrayHasKey('id', $foo);

        $foo->delete();

        $this->assertArrayNotHasKey('id', $foo);
    }

    public function testDeleteNotExistingObject () {
        $foo = new \Sandbox\Model\String($this->db);
        
        $this->assertArrayNotHasKey('id', $foo);
        
        $foo->delete();

        $this->assertArrayNotHasKey('id', $foo);
    }
}