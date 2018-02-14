<?php
/**
 * Pager helper class representation.
 * @category Lister
 * @author   Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author   François Zaninotto
 * @author   Damian Szczerbiński
 */

namespace Dszczer\ListerBundle\Lister;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Collection\Collection;
use Propel\Runtime\Connection\ConnectionInterface;

/**
 * Implements a pager based on a Propel ModelCriteria or Doctrine Query
 * The code from this class heavily borrows from PropelModelPager class.
 *
 * @see \Propel\Runtime\Util\PropelModelPager
 * @since 0.9.2
 */
class PagerHelper implements \IteratorAggregate, \Countable
{
    /** @var ModelCriteria|QueryBuilder Query object */
    protected $query;

    /** @var int current page */
    protected $page;

    /** @var int number of item per page */
    protected $maxPerPage;

    /** @var int index of the last page */
    protected $lastPage;

    /** @var int number of item the query return without pagination */
    protected $nbResults;

    /** @var int Current maximum link */
    protected $currentMaxLink;

    /** @var int Maximum record limit */
    protected $maxRecordLimit;

    /** @var Collection|ArrayCollection|array|mixed Result set */
    protected $results;

    /** @var ConnectionInterface Propel Connection */
    protected $con;

    /**
     * PagerHelper constructor.
     *
     * @param ModelCriteria|Query $query
     * @param int $maxPerPage
     */
    public function __construct($query, int $maxPerPage = 10)
    {
        $this->setQuery($query);
        $this->setMaxPerPage($maxPerPage);
        $this->setPage(1);
        $this->setLastPage(1);
        $this->setMaxRecordLimit(false);
        $this->setNbResults(0);

        $this->currentMaxLink = 1;
    }

    /**
     * Set Query object.
     *
     * @param ModelCriteria|Query $query
     * @return PagerHelper
     */
    public function setQuery($query): PagerHelper
    {
        $this->query = $query;

        return $this;
    }

