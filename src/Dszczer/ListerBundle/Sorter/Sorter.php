<?php
/**
 * Sorter class representation.
 * @category     Sorter
 * @author       Damian Szczerbiński <dszczer@gmail.com>
 */

namespace Dszczer\ListerBundle\Sorter;

use Doctrine\Common\Collections\Criteria;
use Dszczer\ListerBundle\Lister\Lister;
use Dszczer\ListerBundle\Util\Helper;
use Propel\Runtime\ActiveQuery\ModelCriteria;

/**
 * Class Filter
 * @package Dszczer\ListerBundle
 */
class Sorter
{
    /** @var string Name of sorter */
    protected $name;
    /** @var string Label of sorter */
    protected $label;
    /** @var mixed One of: null, 'asc', 'desc' */
    protected $value;
    /** @var string Method to call */
    protected $sorterMethod;
    /** @var bool Flag to determine if method is set or not */
    protected $default = true;

    /**
     * Sorter constructor.
     * @param string $name Name of sorter
     * @param string $label Label to display
     * @param string $method Method to call when applying sorter
     * @param mixed $value Value passed to method
     */
    public function __construct($name = '', $label = '', $method = '', $value = null)
    {
        $this->name = $name;
        $this->label = $label;
        $this->value = $value;

        if (empty($method) && !empty($name)) {
            $this->sorterMethod = Helper::camelize("order_by_$name");
        } else {
            $this->default = false;
            $this->sorterMethod = $method;
        }
    }

    /**
     * Get sorter name.
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set sorter name.
     * @param string $name
     * @return Sorter
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get sorter label.
     * @return string
     */
    public function getLabel()
    {
        return $this->label;
    }

    /**
     * Set sorter label.
     * @param string $label
     * @return Sorter
     */
    public function setLabel($label)
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Get sorting value.
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Set sorting value.
     * @param mixed $value
     * @return Sorter
     */
    public function setValue($value)
    {
        $this->value = $value;

        return $this;
    }

    /**
     * Get method name.
     * @return string
     */
    public function getSorterMethod()
    {
        return $this->sorterMethod;
    }

    /**
     * Set method name.
     * @param string $sorterMethod
     * @return Sorter
     */
    public function setSorterMethod($sorterMethod)
    {
        $this->default = false;
        $this->sorterMethod = $sorterMethod;

        return $this;
    }

    /**
     * Check if method is defined or not.
     * @return bool True for defined method, false for default generated stub
     */
    public function isDefaultMethod()
    {
        return $this->default;
    }

    /**
     * Apply sorting to the list.
     * @param Lister $lister Lister object
     * @param array $extraArguments Additional arguments to pass to called method
     * @return mixed Value returned by called method
     * @throws SorterException
     */
    public function apply(Lister $lister, array $extraArguments = [])
    {
        if (in_array($this->value, [Criteria::ASC, Criteria::DESC])) {
            $query = $lister->getQuery(false);
            if ($query instanceof ModelCriteria) {
                return $this->rawApply(
                    $query,
                    array_merge(is_array($this->value) ? $this->value : [$this->value], $extraArguments)
                );
            } else {
                throw new SorterException('Lister is not ready to apply sorter - missing assigned query object');
            }
        }

        return null;
    }

    /**
     * Call method on object.
     * @param ModelCriteria $query
     * @param array $args
     * @return mixed
     */
    protected function rawApply(ModelCriteria $query, array $args)
    {
        return call_user_func_array([$query, $this->sorterMethod], $args);
    }
}