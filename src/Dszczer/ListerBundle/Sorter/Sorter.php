<?php
/**
 * Sorter class representation.
 * @category Sorter
 * @author   Damian Szczerbiński <dszczer@gmail.com>
 */

namespace Dszczer\ListerBundle\Sorter;

use Doctrine\ORM\EntityRepository;
use Dszczer\ListerBundle\Lister\Lister;
use Dszczer\ListerBundle\Util\Helper;
use Propel\Runtime\ActiveQuery\ModelCriteria;

/**
 * Class Filter
 * @package Dszczer\ListerBundle
 * @since 0.9
 */
class Sorter
{
    /** @var string Name of sorter */
    protected $name;

    /** @var string Label of sorter */
    protected $label;

    /** @var string|null One of: null, 'asc', 'desc' */
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
    public function __construct(string $name = '', string $label = '', string $method = '', $value = null)
    {
        $this->name = $name;
        $this->label = $label;
        $this->sorterMethod = $method;
        $this->setValue($value);

        $this->default = empty($method) && !empty($name);
    }

    /**
     * Get sorter name.
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set sorter name.
     * @param string $name
     * @return Sorter
     */
    public function setName(string $name): Sorter
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get sorter label.
     * @return string
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * Set sorter label.
     * @param string $label
     * @return Sorter
     */
    public function setLabel(string $label): Sorter
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Get sorting value.
     * @return string|null
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Set sorting value.
     * @param string|null $value
     * @return Sorter
     */
    public function setValue($value): Sorter
    {
        $this->value = $value === null ? null : mb_strtoupper(trim($value));

        return $this;
    }

    /**
     * Get method name.
     * @return string
     */
    public function getSorterMethod(): string
    {
        return $this->sorterMethod;
    }

    /**
     * Set method name.
     * @param string $sorterMethod
     * @return Sorter
     */
    public function setSorterMethod(string $sorterMethod): Sorter
    {
        $this->default = false;
        $this->sorterMethod = $sorterMethod;

        return $this;
    }

    /**
     * Check if method is defined or not.
     * @return bool True for defined method, false for default generated stub
     */
    public function isDefaultMethod(): bool
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
        if (in_array($this->value, ['ASC', 'DESC'])) {
            $query = $lister->getQuery(false);
            if (is_object($query)) {
                if($this->default) {
                    $this->sorterMethod = Helper::camelize("order_by_{$this->name}");
                }
                if($query instanceof ModelCriteria) {
                    return call_user_func_array(
                        [$query, $this->sorterMethod],
                        array_merge(is_array($this->value) ? $this->value : [$this->value], $extraArguments)
                    );
                } else {
                    $aliases = $query->getRootAliases();
                    $alias = array_shift($aliases);
                    $repository = $lister->getRepository();
                    if ($repository instanceof EntityRepository && method_exists($repository, $this->sorterMethod)) {
                        return call_user_func_array(
                            [$repository, $this->sorterMethod],
                            array_merge([$query, $this->value], $extraArguments)
                        );
                    } elseif($this->value === 'ASC') {
                        if($this->default) {
                            $this->sorterMethod = $this->name;
                        }
                        return $query->addOrderBy($alias . '.' . $this->sorterMethod, $this->value);
                    }
                }
            } else {
                throw new SorterException('Lister is not ready to apply sorter - missing assigned query object');
            }
        }

        return null;
    }
}