    /**
     * Get Query object.
     *
     * @return QueryBuilder|ModelCriteria
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * Initialize paging.
     *
     * @param ConnectionInterface|null $con
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function init($con = null)
    {
        $this->con = $con;
        $maxRecordLimit = $this->getMaxRecordLimit();
        $hasMaxRecordLimit = false !== $maxRecordLimit;

        $qForCount = clone $this->getQuery();
        if ($this->query instanceof ModelCriteria) {
            $count = $qForCount
                ->offset(0)
                ->limit(-1)
                ->count($this->con);
        } else {
            @list($alias) = $qForCount->getRootAliases();
            /** @var QueryBuilder $qForCount */
            $count = $qForCount->select("COUNT($alias)")
                ->setMaxResults(null)
                ->setFirstResult(null)
                ->getQuery()
                ->getSingleScalarResult();
        }

        $this->setNbResults($hasMaxRecordLimit ? min($count, $maxRecordLimit) : $count);
        $q = $this->getQuery();
        if ($q instanceof ModelCriteria) {
            $q->offset(0)
                ->limit(-1);
        } else {
            $q->setFirstResult(null)
                ->setMaxResults(null);
        }

        if (0 === $this->getPage() || 0 === $this->getMaxPerPage()) {
            $this->setLastPage(0);
        } else {
            $this->setLastPage((int)ceil($this->getNbResults() / $this->getMaxPerPage()));

            $offset = ($this->getPage() - 1) * $this->getMaxPerPage();
            if ($this->query instanceof ModelCriteria) {
                $q->offset($offset);
            } else {
                $q->setFirstResult($offset);
            }

            if ($hasMaxRecordLimit) {
                $maxRecordLimit = $maxRecordLimit - $offset;
                if ($maxRecordLimit > $this->getMaxPerPage()) {
                    if ($this->query instanceof ModelCriteria) {
                        $q->limit($this->getMaxPerPage());
                    } else {
                        $q->setMaxResults($this->getMaxPerPage());
                    }
                } else {
                    if ($this->query instanceof ModelCriteria) {
                        $q->limit($maxRecordLimit);
                    } else {
                        $q->setMaxResults($maxRecordLimit);
                    }
                }
            } else {
                if ($this->query instanceof ModelCriteria) {
                    $q->limit($this->getMaxPerPage());
                } else {
                    $q->setMaxResults($this->getMaxPerPage());
                }
            }
        }
    }

    /**
     * Get the collection of results in the page
     *
     * @return Collection|ArrayCollection A collection of results
     */
    public function getResults()
    {
        if (null === $this->results) {
            $queryKey = method_exists($this->getQuery(), 'getQueryKey') ? $this->getQuery()->getQueryKey() : null;
            if ($queryKey) {
                $newQueryKey = sprintf('%s offset %s limit %s', $queryKey, $this->getQuery()->getOffset(), $this->getQuery()->getLimit());
                $this->getQuery()->setQueryKey($newQueryKey);
            }
            if ($this->query instanceof ModelCriteria) {
                $this->results = $this->getQuery()->find($this->con);
            } else {
                $this->results = $this->getQuery()->getQuery()->getResult();
            }
        }

        return $this->results;
    }

    /**
     * Get current maximum link.
     *
     * @return int
     */
    public function getCurrentMaxLink(): int
    {
        return $this->currentMaxLink;
    }

    /**
     * Get maximum record limit.
     *
     * @return int|bool
     */
    public function getMaxRecordLimit()
    {
        return $this->maxRecordLimit;
    }

    /**
     * Set maximum record link.
     *
     * @param int|bool $limit
     * @return PagerHelper
     */
    public function setMaxRecordLimit($limit): PagerHelper
    {
        $this->maxRecordLimit = $limit;

        return $this;
    }

    /**
     * Get array of neighbouring links for current page.
     *
     * @param int $nbLinks
     * @return array
     */
    public function getLinks(int $nbLinks = 5): array
    {
        $links = [];
        $tmp = $this->page - floor($nbLinks / 2);
        $check = $this->lastPage - $nbLinks + 1;
        $limit = ($check > 0) ? $check : 1;
        $begin = ($tmp > 0) ? (($tmp > $limit) ? $limit : $tmp) : 1;

        $i = (int)$begin;
        while (($i < $begin + $nbLinks) && ($i <= $this->lastPage)) {
            $links[] = $i++;
        }

        $this->currentMaxLink = count($links) ? $links[count($links) - 1] : 1;

        return $links;
    }

    /**
     * Test whether the number of results exceeds the max number of results per page
     *
     * @return bool true if the pager displays only a subset of the results
     */
    public function haveToPaginate(): bool
    {
        return (0 !== $this->getMaxPerPage() && $this->getNbResults() > $this->getMaxPerPage());
    }

    /**
     * Get the index of the first element in the page
     * Returns 1 on the first page, $maxPerPage +1 on the second page, etc
     *
     * @return int
     */
    public function getFirstIndex(): int
    {
        if (0 === $this->page) {
            return 1;
        }

        return ($this->page - 1) * $this->maxPerPage + 1;
    }

    /**
     * Get the index of the last element in the page
     * Always less than or equal to $maxPerPage
     *
     * @return int
     */
    public function getLastIndex(): int
    {
        if (0 === $this->page) {
            return $this->nbResults;
        }

        if (($this->page * $this->maxPerPage) >= $this->nbResults) {
            return $this->nbResults;
        }

        return $this->page * $this->maxPerPage;
    }

    /**
     * Get the total number of results of the query
     * This can be greater than $maxPerPage
     *
     * @return int
     */
    public function getNbResults(): int
    {
        return $this->nbResults;
    }

    /**
     * Set the total number of results of the query
     *
     * @param int $nb
     * @return PagerHelper
     */
    protected function setNbResults(int $nb): PagerHelper
    {
        $this->nbResults = $nb;

        return $this;
    }

    /**
     * Check whether the current page is the first page
     *
     * @return bool true if the current page is the first page
     */
    public function isFirstPage(): bool
    {
        return $this->getPage() === $this->getFirstPage();
    }

    /**
     * Get the number of the first page
     *
     * @return int Always 1
     */
    public function getFirstPage(): int
    {
        return $this->nbResults === 0 ? 0 : 1;
    }

    /**
     * Check whether the current page is the last page
     *
     * @return bool true if the current page is the last page
     */
    public function isLastPage(): bool
    {
        return $this->getPage() === $this->getLastPage();
    }

    /**
     * Get the number of the last page
     *
     * @return int
     */
    public function getLastPage(): int
    {
        return $this->lastPage;
    }

    /**
     * Set the number of the first page
     *
     * @param int $page
     * @return PagerHelper
     */
    protected function setLastPage(int $page): PagerHelper
    {
        $this->lastPage = $page;
        if ($this->getPage() > $page) {
            $this->setPage($page);
        }

        return $this;
    }

    /**
     * Get the number of the current page
     *
     * @return int
     */
    public function getPage(): int
    {
        return $this->page;
    }

    /**
     * Set the number of the current page
     *
     * @param int $page
     * @return PagerHelper
     */
    public function setPage(int $page): PagerHelper
    {
        $this->page = (int)$page;
        if ($this->page <= 0 && $this->nbResults > 0) {
            // set first page, which depends on a maximum set
            $this->page = $this->getMaxPerPage() ? 1 : 0;
        }

        return $this;
    }

    /**
     * Get the number of the next page
     *
     * @return int
     */
    public function getNextPage(): int
    {
        return min($this->getPage() + 1, $this->getLastPage());
    }

    /**
     * Get the number of the previous page
     *
     * @return int
     */
    public function getPreviousPage(): int
    {
        return max($this->getPage() - 1, $this->getFirstPage());
    }

    /**
     * Get the maximum number results per page
     *
     * @return int
     */
    public function getMaxPerPage(): int
    {
        return $this->maxPerPage;
    }

    /**
     * Set the maximum number results per page
     *
     * @param int $max
     * @return PagerHelper
     */
    public function setMaxPerPage(int $max): PagerHelper
    {
        if ($max > 0) {
            $this->maxPerPage = $max;
            if (0 === $this->page) {
                $this->page = 1;
            }
        } elseif (0 === $max) {
            $this->maxPerPage = 0;
            $this->page = 0;
        } else {
            $this->maxPerPage = 1;
            if (0 === $this->page) {
                $this->page = 1;
            }
        }

        return $this;
    }

    /**
     * Check if the collection is empty
     * @see Collection
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        if ($this->getResults() instanceof Collection) {
            return $this->getResults()->isEmpty();
        } else {
            return $this->count() === 0;
        }
    }

    /**
     * Get Collection Iterator
     *
     * @return \Propel\Runtime\Collection\CollectionIterator|\Traversable
     */
    public function getIterator()
    {
        return $this->getResults()->getIterator();
    }

    /**
     * Returns the number of items in the result collection.
     *
     * @see \Countable
     * @return int
     */
    public function count(): int
    {
        return count($this->getResults());
    }

    /**
     * Redirect calls to result set object.
     *
     * @param string $name
     * @param array $params
     * @return mixed
     * @throws \BadMethodCallException
     */
    public function __call(string $name, array $params)
    {
        try {
            return call_user_func_array([$this->getResults(), $name], $params);
        } catch (\BadMethodCallException $exception) {
            throw new \BadMethodCallException('Call to undefined method: ' . $name);
        }
    }

}