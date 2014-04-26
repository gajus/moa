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
        $string = new \Sandbox\Model\String($this->db);
        $string['name'] = 'foo';
        $string->save();

        $this->assertArrayHasKey('id', $string);

        $string->delete();

        $this->assertArrayHasNotKey('id', $string);
    }

    public function testDeleteNotExistingObject () {
        die('test');
        $string = new \Sandbox\Model\String($this->db);
        #$string->assertArrayHasKey($string, 'id');
        #$string->delete();
    }
}