<?php
class SetTest extends PHPUnit_Framework_TestCase {
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

    public function testSetProperty () {
        $string = new \sandbox\model\String($this->db);
        $string['name'] = 'Test';

        $this->assertSame('Test', $string['name']);
    }

    /**
     * @expectedException gajus\moa\exception\Undefined_Property_Exception
     * @expectedExceptionMessage Trying to set non-object property "undefined_property".
     */
    public function testSetUndefinedProperty () {
        $string = new \sandbox\model\String($this->db);
        $string['undefined_property'] = 'test';
    }

    /**
     * @expectedException gajus\moa\exception\Logic_Exception
     * @expectedExceptionMessage Primary key value cannot be changed.
     */
    public function testSetPrimaryKeyPropertyOfInflatedObject () {
        $string = new \sandbox\model\String($this->db);
        $string['id'] = 1;
    }

    /**
     * @dataProvider setDatetimePropertyProvider
     * @expectedException gajus\moa\exception\Invalid_Argument_Exception
     * @expectedExceptionMessage Propery must be a decimal digit.
     */
    public function testSetDatetimeProperty ($property_name) {
        $datetime = new \sandbox\model\Datetime($this->db);
        $datetime[$property_name] = 'test';
    }

    public function setDatetimePropertyProvider () {
        return [
            ['timestamp'],
            ['datetime']
        ];
    }

    /**
     * @dataProvider setNumberPropertyProvider
     * @expectedException gajus\moa\exception\Invalid_Argument_Exception
     * @expectedExceptionMessage Propery must be a decimal digit.
     */
    public function testSetNumberProperty ($property_name) {
        $number = new \sandbox\model\Number($this->db);
        $number[$property_name] = 'foo';
    }

    public function setNumberPropertyProvider () {
        return [
            ['tinyint'],
            ['unsigned_tinyint'],
            ['smallint'],
            ['unsigned_smallint'],
            ['int'],
            ['unsigned_int'],
            ['bigint'],
            ['unsigned_bigint']
        ];
    }

    /**
     * @expectedException gajus\moa\exception\Invalid_Argument_Exception
     * @expectedExceptionMessage Property does not conform to the column's maxiumum character length limit
     */
    public function testSetTooLongPropertyValue () {
        $string = new \sandbox\model\String($this->db);
        $string['name'] = str_repeat('a', 101);
    }

    #public function testCustomrValidator () {
        // @todo
    #}
}