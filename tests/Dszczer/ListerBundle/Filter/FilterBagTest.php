<?php

namespace Dszczer\ListerBundle\Filter;

class FilterBagTest extends \PHPUnit_Framework_TestCase
{
    public function testInstance()
    {
        $bag = new FilterBag();
        $bag->set('test', new Filter(Filter::TYPE_TEXT));
    }
}