<?php
namespace Tms\CacheMonitor\Aspects;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\AOP\JoinPointInterface;
use Neos\Flow\Cache\CacheManager;
use Neos\Flow\Persistence\PersistenceManagerInterface;
use Neos\Utility\ObjectAccess;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Tms\CacheMonitor\Domain\Model\ContentCacheFlushEvent;
use Tms\CacheMonitor\Domain\Model\FullpagecacheActivity;
use Tms\CacheMonitor\Domain\Repository\ContentCacheFlushEventRepository;
use Tms\CacheMonitor\Domain\Repository\FullpagecacheActivityRepository;

/**
 * @Flow\Aspect
 */
class LoggingAspect
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
     * @var array
     * @Flow\InjectConfiguration(package="Flowpack.FullPageCache", path="request.queryParams.allow")
     */
    protected $allowedQueryParams;

    /**
     * @var array
     * @Flow\InjectConfiguration(package="Flowpack.FullPageCache", path="request.queryParams.ignore")
     */
    protected $ignoredQueryParams;

    /**
     * @var array
     * @Flow\InjectConfiguration(package="Flowpack.FullPageCache", path="request.cookieParams.ignore")
     */
    protected $ignoredCookieParams;

    /**
     * @Flow\Inject
     * @var PersistenceManagerInterface
     */
    protected $persistenceManager;

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
     * Log Flowpack.FullPageCache activities
     *
     * @Flow\After("method(Flowpack\FullPageCache\Middleware\RequestCacheMiddleware->process())")
     * @param JoinPointInterface $joinPoint
     */
    public function fullPageCacheLogger(JoinPointInterface $joinPoint): void
    {
        if ($this->fullPageCacheSettings) {
            /** @var  $request RequestInterface */
            $request = $joinPoint->getMethodArgument('request');

            /** @var  $response ResponseInterface */
            $response = $joinPoint->getResult();

            if (
                in_array(strtoupper($request->getMethod()), ['GET', 'HEAD']) &&
                $response instanceof ResponseInterface &&
                $response->hasHeader('X-FullPageCache-Info'))
            {
                $fullPageCacheInfo = $response->getHeader('X-FullPageCache-Info')[0];
                $requestedUri = $request->getUri();

                $activity = new FullpagecacheActivity();
                $disallowedCookieParams = [];
                $disallowedQueryParams = [];

                if ($this->fullPageCacheSettings['skip'] && $this->str_starts_with($fullPageCacheInfo, 'SKIP')) {
                    $activity->setCacheInfo('SKIP');
                    if ($this->fullPageCacheSettings['logDisallowedCookieParams'] === true) {
                        $requestCookieParams = $request->getCookieParams();
                        foreach ($requestCookieParams as $key => $value) {
                            if (!in_array($key, $this->ignoredCookieParams)) {
                                $disallowedCookieParams[] = $key;
                            }
                        }
                    }
                    if ($this->fullPageCacheSettings['logDisallowedQueryParams'] === true) {
                        $requestQueryParams = $request->getQueryParams();
                        $disallowedQueryParams = [];
                        foreach ($requestQueryParams as $key => $value) {
                            if (
                                !in_array($key, $this->allowedQueryParams) &&
                                !in_array($key, $this->ignoredQueryParams)
                            ) {
                                $disallowedQueryParams[] = $key;
                            }
                        }
                    }
                }
                if ($this->fullPageCacheSettings['miss'] && $this->str_starts_with($fullPageCacheInfo, 'MISS')) {
                    $activity->setCacheInfo('MISS');
                }
                if ($this->fullPageCacheSettings['hit'] && $this->str_starts_with($fullPageCacheInfo, 'HIT')) {
                    $activity->setCacheInfo('HIT');
                }

                if ($activity->getCacheInfo() !== null) {
                    $activity->setUri($requestedUri);
                    $activity->setDisallowedCookieParams($disallowedCookieParams);
                    $activity->setDisallowedQueryParams($disallowedQueryParams);

                    $this->fullpagecacheActivityRepository->add($activity);
                    $this->persistenceManager->persistAll();
                }
            }
        }
    }

    /**
     * @Flow\Before("method(Neos\Neos\Fusion\Cache\ContentCacheFlusher->shutdownObject())")
     * @param JoinPointInterface $joinPoint
     *
     * @throws \Neos\Utility\Exception\PropertyNotAccessibleException
     */
    public function logCacheFlush(JoinPointInterface $joinPoint)
    {
        $object = $joinPoint->getProxy();
        $tagsToFlush = ObjectAccess::getProperty($object, 'tagsToFlush', true);
        $workspacesToFlush = ObjectAccess::getProperty($object, 'workspacesToFlush', true);

        $flushEvent = new ContentCacheFlushEvent();
        $flushEvent->setTagsToFlush($tagsToFlush);
        $flushEvent->setWorkspacesToFlush($workspacesToFlush);

        // Get number of affected entries (by cache identifier) at the time of flushing the cache
        $affectedEntries = [];
        if ($this->contentCacheFlushSettings['cacheIdentifiers']) {
            foreach($tagsToFlush as $tag => $_) {
                foreach($this->contentCacheFlushSettings['cacheIdentifiers'] as $cacheIdentifier) {
                    $cache = $this->cacheManager->getCache($cacheIdentifier['identifier'] ?? $cacheIdentifier);
                    $entries = $cache->getByTag($this->sanitizeTag($tag));
                    $affectedEntries[$tag][$cacheIdentifier['identifier'] ?? $cacheIdentifier] = count($entries);
                }
            }
        }
        $flushEvent->setAffectedEntries($affectedEntries);

        $this->contentCacheFlushEventRepository->add($flushEvent);
        $this->persistenceManager->persistAll();
    }

    /**
     * TODO: Can be replaced by adding a dependency on PHP8+
     *
     * @param $haystack
     * @param $needle
     * @return bool
     */
    private function str_starts_with($haystack, $needle) {
        return strpos($haystack , $needle) === 0;
    }

    /**
     * Sanitizes the given tag for use with the cache framework
     *
     * @param string $tag A tag which possibly contains non-allowed characters, for example "NodeType_Acme.Com:Page"
     * @return string A cleaned up tag, for example "NodeType_Acme_Com-Page"
     */
    protected function sanitizeTag($tag)
    {
        return strtr($tag, '.:', '_-');
    }
}
