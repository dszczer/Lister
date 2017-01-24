<?php
/**
 * Element bag representation.
 * @category     Element
 * @author       Damian Szczerbiński <dszczer@gmail.com>
 */

namespace Dszczer\ListerBundle\Element;

use Dszczer\ListerBundle\Util\Bag;

/**
 * Class ElementBag
 * @package dszczer\ListerBundle
 */
class ElementBag extends Bag
{
    /**
     * SorterBag constructor.
     * @param Element[] $array
     */
    public function __construct(array $array = [])
    {
        $this->instanceValidator = '\\Dszczer\\ListerBundle\\Element\\Element';
        parent::__construct($array);
    }
}