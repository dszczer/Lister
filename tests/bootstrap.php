<?php

/**
 * Test environment bootstrap file.
 * @package Dszczer\ListerBundle
 * @author Damian Szczerbiński <dszczer@gmail.com>
 */

// global namespace
namespace {
    /** @const bool OS type, change to false if testing on other platform than Windows */
    define('IS_WINDOWS', true);
    if (IS_WINDOWS) {
        define('ROOTDIR', str_replace(DIRECTORY_SEPARATOR, '/', __DIR__));
    } else {
        define('ROOTDIR', __DIR__);
    }

    // autoloader
    require_once \realpath(ROOTDIR . '/../vendor/autoload.php');
}

//Use different namespace to don't clutter global namespace
namespace Dszczer\ListerBundle\TestInventory {

    use Dszczer\ListerBundle\Author;
    use Dszczer\ListerBundle\AuthorQuery;
    use Dszczer\ListerBundle\Book;
    use Dszczer\ListerBundle\BookQuery;
    use Propel\Runtime\Connection\ConnectionInterface;
    use Propel\Runtime\Propel;

    /**
     * @param array|string[] $strs
     * @return string
     */
    function testGetRandomStr(array $strs): string
    {
        return $strs[array_rand($strs)];
    }

    /**
     * @param int $amount
     * @param ConnectionInterface $conn
     * @throws \Propel\Runtime\Exception\PropelException
     */
    function createAuthors(int $amount, ConnectionInterface $conn)
    {
        $firstNames = ['Ageia', 'Foo', 'Bar', 'Moren', 'Bastet', 'Trevor', 'Camille', 'Gregory', 'John',
            'Emilly', 'Alex'];
        $lastNames = ['Era', 'Idea', 'Watermark', 'Bloom', 'Powerplant', 'Wonder', 'Barcode', 'Quzill', 'Domestic',
            'Smith', 'Bag'];

        for ($i = 1; $i <= $amount; $i++) {
            $a = new Author();
            $a->setFirstName(testGetRandomStr($firstNames))
                ->setLastName(testGetRandomStr($lastNames))
                ->setEmail("example.mail.$i@example.com")
                ->save($conn);
        }
    }

    /**
     * @param int $amount
     * @param ConnectionInterface $conn
     * @throws \Propel\Runtime\Exception\PropelException
     */
    function createBooks(int $amount, ConnectionInterface $conn)
    {
        $words = ['potato', 'umbrella', 'case', 'car', 'group', 'fast', 'local', 'own', 'pollution', 'smart',
            'hook', 'Earth'];

        for ($bi = 1; $bi <= $amount; $bi++) {
            $a = AuthorQuery::getRandom();
            $b = new Book();
            $b->setAuthor($a)
                ->setTitle(
                    testGetRandomStr($words) . ' ' . testGetRandomStr($words) . ' ' . testGetRandomStr($words)
                    . ' ' . testGetRandomStr($words)
                )
                ->setIsbn(mt_rand(19384, 294957))
                ->save($conn);
        }
    }

    /**
     * Database fixture
     */
    $cmdBinDir = sprintf(
        'cd "%s" && %s',
        escapeshellarg(ROOTDIR . '/../vendor/bin'),
        escapeshellcmd('propel' . (IS_WINDOWS ? '.bat' : ''))
    );
    $cmdConfigDir = '--config-dir="' . escapeshellarg(ROOTDIR) . '"';
    exec(sprintf('%s config:convert %s', $cmdBinDir, $cmdConfigDir));
    // init connection
    Propel::init(ROOTDIR . '/Propel/Runtime/config.php');
    $conn = Propel::getConnection();
    // build classes
    exec(sprintf('%s model:build %s', $cmdBinDir, $cmdConfigDir));
    // migrate (create tables)
    exec(sprintf('%s migration:diff %s', $cmdBinDir, $cmdConfigDir));
    exec(sprintf('%s migration:migrate %s', $cmdBinDir, $cmdConfigDir));
    // cleanup after migration
    foreach (glob(ROOTDIR . '/Propel/Migration/*') as $mfile) {
        unlink($mfile);
    }

    // insert test data only if database is empty
    if (AuthorQuery::create()->count($conn) == 0) {
        createAuthors(50, $conn);
    }
    if (BookQuery::create()->count($conn) == 0) {
        createBooks(100, $conn);
    }
}