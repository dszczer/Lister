<?php

namespace Dszczer\ListerBundle\Element;

/**
 * Class ElementBagTest
 * @package Dszczer\ListerBundle\Element
 */
class ElementBagTest extends \PHPUnit_Framework_TestCase
{
    public function testInstance()
    {
        $bag = new ElementBag();
        $bag->set('test', new Element());
    }
    
    public function testWrongInstance()
    {
        $bag = new ElementBag();
        $this->expectException(\InvalidArgumentException::class);
        $bag->set('test', new \stdClass());
    }
}