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
        $this->db->exec("TRUNCATE TABLE `number`");
        $this->db->exec("TRUNCATE TABLE `string`");
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
        
        $this->assertSame($original_id, $string['id']);

        // Inflate
        $string = new \sandbox\model\String($this->db, $string['id']);

        $this->assertSame($original_id, $string['id']);
        $this->assertSame('bar', $string['name']);
    }

    public function testDoNotUpdateIfValueHasNotChanged () {
        $string = new \sandbox\model\String($this->db);
        $string['name'] = 'foo';
        $string->save();

        $synchronisation_count = $string->getSynchronisationCount();

        $string->save();

        $this->assertSame($synchronisation_count, $string->getSynchronisationCount());
    }

    /**
     * @expectedException gajus\moa\exception\Logic_Exception
     * @expectedExceptionMessage "name" column must have a unique value.
     */
    public function testUpdateUsingDuplicateValue () {
        $string = new \sandbox\model\Duplicate($this->db);
        $string['name'] = 'Foo';
        $string->save();

        $string = new \sandbox\model\Duplicate($this->db);
        $string['name'] = 'bar';
        $string->save();
        $string['name'] = 'Foo';
        $string->save();
    }

    /**
     * @expectedException gajus\moa\exception\Logic_Exception
     * @expectedExceptionMessage "foo, bar" column combination must have a unique value.
     */
    public function testUpdateDuplicateValueCombination () {
        $string = new \sandbox\model\Duplicate($this->db);
        $string['foo'] = 'foo';
        $string['bar'] = 'bar';
        $string->save();

        $string = new \sandbox\model\Duplicate($this->db);
        $string['foo'] = 'x';
        $string['bar'] = 'x';
        $string->save();
        $string['foo'] = 'foo';
        $string['bar'] = 'bar';
        $string->save();
    }
}