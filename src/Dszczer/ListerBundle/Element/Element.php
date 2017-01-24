<?php
/**
 * Element class representation.
 * @category     Element
 * @author       Damian Szczerbiński <dszczer@gmail.com>
 */
namespace Dszczer\ListerBundle\Element;

use Dszczer\ListerBundle\Util\Helper;

/**
 * Class Element
 * Determines how specified piece of data should be displayed.
 * @package Dszczer\ListerBundle
 */
class Element
{
    /** @var mixed Data to display */
    protected $data;
    /** @var string Name of Data */
    protected $name;
    /** @var string Label of Data */
    protected $label;
    /** @var string method to call on data source if is object type */
    protected $elementMethod;
    /** @var callable for custom data view */
    protected $callable;
    /** @var bool Flag to use callable (true) or call method (false) */
    protected $custom = false;
    /** @var bool Flag to mark if $elementMethod was not set */
    protected $default = true;

    /**
     * Element constructor.
     * @param string $name Name of Element
     * @param string $label Label to display
     * @param string $method Method to call on related Model object
     * @param callable|null $callable Callable for custom data view
     * @param mixed $data Data to display
     */
    public function __construct(
        string $name = '',
        string $label = '',
        string $method = '',
        callable $callable = null,
        $data = null
    ) {
        $this->name = $name;
        $this->label = $label;
        $this->callable = $callable;
        $this->data;
        $this->custom = is_callable($callable);

        if (empty($method) && !empty($name) && !$this->custom) {
            $this->elementMethod = Helper::camelize("get_$name");
        } else {
            $this->default = false;
            $this->elementMethod = $method;
        }
    }

    /**
     * Get data to render.
     * @param bool $raw Return unprocessed data.
     * @return mixed
     */
    public function getData(bool $raw = false)
    {
        if ($raw) {
            return $this->data;
        }
        if ($this->isCustom()) {
            return call_user_func_array($this->getCallable(), [$this]);
        }

        return is_object($this->data) ? $this->data->{$this->elementMethod}() : $this->data;
    }

    /**
     * Set data to render.
     * @param mixed $data
     * @return Element
     */
    public function setData($data): ELement
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Get name of $this Element.
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set name of $this Element.
     * @param string $name
     * @return Element
     */
    public function setName(string $name): Element
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get displayable Label name of $this Element.
     * @return string
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * Set displayable Label name of $this Element.
     * @param string $label
     * @return Element
     */
    public function setLabel(string $label): Element
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Get method name to call on object when retriving data to render.
     * @return string
     */
    public function getMethod(): string
    {
        return $this->elementMethod;
    }

    /**
     * Set method name to call on object when retriving data to render.
     * @param string $method
     * @return Element
     */
    public function setMethod(string $method): Element
    {
        $this->default = false;
        $this->elementMethod = $method;

        return $this;
    }

    /**
     * Get callable to be called when retriving data to render.
     * @return callable|null Callable or null if not set.
     */
    public function getCallable()
    {
        return $this->callable;
    }

    /**
     * Set callable to be callend when retriving data to render.
     * @param callable $callable
     * @return Element
     */
    public function setCallable(callable $callable): Element
    {
        $this->callable = $callable;

        return $this;
    }

    /**
     * Determine if use method or set callable to use when retriving data to render.
     * @param bool $state True for callable, false for method.
     * @return Element
     */
    public function setCustom(bool $state): Element
    {
        $this->custom = $state && is_callable($this->callable);

        return $this;
    }

    /**
     * Check if Element has callable source of data.
     * @return bool
     */
    public function isCustom(): bool
    {
        return $this->custom;
    }

    /**
     * Check if method is a default Model generated stub.
     * @return bool True for name-based method name, false for defined one.
     */
    public function isDefaultMethod(): bool
    {
        return $this->default;
    }
}