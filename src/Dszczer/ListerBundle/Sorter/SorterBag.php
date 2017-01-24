<?php
/**
 * Sorter bag representation.
 * @category     Sorter
 * @author       Damian Szczerbiński <dszczer@gmail.com>
 */

namespace Dszczer\ListerBundle\Sorter;

use Dszczer\ListerBundle\Util\Bag;

/**
 * Class SorterBag
 * @package Dszczer\ListerBundle
 */
class SorterBag extends Bag
{
    /**
     * SorterBag constructor.
     * @param Sorter[] $array
     */
    public function __construct(array $array = [])
    {
        $this->instanceValidator = '\\Dszczer\\ListerBundle\\Sorter\\Sorter';
        parent::__construct($array);
    }
}
