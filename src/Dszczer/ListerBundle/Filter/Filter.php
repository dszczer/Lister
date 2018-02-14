<?php
/**
 * Filter class representation.
 * @category Filter
 * @author   Damian Szczerbiński <dszczer@gmail.com>
 */

namespace Dszczer\ListerBundle\Filter;

use Doctrine\ORM\EntityRepository;
use Dszczer\ListerBundle\Lister\Lister;
use Dszczer\ListerBundle\Util\Helper;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\RadioType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

/**
 * Class Filter
 * @package Dszczer\ListerBundle
 * @since 0.9
 */
class Filter
{
    const TYPE_TEXT = 'text';
    const TYPE_SELECT = 'select';
    const TYPE_MULTISELECT = 'multi';
    const TYPE_CHECKBOX = 'checkbox';
    const TYPE_RADIO = 'radio';

    /** @var string Type of filter input */
    protected $type;

    /** @var string Name of Filter object */
    protected $name;

    /** @var string Label to display */
    protected $label;

    /** @var mixed Filter's value */
    protected $value;

    /** @var mixed[] Enum values to use selectable list/check, instead of free input */
    protected $values = [];

    /** @var string Method to call when applying filter */
    protected $filterMethod;

    /** @var bool Flag to mark if $filterMethod was set or not */
    protected $default = true;

    /**
     * Filter constructor.
     * @param string $type Type of filter form field
     * @param string $name Name of filter form field
     * @param string $label Label of filter form field
     * @param string $method Method to call on list's object when applying filter
     * @param mixed $value Default value of filter form field
     * @param array $values Possible values for selectable items (check/radio button, select)
     * @throws FilterException
     */
    public function __construct(
        string $type,
        string $name = '',
        string $label = '',
        string $method = '',
        $value = null,
        array $values = []
    )
    {
        $this->type = $type;
        $this->name = $name;
        $this->label = $label;
        $this->filterMethod = $method;
        $this->value = $value;
        $this->values = $values;

        $this->checkType();

        $this->default = empty($method) && !empty($name);
    }

    /**
     * Get Filter type.
     * @param bool $className true for Symfony Type's full class name or false for constant string one
     * @return string
     */
    public function getType(bool $className = true): string
    {
        if ($className) {
            // cannot use switch syntax because of condition order
            if (
                ($this->type === Filter::TYPE_CHECKBOX && !empty($this->values))
                || ($this->type === Filter::TYPE_RADIO && !empty($this->values))
                || $this->type === Filter::TYPE_SELECT
                || $this->type === Filter::TYPE_MULTISELECT
            ) {
                return ChoiceType::class;
            } elseif ($this->type === Filter::TYPE_CHECKBOX) {
                return CheckboxType::class;
            } elseif ($this->type === Filter::TYPE_RADIO) {
                return RadioType::class;
            } else {
                return TextType::class;
            }
        }

        return $this->type;
    }

    /**
     * Set Filter type.
     * @param string $type
     * @return Filter
     * @throws FilterException
     */
    public function setType(string $type): Filter
    {
        $this->type = $type;
        $this->checkType();

        return $this;
    }

    /**
     * Validate set type of Filter.
     * @throws FilterException
     */
    private function checkType()
    {
        if (!in_array(
            $this->type,
            [
                static::TYPE_TEXT,
                static::TYPE_CHECKBOX,
                static::TYPE_MULTISELECT,
                static::TYPE_RADIO,
                static::TYPE_SELECT,
            ]
        )
        ) {
            throw new FilterException(sprintf('Invalid filter type "%s"', $this->type));
        }
    }

    /**
     * Get name of $this Filter.
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set name of $this Filter.
     * @param string $name
     * @return Filter
     */
    public function setName(string $name): Filter
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get label of filter's form field.
     * @return string
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * Set label of filter's form field.
     * @param string $label
     * @return Filter
     */
    public function setLabel(string $label): Filter
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Get filter value.
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Set filter value.
     * @param mixed $value
     * @return Filter
     */
    public function setValue($value): Filter
    {
        $this->value = $value;

        return $this;
    }

    /**
     * Get enum values to use as filter value.
     * @return array
     */
    public function getValues(): array
    {
        return $this->values;
    }

    /**
     * Set enum values to use as filter value.
     * @param array $values
     * @return Filter
     */
    public function setValues(array $values): Filter
    {
        $this->values = $values;

        return $this;
    }

    /**
     * Get filter method name to call when applying filter.
     * @return string
     */
    public function getFilterMethod(): string
    {
        return $this->filterMethod;
    }

    /**
     * Set filter method name to call when applying filter.
     * @param string $filterMethod
     * @return Filter
     */
    public function setFilterMethod(string $filterMethod): Filter
    {
        $this->default = false;
        $this->filterMethod = $filterMethod;

        return $this;
    }

    /**
     * Check if method name is set or not.
     * @return bool True for default generated Model method stub, false for defined one.
     */
    public function isDefaultMethod(): bool
    {
        return $this->default;
    }

    /**
     * Apply filter value by calling defined method on query object.
     * @param Lister $lister List to apply filter on
     * @param array $extraArguments Additional parameters passed to called method
     * @return mixed Value returned by called method
     * @throws FilterException
     */
    public function apply(Lister $lister, array $extraArguments = [])
    {
        if ($this->getType() == ChoiceType::class && empty($this->values)) {
            throw new FilterException('Filter values is required when filter is enum type.');
        }

        $query = $lister->getQuery(false);
        if (!is_object($query)) {
            throw new FilterException('Lister is not ready to apply filter - missing assigned query object');
        }

        if ($this->value !== null) {
            if ($this->default) {
                $this->filterMethod = Helper::camelize("filter_by_{$this->name}");
                if ($query instanceof ModelCriteria) {
                    if(!method_exists($query, $this->filterMethod)) {
                        throw new FilterException(
                            sprintf('Method "%s" of assigned query object is not defined', $this->filterMethod)
                        );
                    }
                    return call_user_func_array(
                        [$query, $this->filterMethod],
                        array_merge([$this->value], $extraArguments)
                    );
                } else {
                    $repository = $lister->getRepository();
                    if ($repository instanceof EntityRepository && method_exists($repository, $this->filterMethod)) {
                        return call_user_func_array(
                            [$repository, $this->filterMethod],
                            array_merge([$query, $this->value], $extraArguments)
                        );
                    } else {
                        $this->filterMethod = $this->name;
                    }
                }
            }

            if ($query instanceof ModelCriteria) {
                return call_user_func_array([$query, $this->filterMethod], array_merge([$this->value], $extraArguments));
            } else {
                $aliases = $query->getRootAliases();
                $alias = array_shift($aliases);
                if(!empty($extraArguments[0]) && $extraArguments[0] === 'LIKE') {
                    $isLike = true;
                    array_shift($extraArguments);
                } else {
                    $isLike = false;
                }
                if ($this->type === self::TYPE_TEXT && $isLike) {
                    array_unshift(
                        $extraArguments,
                        $query->expr()->like(
                            $alias . '.' . $this->filterMethod,
                            $query->expr()->literal($this->value)
                        )
                    );
                } else {
                    array_unshift($extraArguments, $query->expr()->eq($alias . '.' . $this->filterMethod, $this->value));
                }

                return call_user_func_array([$query, 'andWhere'], $extraArguments);
            }
        } else {
            return $query;
        }
    }
}