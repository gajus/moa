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
        $this->db->exec("TRUNCATE TABLE `greedy_timestamp`");
        $this->db->exec("TRUNCATE TABLE `number`");
        $this->db->exec("TRUNCATE TABLE `string`");
    }

    public function testSetProperty () {
        $string = new \Sandbox\Model\String($this->db);
        $string['name'] = 'Test';

        $this->assertSame('Test', $string['name']);
    }

    public function testSetNullablePropertyToNull () {
        $string = new \Sandbox\Model\Number($this->db);
        $string['tinyint'] = null;

        $this->assertSame(null, $string['tinyint']);
    }

    public function testSetDefaultablePropertyToNull () {
        $greedy_timestamp = new \Sandbox\Model\GreedyTimestamp($this->db);
        $greedy_timestamp['timestamp'] = null;
        $greedy_timestamp->save();
    }

    public function testSetDatetimePropertyUsingDatetime () {
        $datetime = new \Sandbox\Model\Datetime($this->db);
        $datetime['datetime'] = '2014-01-02 05:31:20';
        $datetime->save();

        $this->assertSame(strtotime('2014-01-02 05:31:20'), $datetime['datetime']);
    }

    /**
     * @expectedException Gajus\MOA\Exception\UndefinedPropertyException
     * @expectedExceptionMessage Trying to set non-object property "undefined_property".
     */
    public function testSetUndefinedProperty () {
        $string = new \Sandbox\Model\String($this->db);
        $string['undefined_property'] = 'test';
    }

    /**
     * @expectedException Gajus\MOA\Exception\LogicException
     * @expectedExceptionMessage Primary key value cannot be changed.
     */
    public function testSetPrimaryKeyPropertyOfInflatedObject () {
        $string = new \Sandbox\Model\String($this->db);
        $string['id'] = 1;
    }

    /**
     * @dataProvider setDatetimePropertyProvider
     * @expectedException Gajus\MOA\Exception\InvalidArgumentException
     * @expectedExceptionMessage Propery must be either decimal UNIX timestamp or MySQL datetime string.
     */
    public function testSetDatetimeProperty ($property_name) {
        $datetime = new \Sandbox\Model\Datetime($this->db);
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
     * @expectedException Gajus\MOA\Exception\InvalidArgumentException
     * @expectedExceptionMessage Propery must be a decimal digit.
     */
    public function testSetNumberProperty ($property_name) {
        $number = new \Sandbox\Model\Number($this->db);
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
     * @expectedException Gajus\MOA\Exception\InvalidArgumentException
     * @expectedExceptionMessage Property does not conform to the column's maxiumum character length limit
     */
    public function testSetTooLongPropertyValue () {
        $string = new \Sandbox\Model\String($this->db);
        $string['name'] = str_repeat('a', 101);
    }

    #public function testCustomrValidator () {
        // @todo
    #}
}