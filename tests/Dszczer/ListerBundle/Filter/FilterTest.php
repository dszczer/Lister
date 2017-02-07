<?php

namespace Dszczer\ListerBundle\Filter;

use Dszczer\ListerBundle\AuthorQuery;
use Dszczer\ListerBundle\Lister\Lister;
use Dszczer\ListerBundle\Map\AuthorTableMap;
use Propel\Runtime\ActiveQuery\Criterion\BasicCriterion;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\RadioType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class FilterTest extends \PHPUnit_Framework_TestCase
{
    public function testConstructorNoArgs()
    {
        $filter = new Filter(Filter::TYPE_TEXT);
        $this->assertEquals('', $filter->getName());
        $this->assertEquals('', $filter->getLabel());
        $this->assertEquals('', $filter->getFilterMethod());
        $this->assertEquals(null, $filter->getValue());
        $this->assertFalse($filter->isDefaultMethod());
    }

    /**
     * @depends testConstructorNoArgs
     */
    public function testConstructorTypicalUse()
    {
        $filter = new Filter(Filter::TYPE_TEXT, 'myName', 'myLabel');
        $this->assertEquals('myName', $filter->getName());
        $this->assertEquals('myLabel', $filter->getLabel());
        $this->assertEquals('filterByMyName', $filter->getFilterMethod());
        $this->assertEquals(null, $filter->getValue());
        $this->assertTrue($filter->isDefaultMethod());
    }

    /**
     * @depends testConstructorTypicalUse
     */
    public function testGettersAndSetters()
    {
        $value = 'Lorem';
        $values = ['a', 'b'];
        $filter = new Filter(Filter::TYPE_TEXT, 'myName', 'myLabel', 'myMethod');
        $this->assertEquals('myName', $filter->getName());
        $this->assertEquals('myLabel', $filter->getLabel());
        $this->assertEquals('myMethod', $filter->getFilterMethod());
        $this->assertEquals(null, $filter->getValue());
        $this->assertFalse($filter->isDefaultMethod());

        $filter
            ->setName('myName')
            ->setLabel('myLabel')
            ->setFilterMethod('myMethod')
            ->setValue($value)
            ->setValues($values);
        $this->assertEquals('myName', $filter->getName());
        $this->assertEquals('myLabel', $filter->getLabel());
        $this->assertEquals('myMethod', $filter->getFilterMethod());
        $this->assertEquals($value, $filter->getValue());
        $this->assertArraySubset($filter->getValues(), $values, true);
        $this->assertFalse($filter->isDefaultMethod());
    }

    /**
     * @depends testConstructorTypicalUse
     */
    public function testTypeClassName()
    {
        $filter = new Filter(Filter::TYPE_TEXT);
        $this->assertEquals(Filter::TYPE_TEXT, $filter->getType(false));
        $this->assertEquals(TextType::class, $filter->getType());

        $filter->setType(Filter::TYPE_CHECKBOX);
        $this->assertEquals(Filter::TYPE_CHECKBOX, $filter->getType(false));
        $this->assertEquals(CheckboxType::class, $filter->getType());
        $filter->setValues(['d', 's']);
        $this->assertEquals(ChoiceType::class, $filter->getType());

        $filter->setType(Filter::TYPE_RADIO);
        $filter->setValues([]);
        $this->assertEquals(Filter::TYPE_RADIO, $filter->getType(false));
        $this->assertEquals(RadioType::class, $filter->getType());
        $filter->setValues(['d', 's']);
        $this->assertEquals(ChoiceType::class, $filter->getType());

        $filter->setType(Filter::TYPE_SELECT);
        $filter->setValues(['a', 'b']);
        $this->assertEquals(Filter::TYPE_SELECT, $filter->getType(false));
        $this->assertEquals(ChoiceType::class, $filter->getType());

        $filter->setType(Filter::TYPE_MULTISELECT);
        $filter->setValues(['a', 'b']);
        $this->assertEquals(Filter::TYPE_MULTISELECT, $filter->getType(false));
        $this->assertEquals(ChoiceType::class, $filter->getType());
    }

    /**
     * @depends testTypeClassName
     */
    public function testInvalidType()
    {
        $this->expectException(FilterException::class);
        new Filter('invalid');
    }

    /**
     * @depends testTypeClassName
     */
    public function testMissingValues()
    {
        $filter = new Filter(Filter::TYPE_SELECT);
        $lister = $this->getNewLister();
        $this->expectException(FilterException::class);
        $filter->apply($lister);
    }

    /**
     * @depends testTypeClassName
     */
    public function testMissingMethod()
    {
        $filter = new Filter(Filter::TYPE_SELECT, 'invalidMethod');
        $lister = $this->getNewLister();
        $this->expectException(FilterException::class);
        $filter->apply($lister);
    }

    /**
     * @depends testTypeClassName
     */
    public function testListerNotReady()
    {
        $filter = new Filter(Filter::TYPE_TEXT);
        $this->expectException(FilterException::class);
        $filter->apply(new Lister());
    }

    /**
     * @depends testConstructorTypicalUse
     * @depends testListerNotReady
     */
    public function testApply()
    {
        $filter = new Filter(Filter::TYPE_TEXT, 'firstName', 'myFilter');
        $filter->setValue('ASC');

        $lister = $this->getNewLister();
        $filter->apply($lister);
        $query = $lister->getQuery(false);
        $crit = $query->getCriterion(AuthorTableMap::COL_FIRST_NAME);
        $this->assertInstanceOf(BasicCriterion::class, $crit);

        $query->clear();
        $filter->setFilterMethod('filterByFirstName')
            ->apply($lister);
        $crit = $query->getCriterion(AuthorTableMap::COL_FIRST_NAME);
        $this->assertInstanceOf(BasicCriterion::class, $crit);
    }

    private function getNewLister()
    {
        $lister = new Lister('', '');
        return $lister->setQuery(AuthorQuery::create());
    }
}