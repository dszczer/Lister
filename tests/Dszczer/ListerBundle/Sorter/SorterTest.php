<?php

namespace Dszczer\ListerBundle\Sorter;

use Dszczer\ListerBundle\AuthorQuery;
use Dszczer\ListerBundle\Lister\Lister;
use Dszczer\ListerBundle\ListerTestCase;
use Dszczer\ListerBundle\Map\AuthorTableMap;

/**
 * Class SorterTest
 * @package Dszczer\ListerBundle\Sorter
 */
class SorterTest extends ListerTestCase
{
    public function testConstructorNoArgs()
    {
        $sorter = new Sorter();
        $this->assertEquals('', $sorter->getName());
        $this->assertEquals('', $sorter->getLabel());
        $this->assertEquals('', $sorter->getSorterMethod());
        $this->assertEquals(null, $sorter->getValue());
        $this->assertFalse($sorter->isDefaultMethod());
    }

    /**
     * @depends testConstructorNoArgs
     */
    public function testConstructorTypicalUse()
    {
        $sorter = new Sorter('myName', 'myLabel');
        $this->assertEquals('myName', $sorter->getName());
        $this->assertEquals('myLabel', $sorter->getLabel());
        $this->assertEquals('', $sorter->getSorterMethod());
        $this->assertEquals(null, $sorter->getValue());
        $this->assertTrue($sorter->isDefaultMethod());
    }

    /**
     * @depends testConstructorTypicalUse
     */
    public function testGettersAndSetters()
    {
        $value = 'ASC';
        $sorter = new Sorter('myName', 'myLabel', 'myMethod');
        $this->assertEquals('myName', $sorter->getName());
        $this->assertEquals('myLabel', $sorter->getLabel());
        $this->assertEquals('myMethod', $sorter->getSorterMethod());
        $this->assertEquals(null, $sorter->getValue());
        $this->assertFalse($sorter->isDefaultMethod());

        $sorter
            ->setName('myName')
            ->setLabel('myLabel')
            ->setSorterMethod('myMethod')
            ->setValue($value);
        $this->assertEquals('myName', $sorter->getName());
        $this->assertEquals('myLabel', $sorter->getLabel());
        $this->assertEquals('myMethod', $sorter->getSorterMethod());
        $this->assertEquals($value, $sorter->getValue());
        $this->assertFalse($sorter->isDefaultMethod());
    }

    /**
     * @depends testGettersAndSetters
     * @throws SorterException
     * @throws \Dszczer\ListerBundle\Lister\ListerException
     */
    public function testListerNotReady()
    {
        $sorter = new Sorter();
        $sorter->setValue('ASC');
        $this->expectException(SorterException::class);
        $sorter->apply(new Lister());
    }

    /**
     * @depends testListerNotReady
     * @depends testGettersAndSetters
     * @throws \Dszczer\ListerBundle\Lister\ListerException
     * @throws SorterException
     */
    public function testApply()
    {
        $sorter = new Sorter('firstName');
        $sorter->setValue('ASC');
        $lister = new Lister('', '');
        $lister->setQuery(AuthorQuery::create());

        $sorter->apply($lister);
        $query = $lister->getQuery(false);
        $cols = $query->getOrderByColumns();
        $this->assertArrayHasKey(0, $cols);
        $this->assertContains(AuthorTableMap::COL_FIRST_NAME, $cols[0]);

        $query->clear();
        $sorter->setSorterMethod('orderByFirstName')
            ->apply($lister);
        $cols = $query->getOrderByColumns();
        $this->assertArrayHasKey(0, $cols);
        $this->assertContains(AuthorTableMap::COL_FIRST_NAME, $cols[0]);
    }

    /**
     * @depends testListerNotReady
     * @depends testGettersAndSetters
     * @throws \Dszczer\ListerBundle\Lister\ListerException
     * @throws SorterException
     * @throws \Exception
     */
    public function testDoctrineApply()
    {
        $sorter = new Sorter('firstName');
        $sorter->setValue('ASC');
        $lister = self::$myKernel->getContainer()->get('lister.factory')->createList('DszczerListerBundle:Author');

        $sorter->apply($lister);
    }
}