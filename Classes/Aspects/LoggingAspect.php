<?php
namespace Tms\CacheMonitor\Aspects;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\AOP\JoinPointInterface;
use Neos\Flow\Persistence\PersistenceManagerInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Tms\CacheMonitor\Domain\Model\FullpagecacheActivity;
use Tms\CacheMonitor\Domain\Repository\FullpagecacheActivityRepository;

/**
 * @Flow\Aspect
 */
class LoggingAspect
{
    /**
     * @var array
     * @Flow\InjectConfiguration(package="Tms.CacheMonitor", path="fullPageCacheLogger")
     */
    protected $fullPageCacheLogger;

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
     * Log Flowpack.FullPageCache activities
     *
     * @Flow\After("method(Flowpack\FullPageCache\Middleware\RequestCacheMiddleware->process())")
     */
    public function fullPageCacheLogger(JoinPointInterface $joinPoint): void
    {
        if ($this->fullPageCacheLogger) {
            /** @var  $request RequestInterface */
            $request = $joinPoint->getMethodArgument('request');

            /** @var  $response ResponseInterface */
            $response = $joinPoint->getResult();

            if ($response instanceof ResponseInterface && $response->hasHeader('X-FullPageCache-Info')) {
                $fullPageCacheInfo = $response->getHeader('X-FullPageCache-Info')[0];
                $requestedUri = $request->getUri();

                $activity = new FullpagecacheActivity();
                $disallowedCookieParams = [];
                $disallowedQueryParams = [];

                if ($this->fullPageCacheLogger['skip'] && $this->str_starts_with($fullPageCacheInfo, 'SKIP')) {
                    $activity->setCacheInfo('SKIP');
                    if ($this->fullPageCacheLogger['logDisallowedCookieParams'] === true) {
                        $requestCookieParams = $request->getCookieParams();
                        foreach ($requestCookieParams as $key => $value) {
                            if (!in_array($key, $this->ignoredCookieParams)) {
                                $disallowedCookieParams[] = $key;
                            }
                        }
                    }
                    if ($this->fullPageCacheLogger['logDisallowedQueryParams'] === true) {
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
                if ($this->fullPageCacheLogger['miss'] && $this->str_starts_with($fullPageCacheInfo, 'MISS')) {
                    $activity->setCacheInfo('MISS');
                }
                if ($this->fullPageCacheLogger['hit'] && $this->str_starts_with($fullPageCacheInfo, 'HIT')) {
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
     * TODO: Can be replaced by adding a dependency on PHP8+
     *
     * @param $haystack
     * @param $needle
     * @return bool
     */
    private function str_starts_with($haystack, $needle) {
        return strpos($haystack , $needle) === 0;
    }
}
