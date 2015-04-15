<?php
namespace Omeka\Model\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Omeka\Model\Entity\ResourceTemplateProperty;
use Omeka\Model\Entity\ResourceClass;
use Omeka\Model\Entity\User;

/**
 * @Entity
 */
class ResourceTemplate extends AbstractEntity
{
    /**
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     */
    protected $id;

    /**
     * @Column(unique=true)
     */
    protected $label;

    /**
     * @ManyToOne(targetEntity="User", inversedBy="resourceTemplates")
     * @JoinColumn(onDelete="SET NULL")
     */
    protected $owner;

    /**
     * @ManyToOne(targetEntity="ResourceClass")
     * @JoinColumn(onDelete="SET NULL")
     */
    protected $resourceClass;

    /**
     * @OneToMany(
     *     targetEntity="ResourceTemplateProperty",
     *     mappedBy="resourceTemplate",
     *     orphanRemoval=true,
     *     cascade={"persist", "remove"}
     * )
     * @OrderBy({"position" = "ASC"})
     */
    protected $resourceTemplateProperties;

    /**
     * @OneToMany(
     *     targetEntity="Resource",
     *     mappedBy="resourceClass",
     *     fetch="EXTRA_LAZY"
     * )
     */
    protected $resources;

    public function __construct()
    {
        $this->resourceTemplateProperties = new ArrayCollection;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setLabel($label)
    {
        $this->label = $label;
    }

    public function getLabel()
    {
        return $this->label;
    }

    public function setOwner(User $owner = null)
    {
        $this->synchronizeOneToMany($owner, 'owner', 'getResourceTemplates');
    }

    public function getOwner()
    {
        return $this->owner;
    }

    public function setResourceClass(ResourceClass $resourceClass = null)
    {
        $this->resourceClass = $resourceClass;
    }

    public function getResourceClass()
    {
        return $this->resourceClass;
    }

    public function getResourceTemplateProperties()
    {
        return $this->resourceTemplateProperties;
    }
}
