<?php
class GetTest extends PHPUnit_Framework_TestCase {
    private
        $db;

    public function setUp () {
        $this->db = new \PDO('mysql:dbname=moa', 'travis');
        $this->db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    public function testGetAllPropertiesOfExistingObject () {
        $string = new \sandbox\model\String($this->db);
        $string->save();

        $data = [
            'id' => null,
            'name' => ''
        ];

        $data['id'] = $string['id'];

        $properties = $string->getProperties();

        $this->assertSame($data, $properties);
    }

    public function testGetDefinedProperty () {
        $string = new \sandbox\model\String($this->db);
        $string['name'] = 'Foo';

        $this->assertSame('Foo', $string['name']);
    }

    /**
     * @expectedException gajus\moa\exception\Undefined_Property_Exception
     * @expectedExceptionMessage Trying to get non-object property "undefined_property".
     */
    public function testGetUndefinedProperty () {
        $string = new \sandbox\model\String($this->db);
        $string['undefined_property'];
    }

    /**
     * @dataProvider getDatetimePropertyProvider
     */
    public function testGetDatetimeProperty ($property_name) {
        $arbitrary_timestamp = time();

        $datetime = new \sandbox\model\Datetime($this->db);
        $datetime[$property_name] = $arbitrary_timestamp;

        $datetime->save();

        $this->assertSame($arbitrary_timestamp, $datetime[$property_name]);
    }

    public function getDatetimePropertyProvider () {
        return [
            ['datetime'],
            ['timestamp']
        ];
    }

    public function testIssetDefinedProperty () {
        $string = new \sandbox\model\String($this->db);

        $string['name'] = 'Foo';
        
        $this->assertTrue(isset($string['name']));
    }

    public function testIssetUndefinedProperty () {
        $string = new \sandbox\model\String($this->db);
        
        $this->assertFalse(isset($string['undefined_property']));
    }

    /**
     * @dataProvider unsetPropertyProvider
     */
    public function testUnsetProperty ($property_name) {
        $string = new \sandbox\model\String($this->db);
        
        unset($string[$property_name]);
    }

    public function unsetPropertyProvider () {
        return [
            ['defined_property'],
            ['undefined_property']
        ];
    }
}