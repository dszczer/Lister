<?php

namespace Dszczer\ListerBundle\Filter;

/**
 * Class FilterBagTest
 * @package Dszczer\ListerBundle\Filter
 */
class FilterBagTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @throws FilterException
     */
    public function testInstance()
    {
        $bag = new FilterBag();
        $bag->set('test', new Filter(Filter::TYPE_TEXT));
    }


    public function testWrongInstance()
    {
        $bag = new FilterBag();
        $this->expectException(\InvalidArgumentException::class);
        $bag->set('test', new \stdClass());
    }
}