<?php
namespace Tms\CacheMonitor\Domain\Model;

/*
 * This file is part of the Tms.CacheMonitor package.
 */

use Neos\Flow\Annotations as Flow;
use Doctrine\ORM\Mapping as ORM;
use Neos\Flow\Persistence\PersistenceManagerInterface;

/**
 * @Flow\Entity
 */
class ContentCacheFlushEvent
{
    /**
     * @Flow\Inject
     * @var PersistenceManagerInterface
     */
    protected $persistenceManager;

    /**
     * @var \DateTime
     */
    protected $dateCreated;

    /**
     * @var array
     * @ORM\Column(type="flow_json_array")
     */
    protected $tagsToFlush;

    /**
     * @var array
     * @ORM\Column(type="flow_json_array")
     */
    protected $workspacesToFlush;

    /**
     * @var array
     * @ORM\Column(type="flow_json_array")
     */
    protected $affectedEntries;

    /**
     * Constructs a ContentCacheFlushEvent object
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
     * @return array
     */
    public function getTagsToFlush()
    {
        return $this->tagsToFlush;
    }

    /**
     * @param array $tagsToFlush
     * @return void
     */
    public function setTagsToFlush($tagsToFlush)
    {
        $this->tagsToFlush = $tagsToFlush;
    }

    /**
     * @return array
     */
    public function getWorkspacesToFlush()
    {
        return $this->workspacesToFlush;
    }

    /**
     * @param array $workspacesToFlush
     * @return void
     */
    public function setWorkspacesToFlush($workspacesToFlush)
    {
        $this->workspacesToFlush = $workspacesToFlush;
    }

    /**
     * @return array
     */
    public function getAffectedEntries()
    {
        return $this->affectedEntries;
    }

    /**
     * @param array $affectedEntries
     * @return void
     */
    public function setAffectedEntries($affectedEntries)
    {
        $this->affectedEntries = $affectedEntries;
    }

    /**
     * @return string
     */
    public function getIdentifier()
    {
        return $this->persistenceManager->getIdentifierByObject($this);
    }
}
