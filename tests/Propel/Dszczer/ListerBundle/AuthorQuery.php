<?php

namespace Dszczer\ListerBundle;

use Dszczer\ListerBundle\Base\AuthorQuery as BaseAuthorQuery;
use Propel\Runtime\Connection\ConnectionInterface;

/**
 * Skeleton subclass for performing query and update operations on the 'author' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 */
class AuthorQuery extends BaseAuthorQuery
{
    /**
     * @param ConnectionInterface|null $con
     * @return Author
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public static function getRandom(ConnectionInterface $con = null)
    {
        $pks = self::create()->select('Id')->find()->toArray();
        return self::create()
            ->filterById($pks[array_rand($pks)])
            ->findOne($con);
    }
}
