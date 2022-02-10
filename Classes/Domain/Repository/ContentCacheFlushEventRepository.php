<?php
namespace Tms\CacheMonitor\Domain\Repository;

/*
 * This file is part of the Tms.CacheMonitor package.
 */

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Persistence\Doctrine\Repository;
use Neos\Flow\Persistence\QueryInterface;

/**
 * @Flow\Scope("singleton")
 */
class ContentCacheFlushEventRepository extends Repository
{
    /**
     * @var array
     */
    protected $defaultOrderings = array('dateCreated' => QueryInterface::ORDER_ASCENDING);
}
