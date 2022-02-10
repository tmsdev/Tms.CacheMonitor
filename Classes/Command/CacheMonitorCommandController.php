<?php
namespace Tms\CacheMonitor\Command;

/*
 * This file is part of the Tms.CacheMonitor package.
 */

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cache\CacheManager;
use Tms\CacheMonitor\Domain\Model\ContentCacheFlushEvent;
use Tms\CacheMonitor\Domain\Model\FullpagecacheActivity;
use Tms\CacheMonitor\Domain\Repository\ContentCacheFlushEventRepository;
use Tms\CacheMonitor\Domain\Repository\FullpagecacheActivityRepository;

/**
 * @Flow\Scope("singleton")
 */
class CacheMonitorCommandController extends \Neos\Flow\Cli\CommandController
{
    /**
     * @var array
     * @Flow\InjectConfiguration(package="Tms.CacheMonitor", path="logContentCacheFlush")
     */
    protected $contentCacheFlushSettings;

    /**
     * @var array
     * @Flow\InjectConfiguration(package="Tms.CacheMonitor", path="fullPageCacheLogger")
     */
    protected $fullPageCacheSettings;

    /**
     * @Flow\Inject
     * @var FullpagecacheActivityRepository
     */
    protected $fullpagecacheActivityRepository;

    /**
     * @Flow\Inject
     * @var ContentCacheFlushEventRepository
     */
    protected $contentCacheFlushEventRepository;

    /**
     * @Flow\Inject
     * @var CacheManager
     */
    protected $cacheManager;

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
            $this->outputLine(sprintf('<info>%s: %s</info>', $entry['cacheInfo'], $entry['count']) . (!$this->fullPageCacheSettings[strtolower($entry['cacheInfo'])] ? ' (currently disabled)' : ''));
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

        $cacheInfo = $this->output->select('<question>Filter URIs by cache info:</question>', $cacheInfos);
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

    /**
     * A content cache flush audit
     *
     * @param boolean $verbose Show tags with no affected entries as well
     * @return void
     */
    public function flushAuditCommand($verbose = false)
    {
        $flushEvents = $this->contentCacheFlushEventRepository->findAll();

        $flushEventChoices = array_map(function ($e) {
            return sprintf(
                '[%s] <comment>%s</comment> <info>(Tags: %s, Workspaces: %s)</info>',
                $e->getIdentifier(),
                $e->getDateCreated()->format('Y-m-d H:i:sP'),
                count($e->getTagsToFlush()),
                implode(', ', array_keys($e->getWorkspacesToFlush()))
            );
        }, $flushEvents->toArray());

        $flushEventChoice = $this->output->select('<question>Select a cache flush event:</question>', $flushEventChoices);
        preg_match('#\[(.*?)\]#', $flushEventChoice, $match);
        $flushEventIdentifier = $match[1];

        /** @var ContentCacheFlushEvent $flushEvent */
        $flushEvent = $this->contentCacheFlushEventRepository->findByIdentifier($flushEventIdentifier);

        $rows = [];
        foreach($flushEvent->getTagsToFlush() as $tag => $_) {
            $rows[$tag] = [$tag];
            $totalAffectedEntries = 0;
            foreach($this->contentCacheFlushSettings['cacheIdentifiers'] as $cacheIdentifier) {
                $affectedEntries = ($flushEvent->getAffectedEntries()[$tag][$cacheIdentifier['identifier'] ?? $cacheIdentifier]) ?? 0;
                $totalAffectedEntries += $affectedEntries;
                if ($affectedEntries > 0)
                    $affectedEntries = '<question> ' . $affectedEntries . ' </question>';
                else
                    $affectedEntries = ' ' . $affectedEntries . ' ';
                array_push($rows[$tag], $affectedEntries);
            }
            if ($totalAffectedEntries === 0 && $verbose === false)
                unset($rows[$tag]);
        }
        ksort($rows);
        $headers = ['Tag(s) to flush'];
        foreach($this->contentCacheFlushSettings['cacheIdentifiers'] as $cacheIdentifier) {
            array_push($headers, ($cacheIdentifier['name'] ?? $cacheIdentifier['identifier'] ?? $cacheIdentifier));
        }
        $this->output->outputTable($rows, $headers);

        if (!$verbose) {
            $this->outputLine();
            $this->outputLine('INFO: The list is filtered by tags that actually flushed existing cache entries. Use "./flow cachemonitor:flushaudit --verbose" to see all tags.');
        }
    }
}
