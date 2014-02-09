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
        $this->db->exec("TRUNCATE TABLE `number`");
        $this->db->exec("TRUNCATE TABLE `string`");
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionCode 1
     */
    public function testAfterInsert () {
        $string = new \sandbox\model\String($this->db);
        $string['name'] = 'throw_after_insert';
        $string->save();
    }

    public function testAfterInsertRecover () {
        $properties = ['name' => 'throw_after_insert'];

        $string = new \sandbox\model\String($this->db);
        $string->populate($properties);
        
        try {
            $string->save();
        } catch (\RuntimeException $e) {
            $this->assertSame($properties, $string->getProperties());
        }
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionCode 2
     */
    public function testAfterUpdate () {
        $string = new \sandbox\model\String($this->db);
        $string['name'] = 'throw_after_update';
        $string->save();
        $string->save();
    }

    public function testAfterUpdateRecover () {
        $string = new \sandbox\model\String($this->db);
        $string['name'] = 'throw_after_update';
        $string->save();

        $properties = $string->getProperties();
        
        try {
            $string->save();
        } catch (\RuntimeException $e) {
            $this->assertSame($properties, $string->getProperties());
        }
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionCode 3
     */
    public function testAfterDelete () {
        $string = new \sandbox\model\String($this->db);
        $string['name'] = 'throw_after_delete';
        $string->save();
        $string->delete();
    }

    public function testAfterDeleteRecover () {
        $string = new \sandbox\model\String($this->db);
        $string['name'] = 'throw_after_delete';
        $string->save();

        $properties = $string->getProperties();
        
        try {
            $string->delete();
        } catch (\RuntimeException $e) {
            $this->assertSame($properties, $string->getProperties());
        }
    }
}