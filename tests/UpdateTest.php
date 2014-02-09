<?php
class UpdateTest extends PHPUnit_Framework_TestCase {
    private
        $db;

    public function setUp () {
        $this->db = new \PDO('mysql:dbname=moa', 'travis');
        $this->db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    public function testUpdateProperty () {
        // Insert
        $string = new \sandbox\model\String($this->db);
        $string['name'] = 'foo';
        $string->save();

        $original_id = $string['id'];

        // Inflate, update
        $string = new \sandbox\model\String($this->db, $string['id']);
        $string['name'] = 'bar';
        $string->save();

        // Inflate
        $string = new \sandbox\model\String($this->db, $string['id']);

        $this->assertSame($original_id, $string['id']);
        $this->assertSame('bar', $string['name']);
    }
}