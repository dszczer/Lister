<?php

namespace Dszczer\ListerBundle\Lister;

use Dszczer\ListerBundle\ListerTestCase;

/**
 * Class FactoryTest
 * @package Dszczer\ListerBundle\Lister
 */
class FactoryTest extends ListerTestCase
{
    public function testCreateFactory()
    {
        $factory = static::$myKernel->getContainer()->get('lister.factory');
        $this->assertInstanceOf(Factory::class, $factory);
    }

    /**
     * @throws ListerException
     * @throws \Exception
     */
    public function testCreatePropelLister()
    {
        $lister = static::$myKernel->getContainer()
            ->get('lister.factory')
            ->createList('\\Dszczer\\ListerBundle\\BookQuery');
        $this->assertInstanceOf(Lister::class, $lister);
    }

    /**
     * @throws ListerException
     * @throws \Exception
     */
    public function testCreateDoctrineLister()
    {
        $lister = static::$myKernel->getContainer()
            ->get('lister.factory')
            ->createList('DszczerListerBundle:Book');
        $this->assertInstanceOf(Lister::class, $lister);
    }
}