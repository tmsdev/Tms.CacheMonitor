<?php
namespace Tms\CacheMonitor\Domain\Repository;

/*
 * This file is part of the Tms.CacheMonitor package.
 */

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Persistence\QueryInterface;
use Neos\Flow\Persistence\Doctrine\Repository;

/**
 * @Flow\Scope("singleton")
 */
class FullpagecacheActivityRepository extends Repository
{
    /**
     * @var array
     */
    protected $defaultOrderings = array('dateCreated' => QueryInterface::ORDER_DESCENDING);

}
