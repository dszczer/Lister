<?php
/**
 * Bag class representation.
 * @category     Utils
 * @author       Damian Szczerbiński <dszczer@gmail.com>
 */

namespace Dszczer\ListerBundle\Util;

/**
 * Class Bag
 * @package Dszczer\ListerBundle
 */
class Bag implements \IteratorAggregate, \Countable
{
    /**
     * Storage.
     * @var mixed[]
     */
    protected $array = [];
    /**
     * If set valid class name then can validate each saved parameter.
     * @var string
     */
    protected $instanceValidator = '';

    /**
     * Constructor.
     * @param mixed[] $array An array
     */
    public function __construct(array $array = [])
    {
        $this->validateInstance($array);
        $this->array = $array;
    }

    /**
     * Returns the array.
     * @return mixed[] An array
     */
    public function all()
    {
        return $this->array;
    }

    /**
     * Returns the array keys.
     * @return mixed[] An array of array keys
     */
    public function keys()
    {
        return array_keys($this->array);
    }

    /**
     * Replaces the current array by a new set.
     * @param mixed[] $array An array
     */
    public function replace(array $array = [])
    {
        $this->validateInstance($array);
        $this->array = $array;
    }

    /**
     * Adds array.
     * @param mixed[] $array An array
     */
    public function add(array $array = [])
    {
        $this->validateInstance($array);
        $this->array = array_replace($this->array, $array);
    }

    /**
     * Returns a parameter by name.
     * @param string $key The key
     * @param mixed|null $default The default value if the parameter key does not exist
     * @return mixed|null
     * @throws \InvalidArgumentException
     */
    public function get(string $key, $default = null)
    {
        return array_key_exists($key, $this->array) ? $this->array[$key] : $default;
    }

    /**
     * Sets a parameter by name.
     * @param string $key The key
     * @param mixed $value The value
     */
    public function set(string $key, $value)
    {
        $this->validateInstance($value);
        $this->array[$key] = $value;
    }

    /**
     * Returns true if the parameter is defined.
     * @param string $key The key
     * @return bool true if the parameter exists, false otherwise
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->array);
    }

    /**
     * Removes a parameter.
     * @param string $key The key
     */
    public function remove(string $key)
    {
        unset($this->array[$key]);
    }

    /**
     * Returns an iterator for array.
     * @return \ArrayIterator An \ArrayIterator instance
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->array);
    }

    /**
     * Returns the number of array.
     * @return int The number of array
     */
    public function count(): int
    {
        return count($this->array);
    }

    /**
     * Get class name to validate against.
     * @return string
     */
    public function getInstanceValidator(): string
    {
        return $this->instanceValidator;
    }

    /**
     * Set class name to validate against.
     * @param string $instanceValidator
     */
    public function setInstanceValidator(string $instanceValidator)
    {
        $this->instanceValidator = $instanceValidator;
    }

    /**
     * Check if data is a collection or an instance of specific class if validator is on.
     * There is clear pass when no error.
     * @param mixed $data
     * @throws \InvalidArgumentException
     */
    protected function validateInstance($data)
    {
        if ($this->instanceValidator && class_exists($this->instanceValidator)) {
            if (is_array($data)) {
                foreach ($data as $key => $item) {
                    if (!$item instanceof $this->instanceValidator) {
                        throw new \InvalidArgumentException(
                            'Data is instance of "'.get_class(
                                $data
                            ).'", should be "'.$this->instanceValidator.'" at '.$key
                        );
                    }
                }
            } elseif (!$data instanceof $this->instanceValidator) {
                throw new \InvalidArgumentException(
                    'Data is instance of "'.get_class(
                        $data
                    ).'", should be "'.$this->instanceValidator.'"'
                );
            }
        }
    }
}