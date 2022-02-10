<?php
namespace Tms\CacheMonitor\Domain\Repository;

/*
 * This file is part of the Tms.CacheMonitor package.
 */

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Persistence\Doctrine\Repository;
use Neos\Flow\Persistence\QueryInterface;
use Tms\CacheMonitor\Domain\Model\FullpagecacheActivity;

/**
 * @Flow\Scope("singleton")
 */
class FullpagecacheActivityRepository extends Repository
{
    /**
     * @var array
     */
    protected $defaultOrderings = array('dateCreated' => QueryInterface::ORDER_DESCENDING);

    /**
     * Group by cache info
     *
     * @return array
     */
    public function groupByCacheInfo(): array
    {
        $query = $this->createQuery();
        $query = $query->getQueryBuilder()
            ->select('e.cacheInfo AS cacheInfo, COUNT(e.cacheInfo) AS count')
            ->groupBy('cacheInfo')
            ->orderBy('count', 'DESC');
        return $query->getQuery()->execute();
    }

    /**
     * Group by uri
     *
     * @param string $cacheInfo
     * @return array
     */
    public function groupByUri($cacheInfo): array
    {
        /** @var \Doctrine\ORM\QueryBuilder $queryBuilder */
        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder
            ->select('e.uri AS uri, COUNT(e.cacheInfo) AS count')
            ->from(FullpagecacheActivity::class, 'e')
            ->where('e.cacheInfo = :cacheInfo')
            ->setParameter('cacheInfo', $cacheInfo)
            ->orderBy('count', 'DESC')
            ->addOrderBy('uri')
            ->groupBy('uri');
        return $queryBuilder->getQuery()->getResult();
    }
}
