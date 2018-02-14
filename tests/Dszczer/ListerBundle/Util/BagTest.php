<?php

namespace Dszczer\ListerBundle\Util;

/**
 * Class BagTest
 * @package Dszczer\ListerBundle\Util
 */
class BagTest extends \PHPUnit_Framework_TestCase
{
    public function testConstructor()
    {
        new Bag();
    }

    /**
     * @depends testConstructor
     */
    public function testAll()
    {
        $bag = new Bag(array('foo' => 'bar'));
        $this->assertEquals(array('foo' => 'bar'), $bag->all(), '->all() gets all the input');
    }

    /**
     * @depends testAll
     */
    public function testGetIterator()
    {
        $parameters = array('foo' => 'bar', 'hello' => 'world');
        $bag = new Bag($parameters);

        $i = 0;
        foreach ($bag as $key => $val) {
            ++$i;
            $this->assertEquals($parameters[$key], $val);
        }

        $this->assertEquals(count($parameters), $i);
    }

    /**
     * @depends testAll
     */
    public function testCount()
    {
        $parameters = array('foo' => 'bar', 'hello' => 'world');
        $bag = new Bag($parameters);

        $this->assertEquals(count($parameters), count($bag));
    }

    /**
     * @depends testAll
     */
    public function testKeys()
    {
        $bag = new Bag(array('foo' => 'bar'));
        $this->assertEquals(array('foo'), $bag->keys());
    }

    /**
     * @depends testAll
     */
    public function testAdd()
    {
        $bag = new Bag(array('foo' => 'bar'));
        $bag->add(array('bar' => 'bas'));
        $this->assertEquals(array('foo' => 'bar', 'bar' => 'bas'), $bag->all());
    }

    /**
     * @depends testAll
     */
    public function testHas()
    {
        $bag = new Bag(array('foo' => 'bar'));

        $this->assertTrue($bag->has('foo'), '->has() returns true if a parameter is defined');
        $this->assertFalse($bag->has('unknown'), '->has() return false if a parameter is not defined');
    }

    /**
     * @depends testAll
     * @depends testAdd
     */
    public function testRemove()
    {
        $bag = new Bag(array('foo' => 'bar'));
        $bag->add(array('bar' => 'bas'));
        $this->assertEquals(array('foo' => 'bar', 'bar' => 'bas'), $bag->all());
        $bag->remove('bar');
        $this->assertEquals(array('foo' => 'bar'), $bag->all());
    }

    /**
     * @depends testAll
     * @depends testHas
     */
    public function testReplace()
    {
        $bag = new Bag(array('foo' => 'bar'));

        $bag->replace(array('FOO' => 'BAR'));
        $this->assertEquals(array('FOO' => 'BAR'), $bag->all(), '->replace() replaces the input with the argument');
        $this->assertFalse($bag->has('foo'), '->replace() overrides previously set the input');
    }

    /**
     * @depends testAll
     */
    public function testGet()
    {
        $bag = new Bag(array('foo' => 'bar', 'null' => null));

        $this->assertEquals('bar', $bag->get('foo'), '->get() gets the value of a parameter');
        $this->assertEquals('default', $bag->get('unknown', 'default'), '->get() returns second argument as default if a parameter is not defined');
        $this->assertNull($bag->get('null', 'default'), '->get() returns null if null is set');
    }

    /**
     * @depends testAll
     * @depends testGet
     */
    public function testSet()
    {
        $bag = new Bag(array());

        $bag->set('foo', 'bar');
        $this->assertEquals('bar', $bag->get('foo'), '->set() sets the value of parameter');

        $bag->set('foo', 'baz');
        $this->assertEquals('baz', $bag->get('foo'), '->set() overrides previously set parameter');
    }

    public function testSetInstanceValidator()
    {
        $bag = new Bag();
        $bag->setInstanceValidator(Bag::class);
    }

    /**
     * @depends testAll
     * @depends testSetInstanceValidator
     */
    public function testGetInstanceValidator()
    {
        $bag = new Bag();
        $bag->setInstanceValidator(Bag::class);
        $this->assertEquals(Bag::class, $bag->getInstanceValidator());
    }

    /**
     * @depends testAll
     * @depends testSetInstanceValidator
     */
    public function testInvalidParameterInstance()
    {
        $bag = new Bag(['foo', 'bar']);
        $this->expectException(\InvalidArgumentException::class);
        $bag->setInstanceValidator(Bag::class);
    }
}