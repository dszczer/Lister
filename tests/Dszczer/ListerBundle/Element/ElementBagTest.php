<?php

namespace Dszczer\ListerBundle\Element;

class ElementBagTest extends \PHPUnit_Framework_TestCase
{
    public function testInstance()
    {
        $bag = new ElementBag();
        $bag->set('test', new Element());
    }
}