<?php

namespace Dszczer\ListerBundle\Util;

/**
 * Class HelperTest
 * @package Dszczer\ListerBundle\Util
 */
class HelperTest extends \PHPUnit_Framework_TestCase
{
    public function testUuidv4()
    {
        $uniq = Helper::uuidv4();
        $uniq2 = Helper::uuidv4();
        $this->assertRegExp("/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/", $uniq);
        $this->assertNotEquals($uniq, $uniq2);
    }

    public function testCamelize()
    {
        $underscore = "some_underscore_string";
        $malformed = "This-is_malformedString";
        $camelized = "thisShouldNotBeChanged";

        $this->assertEquals("someUnderscoreString", Helper::camelize($underscore));
        $this->assertEquals("thisIs_malformedString", Helper::camelize($malformed, '-'));
        $this->assertEquals("this-isMalformedString", Helper::camelize($malformed));
        $this->assertEquals("thisShouldNotBeChanged", Helper::camelize($camelized));
    }

    public function testFixTwigTemplatePath()
    {
        $symfonyPath = 'FooBarBundle:Static:twigTemplate.html.twig';
        $twigPath = 'FooBar/Static/twigTemplate.html.twig';

        $this->assertEquals($twigPath, Helper::fixTwigTemplatePath($symfonyPath));
        $this->assertEquals($twigPath, Helper::fixTwigTemplatePath($twigPath));
    }
}