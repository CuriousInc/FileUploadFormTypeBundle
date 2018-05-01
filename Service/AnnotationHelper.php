<?php
/**
 * Helper for getting annotation information about entity classes and domain objects
 *
 * @author Webber <webber@takken.io>
 */

namespace CuriousInc\FileUploadFormTypeBundle\Service;

use CuriousInc\FileUploadFormTypeBundle\Exception\MissingServiceException;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\Mapping\ManyToMany;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\Mapping\OneToOne;

/**
 * Class AnnotationHelper.
 */
class AnnotationHelper
{
    public const RELATION_NONE    = 100;
    public const RELATION_TO_ONE  = 101;
    public const RELATION_TO_MANY = 102;

    /** @var \Doctrine\Common\Annotations\AnnotationReader|null */
    private $reader;

    /**
     * AnnotationHelper constructor.
     *
     * @param \Doctrine\Common\Annotations\AnnotationReader|null $reader
     */
    public function __construct(?AnnotationReader $reader)
    {
        $this->reader = $reader;
    }

    /**
     * Get the annotations for a property
     *
     * @param mixed  $entity       The entity of which to get the properties annotations
     * @param string $propertyName The name of the property of which to get annotations
     *
     * @return array The annotations for entity's property
     */
    public function getPropertyAnnotations($entity, string $propertyName): array
    {
        if (null === $this->reader) {
            throw new MissingServiceException('Could not find instance of AnnotationReaderInterface.');
        }

        $reflectionClass = new \ReflectionClass($entity);

        return $this->reader->getPropertyAnnotations($reflectionClass->getProperty($propertyName));
    }

    /**
     * Check if property describes a collection.
     *
     * @param mixed  $entity       The entity of which to get the properties relation
     * @param string $propertyName The name of the property of which to get the relation
     *
     * @return bool Whether or not a property describes a collection
     */
    public function hasPropertyToManyRelation($entity, $propertyName)
    {
        $annotations = $this->getPropertyAnnotations($entity, $propertyName);

        foreach ($annotations as $annotation) {
            if ($annotation instanceof ManyToMany || $annotation instanceof OneToMany) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if property of entity has a OneToOne or ManyToOne doctrine annotation.
     *
     * @param mixed  $entity       The entity of which to get the properties relation
     * @param string $propertyName The name of the property of which to get the relation
     *
     * @return bool
     */
    public function hasPropertyToOneRelation($entity, $propertyName)
    {
        $propertyAnnotations = $this->getPropertyAnnotations($entity, $propertyName);

        foreach ($propertyAnnotations as $annotation) {
            if ($annotation instanceof OneToOne || $annotation instanceof ManyToOne) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the type of relation an entity's property has.
     *
     * @param mixed  $entity       The entity of which to get the properties relation
     * @param string $propertyName The name of the property of which to get the relation
     *
     * @return int One of this classes relation constants indicating the type of relation
     */
    public function getPropertyRelationType($entity, string $propertyName)
    {
        if ($this->hasPropertyToManyRelation($entity, $propertyName)) {
            return static::RELATION_TO_MANY;
        } elseif ($this->hasPropertyToOneRelation($entity, $propertyName)) {
            return static::RELATION_TO_ONE;
        } else {
            return static::RELATION_NONE;
        }
    }

    /**
     * Get the target entity of the relation of the property, if any relation exists.
     *
     * @param mixed $entity       The entity of which to get the target entity
     * @param string $propertyName The name of the property of which to get the target entity
     *
     * @return string|null String representation of target entity or null if no relation was found.
     */
    public function getTargetEntityForProperty($entity, string $propertyName): ?string
    {
        $propertyAnnotations = $this->getPropertyAnnotations($entity, $propertyName);

        foreach ($propertyAnnotations as $annotation) {
            if ($annotation instanceof OneToOne
                || $annotation instanceof OneToMany
                || $annotation instanceof ManyToOne
                || $annotation instanceof ManyToMany
            ) {
                // Doctrine relation
                return $annotation->targetEntity;
            }
        }

        return null;
    }
}
