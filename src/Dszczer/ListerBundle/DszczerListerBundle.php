<?php
/**
 * Bundle configuration.
 * @category Bundle configuration
 * @author   Damian Szczerbiński <dszczer@gmail.com>
 */

namespace Dszczer\ListerBundle;

use Dszczer\ListerBundle\DependencyInjection\ListerExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Class DszczerListerBundle
 * @package Dszczer\ListerBundle
 * @since 0.9
 */
class DszczerListerBundle extends Bundle
{
    /**
     * Get container instance of extension.
     * @see Bundle
     * @return ListerExtension
     */
    public function getContainerExtension()
    {
        return new ListerExtension();
    }
}