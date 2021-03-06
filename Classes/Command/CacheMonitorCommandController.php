<?php
namespace Tms\CacheMonitor\Command;

/*
 * This file is part of the Tms.CacheMonitor package.
 */

use Neos\ContentRepository\Domain\Model\NodeData;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Model\Workspace;
use Neos\ContentRepository\Domain\Repository\NodeDataRepository;
use Neos\ContentRepository\Domain\Repository\WorkspaceRepository;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cache\CacheManager;
use Neos\Fusion\Core\Cache\ContentCache;
use Neos\Neos\Domain\Service\ContentContext;
use Neos\Neos\Domain\Service\ContentContextFactory;
use Ramsey\Uuid\Uuid;
use Tms\CacheMonitor\Domain\Model\ContentCacheFlushEvent;
use Tms\CacheMonitor\Domain\Model\FullPageCacheEvent;
use Tms\CacheMonitor\Domain\Repository\ContentCacheFlushEventRepository;
use Tms\CacheMonitor\Domain\Repository\FullPageCacheEventRepository;

/**
 * @Flow\Scope("singleton")
 */
class CacheMonitorCommandController extends \Neos\Flow\Cli\CommandController
{
    /**
     * @var array
     * @Flow\InjectConfiguration(package="Tms.CacheMonitor", path="logCacheFlush")
     */
    protected $cacheFlushSettings;

    /**
     * @var array
     * @Flow\InjectConfiguration(package="Tms.CacheMonitor", path="logFullPageCache")
     */
    protected $fullPageCacheSettings;

    /**
     * @Flow\Inject
     * @var FullPageCacheEventRepository
     */
    protected $fullPageCacheEventRepository;

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
     * @Flow\Inject
     * @var NodeDataRepository
     */
    protected $nodeDataRepository;

    /**
     * @Flow\Inject
     * @var WorkspaceRepository
     */
    protected $workspaceRepository;

    /**
     * @Flow\Inject
     * @var ContentContextFactory
     */
    protected $contentContextFactory;

