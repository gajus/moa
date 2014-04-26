<?php
class EventTest extends PHPUnit_Framework_TestCase {
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
     * @expectedException RuntimeException
     * @expectedExceptionCode 1
     */
    public function testAfterInsert () {
        $foo = new \Sandbox\Model\String($this->db);
        $foo['name'] = 'throw_after_insert';
        $foo->save();
    }

    public function testAfterInsertRecover () {
        $properties = ['name' => 'throw_after_insert'];

        $foo = new \Sandbox\Model\String($this->db);
        $foo->populate($properties);
        
        try {
            $foo->save();
        } catch (\RuntimeException $e) {
            $this->assertSame($properties, $foo->getData());
        }
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionCode 2
     */
    public function testAfterUpdate () {
        $foo = new \Sandbox\Model\String($this->db);
        $foo['name'] = 'throw_after_update';
        $foo->save();
        $foo->save();
    }

    public function testAfterUpdateRecover () {
        $foo = new \Sandbox\Model\String($this->db);
        $foo['name'] = 'throw_after_update';
        $foo->save();

        $properties = $foo->getData();
        
        try {
            $foo->save();
        } catch (\RuntimeException $e) {
            $this->assertSame($properties, $foo->getData());
        }
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionCode 3
     */
    public function testAfterDelete () {
        $foo = new \Sandbox\Model\String($this->db);
        $foo['name'] = 'throw_after_delete';
        $foo->save();
        $foo->delete();
    }

    public function testAfterDeleteRecover () {
        $foo = new \Sandbox\Model\String($this->db);
        $foo['name'] = 'throw_after_delete';
        $foo->save();

        $properties = $foo->getData();
        
        try {
            $foo->delete();
        } catch (\RuntimeException $e) {
            $this->assertSame($properties, $foo->getData());
        }
    }
}