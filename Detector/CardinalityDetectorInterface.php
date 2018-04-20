<?php

namespace CuriousInc\FileUploadFormTypeBundle\Detector;

/**
 * Interface CardinalityDetectorInterface.
 */
interface CardinalityDetectorInterface
{
    /**
     * Detect cardinality of the relation between given entity and the File entity, referenced by given property.
     *
     * Uses ORM Mapping annotations to detect the real mapped relation, or defaults to checking for the existence of an
     * `addProperty(s)` method.
     *
     * @param mixed  $entity       Either a string containing the name of the class to reflect, or an object.
     * @param string $fileProperty The property in given entity that links to the target entity
     *
     * @return bool
     */
    public function canHaveMultiple($entity, string $fileProperty): bool;
}
