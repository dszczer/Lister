<?php
/**
 * Unserializable Exception means Lister cannot be serialized into string.
 * @category Lister
 * @author   Damian Szczerbiński <dszczer@gmail.com>
 */

namespace Dszczer\ListerBundle\Lister;

/**
 * Class UnserializableException
 * @package Dszczer\ListerBundle\Lister
 * @since 0.9.1
 */
class UnserializableException extends \RuntimeException
{
}