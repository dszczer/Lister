<?php

namespace Dszczer\ListerBundle\Sorter;

class SorterBagTest extends \PHPUnit_Framework_TestCase
{
    public function testInstance()
    {
        $bag = new SorterBag();
        $bag->set('test', new Sorter());
    }
}