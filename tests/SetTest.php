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
        $foo = new \Sandbox\Model\String($this->db);
        $foo['name'] = 'Test';

        $this->assertSame('Test', $foo['name']);
    }

    public function testSetNullablePropertyToNull () {
        $foo = new \Sandbox\Model\Number($this->db);
        $foo['tinyint'] = null;

        $this->assertSame(null, $foo['tinyint']);
    }

    /**
     * In this case, unset must be used insteat of setting value to null.
     * 
     * @expectedException Gajus\MOA\Exception\InvalidArgumentException
     * @expectedExceptionMessage Datetime must be either decimal UNIX timestamp or MySQL datetime string.
     */
    public function testSetDefaultablePropertyToNull () {
        $foo = new \Sandbox\Model\GreedyTimestamp($this->db);
        $foo['timestamp'] = null;
        $foo->save();
    }

    public function testSetDatetimePropertyUsingDatetime () {
        $foo = new \Sandbox\Model\Datetime($this->db);
        $foo['datetime'] = '2014-01-02 05:31:20';
        $foo->save();

        $this->assertSame(strtotime('2014-01-02 05:31:20'), $foo['datetime']);
    }

    /**
     * @expectedException Gajus\MOA\Exception\UndefinedPropertyException
     * @expectedExceptionMessage Cannot set property that is not in the object definition.
     */
    public function testSetUndefinedProperty () {
        $foo = new \Sandbox\Model\String($this->db);
        $foo['undefined_property'] = 'test';
    }

    /**
     * @expectedException Gajus\MOA\Exception\LogicException
     * @expectedExceptionMessage Primary key value cannot be changed.
     */
    public function testSetPrimaryKeyPropertyOfInflatedObject () {
        $foo = new \Sandbox\Model\String($this->db);
        $foo['id'] = 1;
    }

    /**
     * @dataProvider setDatetimePropertyProvider
     * @expectedException Gajus\MOA\Exception\InvalidArgumentException
     * @expectedExceptionMessage Invalid datetime format.
     */
    public function testSetDatetimeProperty ($property_name) {
        $foo = new \Sandbox\Model\Datetime($this->db);
        $foo[$property_name] = 'test';
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
        $foo = new \Sandbox\Model\Number($this->db);
        $foo[$property_name] = 'foo';
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
        $foo = new \Sandbox\Model\String($this->db);
        $foo['name'] = str_repeat('a', 101);
    }

    #public function testCustomrValidator () {
        // @todo
    #}
}