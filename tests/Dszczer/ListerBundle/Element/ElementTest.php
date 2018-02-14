<?php

namespace Dszczer\ListerBundle\Element;

use Dszczer\ListerBundle\Author;

/**
 * Class ElementTest
 * @package Dszczer\ListerBundle\Element
 */
class ElementTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @throws ElementException
     */
    public function testConstructorNoArgs()
    {
        $element = new Element();
        $this->assertEquals('', $element->getName());
        $this->assertEquals('', $element->getLabel());
        $this->assertEquals('', $element->getMethod());
        $this->assertFalse($element->isCustom());
        $this->assertEquals(null, $element->getData(true));
    }

    /**
     * @depends testConstructorNoArgs
     * @throws ElementException
     */
    public function testConstructorTypicalUse()
    {
        $element = new Element('myName', 'myLabel');
        $this->assertEquals('myName', $element->getName());
        $this->assertEquals('myLabel', $element->getLabel());
        $this->assertEquals('getMyName', $element->getMethod());
        $this->assertFalse($element->isCustom());
        $this->assertEquals(null, $element->getData(true));
    }

    /**
     * @depends testConstructorTypicalUse
     * @throws ElementException
     */
    public function testInvalidMethod()
    {
        $element = new Element('myName', 'myLabel', '', null, new Author());
        $this->expectException(ElementException::class);
        $element->getData();
    }

    /**
     * @depends testConstructorTypicalUse
     * @throws ElementException
     */
    public function testInvalidCallable()
    {
        $element = new Element('myName', 'myLabel', '', 'some_not_existing_class::callable', new Author());
        $this->expectException(ElementException::class);
        $element->getData();
    }

    /**
     * @depends testInvalidMethod
     * @depends testInvalidCallable
     * @throws ElementException
     */
    public function testGettersAndSetters()
    {
        $dataElement = new Element('myData');
        $element = new Element('myName', 'myLabel', 'myMethod', [$this, 'exampleCallable']);
        $this->assertEquals('myName', $element->getName());
        $this->assertEquals('myLabel', $element->getLabel());
        $this->assertEquals('myMethod', $element->getMethod());
        $this->assertTrue($element->isCustom());
        $this->assertEquals(null, $element->getData(true));
        $this->assertEquals('exampleCallableSuccess', $element->getData());
        is_callable($element->getCallable(), true, $callableName);
        $this->assertEquals('Dszczer\ListerBundle\Element\ElementTest::exampleCallable', $callableName);

        $element
            ->setName('myName')
            ->setLabel('myLabel')
            ->setMethod('getName')
            ->setCallable([$this, 'exampleCallable'])
            ->setData($dataElement);
        $this->assertEquals('myName', $element->getName());
        $this->assertEquals('myLabel', $element->getLabel());
        $this->assertEquals('getName', $element->getMethod());
        $this->assertTrue($element->isCustom());
        $this->assertEquals($dataElement, $element->getData(true));
        $this->assertEquals('exampleCallableSuccess', $element->getData());
        is_callable($element->getCallable(), true, $callableName);
        $this->assertEquals('Dszczer\ListerBundle\Element\ElementTest::exampleCallable', $callableName);
        $element->setCustom(false);
        $this->assertEquals('myData', $element->getData());
    }

    /**
     * @internal For internal testing purposes only.
     * @return string
     */
    public function exampleCallable()
    {
        return 'exampleCallableSuccess';
    }
}