<?php
/**
 * Filter bag representation.
 * @category Filter
 * @author   Damian Szczerbiński <dszczer@gmail.com>
 */

namespace Dszczer\ListerBundle\Filter;

use Dszczer\ListerBundle\Util\Bag;

/**
 * Class FilterBag
 * @package Dszczer\ListerBundle
 * @since 0.9
 */
class FilterBag extends Bag
{
    /**
     * SorterBag constructor.
     * @param Filter[]|array $array
     */
    public function __construct(array $array = [])
    {
        $this->instanceValidator = '\\Dszczer\\ListerBundle\\Filter\\Filter';
        parent::__construct($array);
    }
}
