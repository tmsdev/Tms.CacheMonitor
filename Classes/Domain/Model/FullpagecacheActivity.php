<?php
namespace Tms\CacheMonitor\Domain\Model;

/*
 * This file is part of the Tms.CacheMonitor package.
 */

use Neos\Flow\Annotations as Flow;
use Doctrine\ORM\Mapping as ORM;

/**
 * @Flow\Entity
 */
class FullpagecacheActivity
{
    /**
     * @var \DateTime
     */
    protected $dateCreated;

    /**
     * @var string
     * @ORM\Column(length=4000)
     */
    protected $uri;

    /**
     * @var string
     * @ORM\Column(length=4)
     */
    protected $cacheInfo;

    /**
     * @var array
     * @ORM\Column(type="flow_json_array")
     */
    protected $disallowedCookieParams;

    /**
     * @var array
     * @ORM\Column(type="flow_json_array")
     */
    protected $disallowedQueryParams;

    /**
     * Constructs a DownloadLog object
     */
    public function __construct()
    {
        $this->dateCreated = new \DateTime();
    }

    /**
     * @return \DateTime
     */
    public function getDateCreated()
    {
        return $this->dateCreated;
    }

    /**
     * @return string
     */
    public function getUri()
    {
        return $this->uri;
    }

    /**
     * @param string $uri
     * @return void
     */
    public function setUri($uri)
    {
        $this->uri = $uri;
    }

    /**
     * @return string
     */
    public function getCacheInfo()
    {
        return $this->cacheInfo;
    }

    /**
     * @param string $cacheInfo
     * @return void
     */
    public function setCacheInfo($cacheInfo)
    {
        $this->cacheInfo = $cacheInfo;
    }

    /**
     * @return array
     */
    public function getDisallowedCookieParams()
    {
        return $this->disallowedCookieParams;
    }

    /**
     * @param array $disallowedCookieParams
     * @return void
     */
    public function setDisallowedCookieParams($disallowedCookieParams)
    {
        $this->disallowedCookieParams = $disallowedCookieParams;
    }

    /**
     * @return array
     */
    public function getDisallowedQueryParams()
    {
        return $this->disallowedQueryParams;
    }

    /**
     * @param array $disallowedQueryParams
     * @return void
     */
    public function setDisallowedQueryParams($disallowedQueryParams)
    {
        $this->disallowedQueryParams = $disallowedQueryParams;
    }
}
