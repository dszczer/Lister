<?php

namespace Dszczer\ListerBundle\Lister;

use Dszczer\ListerBundle\BookQuery;
use Dszczer\ListerBundle\ListerTestCase;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Collection\ArrayCollection;
use Propel\Runtime\Collection\ObjectCollection;

/**
 * Class PropelModelPagerTest
 * @package Dszczer\ListerBundle\Lister
 */
class PagerHelperTest extends ListerTestCase
{
    /**
     * @param $maxPerPage
     * @param int $page
     * @return PagerHelper
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    protected function getPager(int $maxPerPage, int $page = 1)
    {
        $pager = new PagerHelper(BookQuery::create(), $maxPerPage);
        $pager->setPage($page);
        $pager->init(static::$propelConnection);

        return $pager;
    }

    /**
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function testHaveToPaginate()
    {
        static::deleteAllBooks();
        $this->assertEquals(false, $this->getPager(0)->haveToPaginate(), 'haveToPaginate() returns false when there is no result');
        static::createBooks(5);
        $this->assertEquals(false, $this->getPager(0)->haveToPaginate(), 'haveToPaginate() returns false when the maxPerPage is null');
        $this->assertEquals(true, $this->getPager(2)->haveToPaginate(), 'haveToPaginate() returns true when the maxPerPage is less than the number of results');
        $this->assertEquals(false, $this->getPager(6)->haveToPaginate(), 'haveToPaginate() returns false when the maxPerPage is greater than the number of results');
        $this->assertEquals(false, $this->getPager(5)->haveToPaginate(), 'haveToPaginate() returns false when the maxPerPage is equal to the number of results');
    }

    /**
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function testGetNbResults()
    {
        static::deleteAllBooks();
        $pager = $this->getPager(4, 1);
        $this->assertEquals(0, $pager->getNbResults(), 'getNbResults() returns 0 when there are no results');
        static::createBooks(5);
        $pager = $this->getPager(4, 1);
        $this->assertEquals(5, $pager->getNbResults(), 'getNbResults() returns the total number of results');
        $pager = $this->getPager(2, 1);
        $this->assertEquals(5, $pager->getNbResults(), 'getNbResults() returns the total number of results');
        $pager = $this->getPager(2, 2);
        $this->assertEquals(5, $pager->getNbResults(), 'getNbResults() returns the total number of results');
        $pager = $this->getPager(7, 6);
        $this->assertEquals(5, $pager->getNbResults(), 'getNbResults() returns the total number of results');
        $pager = $this->getPager(0, 0);
        $this->assertEquals(5, $pager->getNbResults(), 'getNbResults() returns the total number of results');
    }

    /**
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function testGetResults()
    {
        static::deleteAllBooks();
        static::createBooks(5);
        $pager = $this->getPager(4, 1);
        $this->assertTrue($pager->getResults() instanceof ObjectCollection, 'getResults() returns a PropelObjectCollection');
        $this->assertEquals(4, count($pager->getResults()), 'getResults() returns at most $maxPerPage results');
        $pager = $this->getPager(4, 2);
        $this->assertEquals(1, count($pager->getResults()), 'getResults() returns the remaining results when in the last page');
        $pager = $this->getPager(4, 3);
        $this->assertEquals(1, count($pager->getResults()), 'getResults() returns the results of the last page when called on nonexistent pages');
    }

    /**
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function testGetResultsRespectsFormatter()
    {
        static::deleteAllBooks();
        static::createBooks(5);
        $query = BookQuery::create();
        $query->setFormatter(ModelCriteria::FORMAT_ARRAY);
        $pager = new PagerHelper($query, 4);
        $pager->setPage(1);
        $pager->init();
        $this->assertTrue($pager->getResults() instanceof ArrayCollection, 'getResults() returns a PropelArrayCollection if the query uses array hydration');
    }

    /**
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function testGetIterator()
    {
        static::deleteAllBooks();
        static::createBooks(5);

        $pager = $this->getPager(4, 1);
        $i = 0;
        foreach ($pager as $book) {
            $i++;
        }
        $this->assertEquals(4, $i, 'getIterator() uses the results collection');
    }

    /**
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function testIterateTwice()
    {
        static::deleteAllBooks();
        static::createBooks(5);
        $pager = $this->getPager(4, 1);

        $i = 0;
        foreach ($pager as $book) {
            $i++;
        }
        $this->assertEquals(4, $i, 'getIterator() uses the results collection');

        $i = 0;
        foreach ($pager as $book) {
            $i++;
        }
        $this->assertEquals(4, $i, 'getIterator() can be called several times');
    }

    /**
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function testSetPage()
    {
        static::deleteAllBooks();
        static::createBooks(5);
        $pager = $this->getPager(2, 2);
        $i = 2;
        foreach ($pager as $book) {
            $i++;
        }
        $this->assertEquals(4, $i, 'setPage() doesn\'t change the page count');
    }

    /**
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function testIsFirstPage()
    {
        static::deleteAllBooks();
        static::createBooks(5);
        $pager = $this->getPager(4, 1);
        $this->assertTrue($pager->isFirstPage(), 'isFirstPage() returns true on the first page');
        $pager = $this->getPager(4, 2);
        $this->assertFalse($pager->isFirstPage(), 'isFirstPage() returns false when not on the first page');
    }

    /**
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function testIsLastPage()
    {
        static::deleteAllBooks();
        static::createBooks(5);
        $pager = $this->getPager(4, 1);
        $this->assertFalse($pager->isLastPage(), 'isLastPage() returns false when not on the last page');
        $pager = $this->getPager(4, 2);
        $this->assertTrue($pager->isLastPage(), 'isLastPage() returns true on the last page');
    }

    /**
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function testGetLastPage()
    {
        static::deleteAllBooks();
        static::createBooks(5);
        $pager = $this->getPager(4, 1);
        $this->assertEquals(2, $pager->getLastPage(), 'getLastPage() returns the last page number');
        $this->assertInternalType('integer', $pager->getLastPage(), 'getLastPage() returns an integer');
    }

    /**
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function testIsEmptyIsTrueOnEmptyPagers()
    {
        static::deleteAllBooks();
        $pager = $this->getPager(4, 1);
        $this->assertTrue($pager->isEmpty());
    }

    /**
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function testIsEmptyIsFalseOnNonEmptyPagers()
    {
        static::deleteAllBooks();
        static::createBooks(1);
        $pager = $this->getPager(4, 1);
        $this->assertFalse($pager->isEmpty());
    }

    /**
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function testCountableInterface()
    {
        static::deleteAllBooks();
        $pager = $this->getPager(10);
        $this->assertCount(0, $pager);

        static::createBooks(15);
        $pager = $this->getPager(10);
        $this->assertCount(10, $pager);

        $pager = $this->getPager(10, 2);
        $this->assertCount(5, $pager);
    }

    /**
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function testZeroOnNoResult()
    {
        static::deleteAllBooks();
        $pager = $this->getPager(1, 100);
        $this->assertEquals(0, $pager->getNbResults());
        $this->assertEquals(0, $pager->getPage());
        $this->assertEquals(0, $pager->getFirstPage());
        $this->assertEquals(0, $pager->getLastPage());
    }

    /**
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function testCallIteratorMethods()
    {
        static::deleteAllBooks();
        static::createBooks(5);
        $pager = $this->getPager(10);
        $methods = ['getPosition', 'isFirst', 'isLast', 'isOdd', 'isEven'];
        $it = $pager->getIterator();
        foreach ($it as $item) {
            foreach ($methods as $method) {
                $this->assertNotNull(
                    $it->$method(),
                    $method . '() returns a non-null value'
                );
            }
        }
    }

}
