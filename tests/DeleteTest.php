<?php
class DeleteTest extends PHPUnit_Framework_TestCase {
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

    public function testDelete () {
        $string = new \sandbox\model\String($this->db);
        $string['name'] = 'foo';
        $string->save();
        $string->delete();

        $this->assertFalse(isset($string['id']));
    }

    /**
     * @expectedException gajus\moa\exception\Logic_Exception
     * @expectedExceptionMessage Cannot delete not initialised object.
     */
    public function testDeleteNotExistingObject () {
        $string = new \sandbox\model\String($this->db);
        $string->delete();
    }
}