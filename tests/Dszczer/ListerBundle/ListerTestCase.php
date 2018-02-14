<?php

namespace Dszczer\ListerBundle;

use function Dszczer\ListerBundle\TestInventory\createBooks;
use function Dszczer\ListerBundle\TestInventory\createAuthors;
use Propel\Runtime\Propel;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Trait CreateKernelTrait
 */
abstract class ListerTestCase extends WebTestCase
{
    /** @var \AppKernel */
    protected static $myKernel;

    /** @var \Propel\Runtime\Connection\PropelPDO */
    protected static $propelConnection;

    /**
     * @param $amount
     * @throws \Propel\Runtime\Exception\PropelException
     */
    protected static function createBooks($amount)
    {
        createBooks($amount, static::$propelConnection);
    }

    /**
     * @param $amount
     * @throws \Propel\Runtime\Exception\PropelException
     */
    protected static function createAuthors($amount)
    {
        createAuthors($amount, static::$propelConnection);
    }

    /**
     * @return \Symfony\Component\HttpKernel\KernelInterface
     */
    protected static function createTestKernel()
    {
        $kernel = static::createKernel(['environment' => 'test', 'debug' => 'true']);
        $kernel->boot();

        return $kernel;
    }

    protected static function deleteAllBooks()
    {
        static::$propelConnection->exec("DELETE FROM book");
    }

    protected static function deleteAllAuthors()
    {
        static::$propelConnection->exec("DELETE FROM author");
    }

    /**
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public static function setUpBeforeClass()
    {
        static::$myKernel = static::createTestKernel();
        Propel::init(ROOTDIR . '/Propel/Runtime/config.php');
        static::$propelConnection = Propel::getConnection();
        static::deleteAllBooks();
        static::deleteAllAuthors();
        static::createAuthors(50);
        static::createBooks(100);
    }

    public static function tearDownAfterClass()
    {
        static::$myKernel = null;
    }
}