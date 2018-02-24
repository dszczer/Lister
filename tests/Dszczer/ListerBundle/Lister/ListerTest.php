<?php

namespace Dszczer\ListerBundle\Lister;

use Dszczer\ListerBundle\AuthorQuery;
use Dszczer\ListerBundle\BookQuery;
use Dszczer\ListerBundle\Element\Element;
use Dszczer\ListerBundle\Filter\Filter;
use Dszczer\ListerBundle\ListerTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class ListerTest
 * @package Dszczer\ListerBundle\Lister
 */
class ListerTest extends ListerTestCase
{
    /** @var array */
    private static $serializedLister;

    /**
     * @throws ListerException
     */
    public function testConstructorNoArgs()
    {
        $lister = new Lister();
        $this->assertInstanceOf(Lister::class, $lister);
    }

    /**
     * @depends testConstructorNoArgs
     * @throws ListerException
     * @throws \Dszczer\ListerBundle\Filter\FilterException
     * @throws \Dszczer\ListerBundle\Sorter\SorterException
     * @throws \Exception
     */
    public function testBlankDoctrineApply()
    {
        $lister = static::$myKernel->getContainer()->get('lister.factory')->createList('DszczerListerBundle:Book');
        $this->expectException(ListerException::class);
        $lister->apply(Request::create('/'));
        $this->assertInstanceOf(PagerHelper::class, $lister->getPager());
        $this->assertEquals(1, $lister->getCurrentPage());
        $this->assertEmpty($lister->getHydratedElements());
    }

    /**
     * @depends testBlankDoctrineApply
     * @throws ListerException
     * @throws \Dszczer\ListerBundle\Filter\FilterException
     * @throws \Dszczer\ListerBundle\Sorter\SorterException
     * @throws \Exception
     */
    public function testOneElementDoctrineListApply()
    {
        $lister = static::$myKernel->getContainer()->get('lister.factory')->createList('DszczerListerBundle:Book');
        $element = new Element('id', 'Book id');
        $lister->addElement($element);
        $lister->apply(Request::create('/'));
        $this->assertNotEmpty($lister->getHydratedElements());
    }

    /**
     * @depends testBlankDoctrineApply
     * @throws ListerException
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Dszczer\ListerBundle\Filter\FilterException
     * @throws \Dszczer\ListerBundle\Sorter\SorterException
     * @throws \Exception
     */
    public function testOneFiledDefaultArgumentsDoctrineListApply()
    {
        $lister = static::$myKernel->getContainer()->get('lister.factory')->createList('DszczerListerBundle:Author');
        $lister->addField('firstName', 'Author name');
        $lister->apply(Request::create('/'));
    }

    /**
     * @depends testBlankDoctrineApply
     * @throws ListerException
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Dszczer\ListerBundle\Filter\FilterException
     * @throws \Dszczer\ListerBundle\Sorter\SorterException
     * @throws \Exception
     */
    public function testDoctrineFilteringList()
    {
        $container = static::$myKernel->getContainer();
        $lister = $container->get('lister.factory')->createList('DszczerListerBundle:Author');
        $lister->addField('firstName', 'Author name', true, Filter::TYPE_TEXT, '', 'Foo');
        $lister->apply(Request::create('/'));
    }

    /**
     * @depends testBlankDoctrineApply
     * @throws ListerException
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Dszczer\ListerBundle\Filter\FilterException
     * @throws \Dszczer\ListerBundle\Sorter\SorterException
     * @throws \Exception
     */
    public function testDoctrineSortingList()
    {
        $container = static::$myKernel->getContainer();
        $lister = $container->get('lister.factory')->createList('DszczerListerBundle:Author');
        $lister->addField('firstName', 'Author name', true, '', '', null, [], '', 'ASC');
        $lister->apply(Request::create('/'));
    }

    /**
     * @depends testConstructorNoArgs
     * @throws ListerException
     * @throws \Dszczer\ListerBundle\Filter\FilterException
     * @throws \Dszczer\ListerBundle\Sorter\SorterException
     * @throws \Exception
     */
    public function testBlankApply()
    {
        $lister = static::$myKernel->getContainer()->get('lister.factory')->createList(BookQuery::class);
        $this->expectException(ListerException::class);
        $lister->apply(Request::create('/'));
        $this->assertInstanceOf(PagerHelper::class, $lister->getPager());
        $this->assertEquals(1, $lister->getCurrentPage());
        $this->assertEmpty($lister->getHydratedElements());
    }

    /**
     * @depends testBlankApply
     * @throws ListerException
     * @throws \Dszczer\ListerBundle\Filter\FilterException
     * @throws \Dszczer\ListerBundle\Sorter\SorterException
     * @throws \Exception
     */
    public function testOneElementListApply()
    {
        $lister = static::$myKernel->getContainer()->get('lister.factory')->createList(BookQuery::class);
        $element = new Element('id', 'Book id');
        $lister->addElement($element);
        $lister->apply(Request::create('/'));
        $this->assertNotEmpty($lister->getHydratedElements());
    }

    /**
     * @depends testBlankApply
     * @throws ListerException
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Dszczer\ListerBundle\Filter\FilterException
     * @throws \Dszczer\ListerBundle\Sorter\SorterException
     * @throws \Exception
     */
    public function testOneFiledDefaultArgumentsListApply()
    {
        $lister = static::$myKernel->getContainer()->get('lister.factory')->createList(AuthorQuery::class);
        $lister->addField('firstName', 'Author name');
        $lister->apply(Request::create('/'));
    }

    /**
     * @depends testBlankApply
     * @throws ListerException
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Dszczer\ListerBundle\Filter\FilterException
     * @throws \Dszczer\ListerBundle\Sorter\SorterException
     * @throws \Exception
     */
    public function testFilteringList()
    {
        $container = static::$myKernel->getContainer();
        $lister = $container->get('lister.factory')->createList(AuthorQuery::class);
        $lister->addField('firstName', 'Author name', true, Filter::TYPE_TEXT, '', 'Foo');
        $lister->apply(Request::create('/'));
    }

    /**
     * @depends testBlankApply
     * @throws ListerException
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Dszczer\ListerBundle\Filter\FilterException
     * @throws \Dszczer\ListerBundle\Sorter\SorterException
     * @throws \Exception
     */
    public function testSortingList()
    {
        $container = static::$myKernel->getContainer();
        $lister = $container->get('lister.factory')->createList(AuthorQuery::class);
        $lister->addField('firstName', 'Author name', true, '', '', null, [], '', 'ASC');
        $lister->apply(Request::create('/'));
    }

    /**
     * @depends testBlankApply
     * @throws ListerException
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Dszczer\ListerBundle\Filter\FilterException
     * @throws \Dszczer\ListerBundle\Sorter\SorterException
     * @throws \Exception
     */
    public function testSerializingList()
    {
        $container = static::$myKernel->getContainer();
        $lister = $container->get('lister.factory')->createList('DszczerListerBundle:Author');
        $lister->addField('firstName', 'Author name', true, '', '', null, [], '', 'ASC');
        $lister->apply(Request::create('/'));
        self::$serializedLister = $lister->serialize();
    }

    /**
     * @depends testSerializingList
     * @throws \Exception
     */
    public function testUnserializingList()
    {
        $container = static::$myKernel->getContainer();
        $lister = new Lister();
        $lister->unserialize(self::$serializedLister);
        $lister->setRepository($container->get('doctrine')->getRepository('DszczerListerBundle:Author'));
        $lister->setQuery($lister->getRepository()->createQueryBuilder('e'));
        $lister->apply(Request::create('/'));
    }
}