    /**
     * Summarize cache monitor logs
     *
     * @param boolean $verbose Show all disallowed cookies and query strings. (Default: top 10 seen)
     * @return void
     */
    public function infoCommand($verbose = false)
    {
        $disallowedCookieParams = [];
        $disallowedQueryParams = [];

        /** @var $event FullPageCacheEvent */
        foreach ($this->fullPageCacheEventRepository->findAll() as $event) {
            foreach ($event->getDisallowedCookieParams() as $item) {
                if (isset($disallowedCookieParams[$item]))
                    $disallowedCookieParams[$item]++;
                else
                    $disallowedCookieParams[$item] = 1;
            }
            foreach ($event->getDisallowedQueryParams() as $item) {
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
        foreach ($this->fullPageCacheEventRepository->groupByCacheInfo() as $entry) {
            $this->outputLine(sprintf('<info>%s: %s</info>', $entry['cacheInfo'], $entry['count']) . (!in_array($entry['cacheInfo'], $this->fullPageCacheSettings['cacheInfos']) ? ' (currently disabled)' : ''));
        }
        $this->outputLine();

        if (!$verbose)
            $disallowedCookieParams = array_slice($disallowedCookieParams, 0, 10);
        $this->output->outputTable($disallowedCookieParams, ['Disallowed cookie names', 'Count']);
        $this->outputLine();

        if (!$verbose)
            $disallowedQueryParams = array_slice($disallowedQueryParams, 0, 10);
        $this->output->outputTable($disallowedQueryParams, ['Disallowed query strings', 'Count']);
        $this->outputLine();

        if (!$verbose)
            $this->outputLine('INFO: By default, you will see the top 10 cookies and query strings found. Use "./flow cachemonitor:info --verbose" to see all entries.');
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

        /** @var $event FullPageCacheEvent */
        foreach ($this->fullPageCacheEventRepository->findAll() as $event) {
            if (in_array($searchTerm, $event->getDisallowedCookieParams()))
                $urisCookieParams[] = $event->getUri();
            if (in_array($searchTerm, $event->getDisallowedQueryParams()))
                $urisQueryParams[] = $event->getUri();
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
     * Breakdown of fullpage cache infos and uris
     *
     * @return void
     */
    public function urisCommand()
    {
        $cacheInfos = $this->fullPageCacheEventRepository->groupByCacheInfo();
        $cacheInfos = array_map(function ($i) { return sprintf('%s (%s)', $i['cacheInfo'], $i['count']); }, $cacheInfos);

        $cacheInfo = $this->output->select('<question>Filter URIs by cache info:</question>', $cacheInfos);
        $uris = $this->fullPageCacheEventRepository->groupByUri(explode(' ', $cacheInfo)[0]);

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
     * A content cache flush audit
     *
     * @param boolean $verbose Show flush events and cache tags with no affected entries as well
     * @param string $cacheTag Filter cache flushes by events that include the given cache tag
     * @param integer $threshold Affected entries threshold (use in combination with the "--cache-tag" filter)
     * @return void
     */
    public function flushAuditCommand($verbose = false, $cacheTag = '', $threshold = 0)
    {
        $flushEvents = $this->contentCacheFlushEventRepository->findAll();

        $flushEventChoices = [];
        foreach ($flushEvents->toArray() as $flushEvent) {

            if ($cacheTag &&
                (
                    !isset($flushEvent->getAffectedEntries()[$cacheTag]) ||
                    array_sum($flushEvent->getAffectedEntries()[$cacheTag]) <= $threshold
                )
            )
                continue;

            $countAffectedEntries = 0;
            foreach ($flushEvent->getAffectedEntries() as $affectedEntry) {
                $countAffectedEntries += array_sum($affectedEntry);
            }

            if (!$verbose && $countAffectedEntries === 0)
                continue;

            $flushEventChoices[] = sprintf(
                '[%s] <comment>%s</comment> <question> %s </question> <info>(Tags: %s, Workspaces: %s)</info>',
                $flushEvent->getIdentifier(),
                $flushEvent->getDateCreated()->format('Y-m-d H:i:sP'),
                $countAffectedEntries,
                count($flushEvent->getTagsToFlush()),
                implode(', ', array_keys($flushEvent->getWorkspacesToFlush()))
            );
        }

        if (!$flushEventChoices) {
            $this->outputLine('<info>No cache flush event found.</info>');
            exit;
        }

        $flushEventChoice = $this->output->select('<question>Select a cache flush event:</question>', $flushEventChoices);
        preg_match('#\[(.*?)\]#', $flushEventChoice, $match);
        $flushEventIdentifier = $match[1];

        /** @var ContentCacheFlushEvent $flushEvent */
        $flushEvent = $this->contentCacheFlushEventRepository->findByIdentifier($flushEventIdentifier);

        $rows = [];
        foreach($flushEvent->getTagsToFlush() as $tag => $_) {
            $rows[$tag] = [$tag];
            $totalAffectedEntries = 0;
            foreach($this->cacheFlushSettings['cacheIdentifiers'] as $cacheIdentifier) {
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
        foreach($this->cacheFlushSettings['cacheIdentifiers'] as $cacheIdentifier) {
            array_push($headers, ($cacheIdentifier['name'] ?? $cacheIdentifier['identifier'] ?? $cacheIdentifier));
        }
        $this->output->outputTable($rows, $headers);

        if (!$verbose) {
            $this->outputLine();
            $this->outputLine('INFO: The list is filtered by tags that actually flushed existing cache entries. Use "./flow cachemonitor:flushaudit --verbose" to see all tags.');
        }
    }

    /**
     * Clear cache logs
     *
     * @return void
     */
    public function clearCommand()
    {
        $countContentCacheFlushEvents = $this->contentCacheFlushEventRepository->countAll();
        $this->contentCacheFlushEventRepository->removeAll();
        $this->outputLine(sprintf('<info>Removed %s content cache flush events.</info>', $countContentCacheFlushEvents));

        $countFullPageCacheEvents = $this->fullPageCacheEventRepository->countAll();
        $this->fullPageCacheEventRepository->removeAll();
        $this->outputLine(sprintf('<info>Removed %s fullpage cache events.</info>', $countFullPageCacheEvents));
    }

    /**
     * A cache tag can consist of up to 3 parts, which this flow command will break down for you
     *
     * 1. The prefix: [Everything, Node_, NodeType_, NodeDynamicTag_, DescendantOf_, AssetDynamicTag_]
     * 2. The workspace tag: an MD5 hash of the workspace name wrapped in %...%
     * 3. The nodetype name OR node identifier
     *
     * @param string $cacheTag The cache tag you are interested in (e.g. DescendantOf_%d0dbe915091d400bd8ee7f27f0791303%_a0051fcd-4334-4c6b-ae9d-1faa2d406eaf)
     * @return void
     */
    public function decodeCacheTagCommand($cacheTag)
    {
        $cacheTagParts = explode('_', $cacheTag);

        $cacheTagPrefix = $cacheTagParts[0];
        $workspaceName = null;
        $nodeTypeName = null;
        $nodeIdentifier = null;

        $validCacheTagPrefixes = [
            'Everything',
            'Node',
            'NodeType',
            'NodeDynamicTag',
            'DescendantOf',
            'AssetDynamicTag'
        ];

        if (!in_array($cacheTagPrefix, $validCacheTagPrefixes)) {
            $this->outputLine(sprintf('<error>"%s" is not a valid cache tag prefix.</error>', $cacheTagPrefix));
            return;
        }

        if ($cacheTagParts[0] === ContentCache::TAG_EVERYTHING) {
            $this->outputLine(
                sprintf(
                    '<comment>Cache entries tagged with "%s" will get flushed on every registered node change, independent of the content context. This can happen accidentally if you forgot to add entry tags.</comment>'
                    , ContentCache::TAG_EVERYTHING
                ));
            return;
        }

        // Decode cache tag without workspace tag (context node)
        if (count($cacheTagParts) === 2) {
            if(Uuid::isValid($cacheTagParts[1])) {
                $nodeIdentifier = $cacheTagParts[1];
                $nodeInfo = $this->getNodeInfo($cacheTagParts[1]);
            } else {
                $nodeTypeName = $cacheTagParts[1];
            }
        }

        // Decode cache tag that includes a workspace tag
        if (count($cacheTagParts) === 3) {
            $workspaceName = $this->getWorkspaceNameFromWorkspaceTag($cacheTagParts[1]);
            if(Uuid::isValid($cacheTagParts[2])) {
                $nodeIdentifier = $cacheTagParts[2];
                $nodeInfo = $this->getNodeInfo($cacheTagParts[2], $workspaceName);
            } else {
                $nodeTypeName = $cacheTagParts[2];
            }
        }

        $this->outputLine(sprintf('1. Prefix <question>%s</question>', $cacheTagPrefix));
        $this->outputLine(sprintf('2. Workspace <question>%s</question>', $workspaceName ?? '(not set)'));
        if ($nodeTypeName)
            $this->outputLine(sprintf('3. Nodetype name <question>%s</question>', $nodeTypeName));
        if ($nodeIdentifier) {
            $this->outputLine(sprintf('3. Node identifier <question>%s</question>, search for nodes related to this identifier (and workspace)...', $nodeIdentifier));
            if (empty($nodeInfo))
                $this->outputLine(sprintf('<comment>No nodes found for identifier "%s" in "%s" workspace(s).</comment>', $nodeIdentifier, $workspaceName ?? '(all)'));
            else
                $this->output->outputTable($nodeInfo, array_keys($nodeInfo[0]));
        }
    }

    /**
     * @param string $workspaceTag
     * @return string|null
     */
    protected function getWorkspaceNameFromWorkspaceTag($workspaceTag)
    {
        /** @var Workspace $workspace */
        foreach ($this->workspaceRepository->findAll() as $workspace) {
            if (trim($workspaceTag, '%') === md5($workspace->getName()))
                return $workspace->getName();
        }
        return null;
    }

    /**
     * @param string $identifier
     * @param string $workspaceName
     * @return array
     */
    protected function getNodeInfo($identifier, $workspaceName = null)
    {
        $result = [];
        $query = $this->nodeDataRepository->createQuery();
        $query->matching($query->equals('identifier', $identifier));

        /** @var NodeData $nodeData */
        foreach ($query->execute() as $nodeData) {

            /** @var ContentContext $contentContext */
            $contentContext = $this->contentContextFactory->create([
                'workspaceName' => $nodeData->getWorkspace()->getName(),
                'dimensions' => $nodeData->getDimensions()
            ]);

            $node = $contentContext->getNode($nodeData->getPath());
            if ($node instanceof NodeInterface) {
                if ($workspaceName && $nodeData->getWorkspace()->getName() !== $workspaceName)
                    continue;

                $result[] = [
                    'nodeTypeName' => $nodeData->getNodeType()->getName(),
                    'contextPath' => $nodeData->getContextPath(),
                    'label' => $node->getLabel()
                ];
            }
        }
        return $result;
    }
}
