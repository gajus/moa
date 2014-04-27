<?php
class ValidationTest extends PHPUnit_Framework_TestCase {
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

    /**
     * @expectedException Gajus\MOA\Exception\ValidationException
     * @expectedExceptionMessage set_does_not_pass
     */
    public function testSetValidationDoesNotPass () {
        $foo = new \Sandbox\Model\String($this->db);
        $foo['name'] = 'set_do_not_pass';
    }

    /**
     * @expectedException Gajus\MOA\Exception\ValidationException
     * @expectedExceptionMessage save_does_not_pass
     */
    public function testSaveValidationDoesNotPass () {
        $foo = new \Sandbox\Model\String($this->db);
        $foo['name'] = 'save_do_not_pass';
        $foo->save();
    }
}