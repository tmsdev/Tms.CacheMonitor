<?php
namespace Tms\CacheMonitor\Command;

/*
 * This file is part of the Tms.CacheMonitor package.
 */

use Neos\Flow\Annotations as Flow;
use Tms\CacheMonitor\Domain\Model\FullpagecacheActivity;
use Tms\CacheMonitor\Domain\Repository\FullpagecacheActivityRepository;

/**
 * @Flow\Scope("singleton")
 */
class CacheMonitorCommandController extends \Neos\Flow\Cli\CommandController
{
    /**
     * @var array
     * @Flow\InjectConfiguration(package="Tms.CacheMonitor", path="fullPageCacheLogger")
     */
    protected $fullPageCacheLogger;

    /**
     * @Flow\Inject
     * @var FullpagecacheActivityRepository
     */
    protected $fullpagecacheActivityRepository;

    /**
     * Summarize cache monitor logs
     *
     * @return void
     */
    public function infoCommand()
    {
        $activities = $this->fullpagecacheActivityRepository->findAll();

        $cacheInfoSummary = [
            'HIT' => 0,
            'MISS' => 0,
            'SKIP' => 0
        ];
        $disallowedCookieParams = [];
        $disallowedQueryParams = [];

        /** @var $activity FullpagecacheActivity */
        foreach ($activities as $activity) {
            $cacheInfoSummary[$activity->getCacheInfo()]++;
            foreach ($activity->getDisallowedCookieParams() as $item) {
                if (isset($disallowedCookieParams[$item]))
                    $disallowedCookieParams[$item]++;
                else
                    $disallowedCookieParams[$item] = 1;
            }
            foreach ($activity->getDisallowedQueryParams() as $item) {
                if (isset($disallowedQueryParams[$item]))
                    $disallowedQueryParams[$item]++;
                else
                    $disallowedQueryParams[$item] = 1;
            }
        }
        $disallowedCookieParams = array_map(function ($k, $v) { return [$k, $v]; }, array_keys($disallowedCookieParams), $disallowedCookieParams);
        $disallowedQueryParams = array_map(function ($k, $v) { return [$k, $v]; }, array_keys($disallowedQueryParams), $disallowedQueryParams);
        rsort($disallowedCookieParams);
        rsort($disallowedQueryParams);

        $this->outputLine();
        foreach ($cacheInfoSummary as $cacheInfo => $count) {
            $this->outputLine(sprintf('<info>%s: %s</info>', $cacheInfo, $count) . (!$this->fullPageCacheLogger[strtolower($cacheInfo)] ? ' (currently disabled)' : ''));
        }
        $this->outputLine();
        $this->output->outputTable($disallowedCookieParams, ['Disallowed cookie names', 'Count']);
        $this->outputLine();
        $this->output->outputTable($disallowedQueryParams, ['Disallowed query strings', 'Count']);
        $this->outputLine();
    }

    /**
     * Show list of requested URIs filtered by included cookie or query parameters
     *
     * @param string $filter
     * @return void
     */
    public function urisCommand($filter)
    {
        $activities = $this->fullpagecacheActivityRepository->findAll();
        $urisCookieParams = [];
        $urisQueryParams = [];

        /** @var $activity FullpagecacheActivity */
        foreach ($activities as $activity) {
            if (in_array($filter, $activity->getDisallowedCookieParams()))
                $urisCookieParams[] = $activity->getUri();
            if (in_array($filter, $activity->getDisallowedQueryParams()))
                $urisQueryParams[] = $activity->getUri();
        }

        $this->outputLine();
        $this->outputLine(sprintf('<comment>Found %s requested URIs for "%s" in disallowed cookie params...</comment>', count($urisCookieParams), $filter));
        foreach ($urisCookieParams as $uri)
            $this->outputLine($uri);

        $this->outputLine();
        $this->outputLine(sprintf('<comment>Found %s requested URIs for "%s" in disallowed query params...</comment>', count($urisQueryParams), $filter));
        foreach ($urisQueryParams as $uri)
            $this->outputLine($uri);
        $this->outputLine();
    }

    /**
     * Clear log entries
     *
     * @return void
     */
    public function clearCommand()
    {
        $count = $this->fullpagecacheActivityRepository->countAll();
        $this->fullpagecacheActivityRepository->removeAll();
        $this->outputLine(sprintf('<info>Removed %s cache monitor entries.</info>', $count));
    }
}
