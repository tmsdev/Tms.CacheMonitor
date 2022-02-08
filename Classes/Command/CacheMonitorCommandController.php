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
        $disallowedCookieParams = [];
        $disallowedQueryParams = [];

        /** @var $activity FullpagecacheActivity */
        foreach ($this->fullpagecacheActivityRepository->findAll() as $activity) {
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

        // Map and sort cookie params
        $disallowedCookieParams = array_map(function ($k, $v) { return [$k, $v]; }, array_keys($disallowedCookieParams), $disallowedCookieParams);
        usort($disallowedCookieParams, function ($i1, $i2) { return $i2[1] <=> $i1[1]; });

        // Map and sort query params
        $disallowedQueryParams = array_map(function ($k, $v) { return [$k, $v]; }, array_keys($disallowedQueryParams), $disallowedQueryParams);
        usort($disallowedQueryParams, function ($i1, $i2) { return $i2[1] <=> $i1[1]; });

        // Output results
        $this->outputLine();
        foreach ($this->fullpagecacheActivityRepository->groupByCacheInfo() as $entry) {
            $this->outputLine(sprintf('<info>%s: %s</info>', $entry['cacheInfo'], $entry['count']) . (!$this->fullPageCacheLogger[strtolower($entry['cacheInfo'])] ? ' (currently disabled)' : ''));
        }
        $this->outputLine();
        $this->output->outputTable($disallowedCookieParams, ['Disallowed cookie names', 'Count']);
        $this->outputLine();
        $this->output->outputTable($disallowedQueryParams, ['Disallowed query strings', 'Count']);
        $this->outputLine();
    }

    /**
     * Search for cookie or query parameters and return related URIs
     *
     * @param string $searchTerm
     * @return void
     */
    public function searchCommand($searchTerm)
    {
        $urisCookieParams = [];
        $urisQueryParams = [];

        /** @var $activity FullpagecacheActivity */
        foreach ($this->fullpagecacheActivityRepository->findAll() as $activity) {
            if (in_array($searchTerm, $activity->getDisallowedCookieParams()))
                $urisCookieParams[] = $activity->getUri();
            if (in_array($searchTerm, $activity->getDisallowedQueryParams()))
                $urisQueryParams[] = $activity->getUri();
        }

        $this->outputLine();
        $this->outputLine(sprintf('<comment>Found %s entries for "%s" in disallowed cookie params...</comment>', count($urisCookieParams), $searchTerm));
        foreach ($urisCookieParams as $uri)
            $this->outputLine($uri);

        $this->outputLine();
        $this->outputLine(sprintf('<comment>Found %s entries for "%s" in disallowed query params...</comment>', count($urisQueryParams), $searchTerm));
        foreach ($urisQueryParams as $uri)
            $this->outputLine($uri);
        $this->outputLine();
    }

    /**
     * Breakdown of cache info and uris
     *
     * @return void
     */
    public function urisCommand()
    {
        $cacheInfos = $this->fullpagecacheActivityRepository->groupByCacheInfo();
        $cacheInfos = array_map(function ($i) { return sprintf('%s (%s)', $i['cacheInfo'], $i['count']); }, $cacheInfos);

        $cacheInfo = $this->output->select('<comment>Filter URIs by cache info:</comment>', $cacheInfos);
        $uris = $this->fullpagecacheActivityRepository->groupByUri(explode(' ', $cacheInfo)[0]);

        $uris = array_map(function ($i) {
            $maximumChars = 120;
            $croppedUri = substr($i['uri'], 0, $maximumChars);
            if (strlen($i['uri']) > $maximumChars)
                $croppedUri .= '[...]';
            return [
                'uri' => sprintf('<href=%s>%s</>', $i['uri'], $croppedUri),
                'count' => $i['count']
            ];
        }, $uris);
        $this->output->outputTable($uris, ['URI', 'Count']);
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
