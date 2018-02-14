<?php
/**
 * @author Damian Szczerbiński
 * @copyright Copyright © 2018. All rights reserved.
 */

namespace Dszczer\ListerBundle\Entity;

/**
 * Class Book
 */
class Book
{
    /** @var int */
    protected $id;

    /** @var Author */
    protected $author;

    /** @var string */
    protected $title;

    /** @var string */
    protected $isbn;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return Book
     */
    public function setId(int $id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return Author
     */
    public function getAuthor()
    {
        return $this->author;
    }

    /**
     * @param Author $author
     * @return Book
     */
    public function setAuthor(Author $author)
    {
        $this->author = $author;
        return $this;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @param string $title
     * @return Book
     */
    public function setTitle(string $title)
    {
        $this->title = $title;
        return $this;
    }

    /**
     * @return string
     */
    public function getIsbn()
    {
        return $this->isbn;
    }

    /**
     * @param string $isbn
     * @return Book
     */
    public function setIsbn(string $isbn)
    {
        $this->isbn = $isbn;
        return $this;
    }


}