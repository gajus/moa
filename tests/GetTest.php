<?php
class GetTest extends PHPUnit_Framework_TestCase {
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

    public function testGetAllPropertiesOfExistingObject () {
        $foo = new \Sandbox\Model\String($this->db);
        $foo->save();

        $data = [
            'id' => null,
            'name' => ''
        ];

        $data['id'] = $foo['id'];

        $properties = $foo->getProperties();

        $this->assertSame($data, $properties);
    }

    public function testGetDefinedProperty () {
        $foo = new \Sandbox\Model\String($this->db);
        $foo['name'] = 'Foo';

        $this->assertSame('Foo', $foo['name']);
    }

    /**
     * @expectedException Gajus\MOA\Exception\UndefinedPropertyException
     * @expectedExceptionMessage Cannot get property that is not in the object definition.
     */
    public function testGetUndefinedProperty () {
        $foo = new \Sandbox\Model\String($this->db);
        $foo['undefined_property'];
    }

    /**
     * @dataProvider getDatetimePropertyProvider
     */
    public function testGetDatetimeProperty ($property_name) {
        $arbitrary_timestamp = time();

        $foo = new \Sandbox\Model\Datetime($this->db);
        $foo[$property_name] = $arbitrary_timestamp;

        $foo->save();

        $this->assertSame($arbitrary_timestamp, $foo[$property_name]);
    }

    public function getDatetimePropertyProvider () {
        return [
            ['datetime'],
            ['timestamp']
        ];
    }

    public function testIssetDefinedProperty () {
        $foo = new \Sandbox\Model\String($this->db);

        $foo['name'] = 'Foo';
        
        $this->assertTrue(isset($foo['name']));
    }

    public function testIssetUndefinedProperty () {
        $foo = new \Sandbox\Model\String($this->db);
        
        $this->assertFalse(isset($foo['undefined_property']));
    }

    /**
     * @dataProvider unsetPropertyProvider
     */
    public function testUnsetProperty ($property_name) {
        $foo = new \Sandbox\Model\String($this->db);
        
        unset($foo[$property_name]);
    }

    public function unsetPropertyProvider () {
        return [
            ['defined_property'],
            ['undefined_property']
        ];
    }
}