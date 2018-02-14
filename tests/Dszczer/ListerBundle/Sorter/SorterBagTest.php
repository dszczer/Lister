<?php

namespace Dszczer\ListerBundle\Sorter;

/**
 * Class SorterBagTest
 * @package Dszczer\ListerBundle\Sorter
 */
class SorterBagTest extends \PHPUnit_Framework_TestCase
{
    public function testInstance()
    {
        $bag = new SorterBag();
        $bag->set('test', new Sorter());
    }


    public function testWrongInstance()
    {
        $bag = new SorterBag();
        $this->expectException(\InvalidArgumentException::class);
        $bag->set('test', new \stdClass());
    }
}